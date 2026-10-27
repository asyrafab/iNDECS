<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Notifications\PublicAccessLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PublicIdentityService
{
    public function findByEmail(string $email): ?User
    {
        return User::query()->whereRaw('lower(email) = ?', [Str::lower(trim($email))])->first();
    }

    public function create(string $email, string $name, ?string $password = null, string $type = 'public'): User
    {
        $user = new User;
        $user->forceFill([
            'email' => Str::lower(trim($email)), 'name' => $name,
            'password' => $password ?? Str::random(64), 'has_password' => $password !== null,
            'account_type' => $type, 'role' => 'public_applicant', 'department_id' => null,
            'is_active' => true,
        ])->save();

        return $user;
    }

    public function issue(User $user, string $purpose): void
    {
        if (! $user->isPublicAccount() || ! in_array($purpose, ['login', 'verify', 'reset'], true)) {
            return;
        }
        $token = bin2hex(random_bytes(32));
        DB::transaction(function () use ($user, $purpose, $token): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            DB::table('core.public_access_tokens')->updateOrInsert(
                ['user_id' => $user->id, 'purpose' => $purpose],
                ['token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(15)],
            );
        }, 3);
        $user->notify(new PublicAccessLink($token, $purpose));
    }

    public function consume(string $token, string $purpose, ?string $password = null): User
    {
        return DB::transaction(function () use ($token, $purpose, $password): User {
            $row = DB::table('core.public_access_tokens')->where('token_hash', hash('sha256', $token))
                ->where('purpose', $purpose)->where('expires_at', '>', now())->lockForUpdate()->first();
            $user = $row ? User::query()->lockForUpdate()->find($row->user_id) : null;
            if (! $user?->isPublicAccount() || ($purpose === 'reset' && $password === null)) {
                throw ValidationException::withMessages(['email' => 'Pautan tidak sah, telah digunakan atau tamat tempoh.']);
            }
            if (! $user->email_verified_at && $purpose === 'login') {
                // A mailbox owner must not inherit a password set by an unverified registrant.
                $user->password = Str::random(64);
                $user->has_password = false;
                $user->remember_token = Str::random(60);
                DB::table('sessions')->where('user_id', $user->id)->delete();
                DB::table('core.public_access_tokens')->where('user_id', $user->id)->delete();
            }
            $user->email_verified_at ??= now();
            if ($purpose === 'reset') {
                $user->password = $password;
                $user->has_password = true;
                $user->account_type = 'public';
                $user->remember_token = Str::random(60);
                DB::table('sessions')->where('user_id', $user->id)->delete();
                DB::table('core.public_access_tokens')->where('user_id', $user->id)->delete();
            } else {
                DB::table('core.public_access_tokens')->where('token_hash', $row->token_hash)->delete();
            }
            $user->save();

            return $user;
        }, 3);
    }
}
