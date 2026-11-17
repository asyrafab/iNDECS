<?php

namespace App\Services\Identity;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SandboxIdentity
{
    public function enabled(): bool
    {
        return app()->environment(['local', 'testing', 'sandbox']) && config('mydigitalid.enabled')
            && (app()->environment('testing') || in_array(config('cache.default'), ['database', 'redis'], true));
    }

    public function guard(string $mode): void
    {
        abort_unless($this->enabled() && config('mydigitalid.'.$mode.'_enabled'), 404);
    }

    public function eligible(?User $user): bool
    {
        return $user?->isPublicAccount() && $user->email_verified_at
            && in_array((string) $user->id, array_map('strval', config('mydigitalid.participants')), true);
    }

    public function lookup(string $email): string
    {
        return $this->digest('email', mb_strtolower(trim($email)));
    }

    public function digest(string $purpose, string $value): string
    {
        return hash_hmac('sha256', config('mydigitalid.key_version').'|'.$purpose.'|'.$value, (string) config('app.key'));
    }

    public function browser(): string
    {
        $session = request()->session();
        if (! $session->has('identity_browser')) {
            $session->put('identity_browser', bin2hex(random_bytes(32)));
        }

        return $this->digest('browser', $session->get('identity_browser'));
    }

    public function linked(User $user): bool
    {
        return DB::table('core.external_identities')->where('user_id', $user->id)->where('provider', 'mydigitalid')
            ->where('environment', 'sandbox')->where('issuer', config('mydigitalid.issuer'))->where('active', true)->exists();
    }

    public function audit(User $user, string $event): void
    {
        AuditLog::query()->create(['auditable_type' => 'sandbox_identity', 'auditable_id' => (string) $user->id,
            'actor_id' => $user->id, 'event' => 'identity.'.$event, 'after_values' => ['environment' => 'sandbox']]);
    }
}
