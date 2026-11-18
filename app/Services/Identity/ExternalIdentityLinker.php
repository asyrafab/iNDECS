<?php

namespace App\Services\Identity;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class ExternalIdentityLinker
{
    public function __construct(private SandboxIdentity $identity) {}

    public function enrolment(User $user): string
    {
        return $this->create('link', $user->id, 'mailbox', ['email' => $this->identity->lookup($user->email), 'mail_at' => now()->timestamp]);
    }

    public function login(): string
    {
        return $this->create('login', null, 'oidc', []);
    }

    private function create(string $intent, ?int $userId, string $stage, array $payload): string
    {
        $id = (string) Str::uuid();
        $payload += ['state' => bin2hex(random_bytes(32)), 'nonce' => bin2hex(random_bytes(32)), 'code_verifier' => bin2hex(random_bytes(32))];
        DB::table('core.identity_transactions')->insert(['id' => $id, 'user_id' => $userId, 'intent' => $intent, 'stage' => $stage,
            'browser' => $this->identity->browser(), 'payload' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)),
            'expires_at' => now()->addSeconds(config('mydigitalid.ttl'))]);

        return $id;
    }

    public function transaction(string $id, ?User $user, string $stage): array
    {
        $row = DB::table('core.identity_transactions')->where('id', $id)->first();
        if (! $row || $row->stage !== $stage || Carbon::parse($row->expires_at)->isPast()
            || ! hash_equals($row->browser, $this->identity->browser())
            || ($row->intent === 'link' && (! $this->identity->eligible($user) || $row->user_id !== $user->id))
            || ($row->intent === 'login' && $user !== null)) {
            throw new RuntimeException('Invalid identity transaction.');
        }

        return ['row' => $row, 'proof' => json_decode(Crypt::decryptString($row->payload), true, 32, JSON_THROW_ON_ERROR)];
    }

    public function beginLink(string $id, User $user): array
    {
        $transaction = $this->transaction($id, $user, 'mailbox');
        DB::table('core.identity_transactions')->where('id', $id)->where('stage', 'mailbox')->update(['stage' => 'oidc']);
        $this->identity->audit($user, 'link_started');

        return $transaction['proof'];
    }

    public function takeCallback(string $id, ?User $user, string $state): array
    {
        return DB::transaction(function () use ($id, $user, $state): array {
            DB::table('core.identity_transactions')->where('id', $id)->lockForUpdate()->first();
            $transaction = $this->transaction($id, $user, 'oidc');
            // Claim once before any provider call. Bad state also burns the transaction.
            DB::table('core.identity_transactions')->where('id', $id)->update(['stage' => 'consumed']);

            return $transaction + ['valid_state' => hash_equals($transaction['proof']['state'], $state)];
        });
    }

    public function pending(string $id, array $proof, VerifiedIdentity $verified): void
    {
        $proof['identity'] = $verified->toArray();
        DB::table('core.identity_transactions')->where('id', $id)->where('stage', 'consumed')->update([
            'stage' => 'confirm', 'payload' => Crypt::encryptString(json_encode($proof, JSON_THROW_ON_ERROR)),
        ]);
    }

    public function confirm(string $id, User $owner): void
    {
        DB::transaction(function () use ($id, $owner): void {
            $user = User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
            DB::table('core.identity_transactions')->where('id', $id)->lockForUpdate()->first();
            $transaction = $this->transaction($id, $user, 'confirm');
            $proof = $transaction['proof'];
            $verified = $proof['identity'];
            if ($proof['email'] !== $this->identity->lookup($user->email) || $proof['mail_at'] < now()->timestamp - 300
                || $verified['authenticated_at'] < now()->timestamp - 300 || $verified['authenticated_at'] > now()->timestamp + 5
                || $verified['issuer'] !== config('mydigitalid.issuer') || $verified['subject'] === '') {
                throw new RuntimeException('Stale identity proof.');
            }
            // Serialize competing subjects and normalized mailbox activation across users.
            foreach (['identity:'.$verified['issuer'].'|'.$verified['subject'], 'identity-email:'.$proof['email']] as $lock) {
                DB::select('select pg_advisory_xact_lock(hashtext(?))', [$lock]);
            }
            if (User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($user->email))])->where('id', '!=', $user->id)->exists()) {
                throw new RuntimeException('Contact conflict.');
            }
            $linked = DB::table('core.external_identities')->where('provider', 'mydigitalid')->where('issuer', $verified['issuer'])->where('subject', $verified['subject'])->first();
            $own = DB::table('core.external_identities')->where('user_id', $user->id)->where('provider', 'mydigitalid')->where('environment', 'sandbox')->first();
            if (($linked && ($linked->user_id !== $user->id || ! $linked->active)) || ($own && (! $linked || $own->id !== $linked->id))) {
                throw new RuntimeException('Identity conflict.');
            }
            if (! $linked) {
                DB::table('core.external_identities')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id,
                    'provider' => 'mydigitalid', 'environment' => 'sandbox', 'issuer' => $verified['issuer'], 'subject' => $verified['subject'],
                    'official_name' => Crypt::encryptString($verified['name']), 'created_at' => now(), 'updated_at' => now()]);
            }
            $contact = DB::table('core.identity_login_contacts')->where('user_id', $user->id)->first();
            if ($contact && (! $contact->active || $contact->lookup !== $proof['email'])) {
                throw new RuntimeException('Contact replacement requires recovery.');
            }
            if (! $contact) {
                DB::table('core.identity_login_contacts')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id,
                    'destination' => Crypt::encryptString($user->email), 'lookup' => $proof['email'], 'key_version' => config('mydigitalid.key_version'), 'verified_at' => now()]);
            }
            $this->identity->audit($user, 'linked');
            DB::table('core.identity_transactions')->where('id', $id)->delete();
        });
    }
}
