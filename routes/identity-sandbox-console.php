<?php

use App\Models\User;
use App\Services\Identity\SandboxIdentity;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('identity:sandbox-cleanup', function (): int {
    if (! app()->environment(['local', 'testing', 'sandbox'])) {
        $this->error('Sandbox only.');

        return 1;
    }
    foreach (['identity_challenges', 'identity_transactions'] as $table) {
        DB::table('core.'.$table)->where('expires_at', '<=', now())->delete();
    }
    $this->info('Expired sandbox proofs removed. Audit history retained.');

    return 0;
});

Artisan::command('identity:sandbox-revoke {user : Existing local user ID}', function (): int {
    if (! app()->environment(['local', 'testing', 'sandbox'])) {
        $this->error('Sandbox only.');

        return 1;
    }
    DB::transaction(function (): void {
        $user = User::query()->whereKey($this->argument('user'))->lockForUpdate()->firstOrFail();
        DB::table('core.external_identities')->where('user_id', $user->id)->where('environment', 'sandbox')->update(['active' => false]);
        DB::table('core.identity_login_contacts')->where('user_id', $user->id)->update(['active' => false]);
        DB::table('core.identity_challenges')->where('user_id', $user->id)->update(['active' => false]);
        DB::table('core.identity_transactions')->where('user_id', $user->id)->delete();
        app(SandboxIdentity::class)->audit($user, 'revoked');
    });
    $this->info('Sandbox identity revoked. Pilot sessions end on their next request. Legacy sessions are unchanged.');

    return 0;
});
