<?php

namespace App\Services\Identity;

use App\Models\User;
use App\Notifications\SandboxIdentityCode;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class EmailOtpService
{
    public function __construct(private SandboxIdentity $identity) {}

    private function budget(string $key, int $limit, int $seconds): bool
    {
        try {
            return Cache::lock('lock:'.$key, 5)->block(1, function () use ($key, $limit, $seconds): bool {
                if (RateLimiter::tooManyAttempts($key, $limit)) {
                    return false;
                }
                RateLimiter::hit($key, $seconds);

                return true;
            });
        } catch (LockTimeoutException) {
            return false;
        }
    }

    public function issue(string $email, string $purpose, ?User $owner = null): string
    {
        $lookup = $this->identity->lookup($email);
        $id = (string) Str::uuid();
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $allowed = $this->budget('identity:send:ip:'.$this->identity->digest('ip', request()->ip()), 20, 900)
            && $this->budget('identity:send:global', 100, 900)
            && $this->budget('identity:send:'.$lookup, 5, 900)
            && $this->budget('identity:cooldown:'.$lookup, 1, 60);
        if (! $allowed) {
            $existing = DB::table('core.identity_challenges')->where('lookup', $lookup)->where('purpose', $purpose)
                ->where('browser', $this->identity->browser())->where('active', true)->where('expires_at', '>', now())->first();
            if ($existing) {
                return $existing->id;
            }
        }
        $candidate = $owner ?? User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->first();
        $user = $allowed && $this->identity->eligible($candidate) ? $candidate : null;
        if ($purpose === 'email_login' && $user) {
            $contact = DB::table('core.identity_login_contacts')->where('user_id', $user->id)->where('active', true)->first();
            if (! $contact || ! hash_equals($contact->lookup, $lookup) || ! $this->identity->linked($user)) {
                $user = null;
            }
        }

        DB::transaction(function () use ($user, $id, $purpose, $lookup, $code): void {
            if ($user) {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                DB::table('core.identity_challenges')->where('user_id', $user->id)->where('purpose', $purpose)->update(['active' => false]);
            }
            DB::table('core.identity_challenges')->insert(['id' => $id, 'user_id' => $user?->id, 'purpose' => $purpose,
                'browser' => $this->identity->browser(), 'lookup' => $lookup, 'digest' => $this->identity->digest('code:'.$id, $code),
                'key_version' => config('mydigitalid.key_version'), 'expires_at' => now()->addSeconds(config('mydigitalid.ttl')),
                'created_at' => now(), 'active' => $user !== null]);
        });
        if ($user) {
            try {
                // Synchronous array mail only: no plaintext code in queue/session/database/log.
                $user->notify(new SandboxIdentityCode($code, $purpose));
            } catch (\Throwable) {
                DB::table('core.identity_challenges')->where('id', $id)->update(['active' => false]);
            }
        }

        return $id;
    }

    public function consume(string $id, string $code, string $purpose, ?User $owner = null): ?User
    {
        $row = DB::table('core.identity_challenges')->where('id', $id)->first();
        if (! $row || ! $this->budget('identity:verify:ip:'.$this->identity->digest('ip', request()->ip()), 50, 900)
            || ! $this->budget('identity:verify:'.$row->lookup, 10, 900)) {
            return null;
        }

        // Commit wrong attempts rather than throwing an exception inside the transaction.
        return DB::transaction(function () use ($id, $code, $purpose, $owner): ?User {
            $hint = DB::table('core.identity_challenges')->where('id', $id)->first();
            $user = $hint?->user_id ? User::query()->whereKey($hint->user_id)->lockForUpdate()->first() : null;
            $row = DB::table('core.identity_challenges')->where('id', $id)->lockForUpdate()->first();
            if (! $row || ! $row->active || $row->purpose !== $purpose || Carbon::parse($row->expires_at)->isPast()
                || $row->attempts >= 5 || $row->key_version !== config('mydigitalid.key_version')
                || ! hash_equals($row->browser, $this->identity->browser()) || ! $this->identity->eligible($user)
                || ($owner && $owner->id !== $user->id) || ! hash_equals($row->lookup, $this->identity->lookup($user->email))) {
                return null;
            }
            DB::table('core.identity_challenges')->where('id', $id)->increment('attempts');
            if (! hash_equals($row->digest, $this->identity->digest('code:'.$id, $code))) {
                return null;
            }
            if ($purpose === 'email_login') {
                $contact = DB::table('core.identity_login_contacts')->where('user_id', $user->id)->where('active', true)->first();
                if (! $contact || ! $this->identity->linked($user) || ! hash_equals($contact->lookup, $row->lookup)
                    || $contact->key_version !== config('mydigitalid.key_version')
                    || $this->identity->lookup(Crypt::decryptString($contact->destination)) !== $row->lookup) {
                    return null;
                }
            }
            DB::table('core.identity_challenges')->where('id', $id)->update(['active' => false]);
            $this->identity->audit($user, $purpose === 'email_login' ? 'otp_authenticated' : 'mailbox_verified');

            return $user;
        });
    }
}
