<?php

namespace App\Http\Requests;

use App\Contracts\StaffAuthenticator;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => $this->usesMbsp() ? ['prohibited'] : ['required', 'email'],
            'staff_number' => $this->usesMbsp() ? ['required', 'string', 'regex:/^[0-9]{1,30}$/'] : ['prohibited'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function authenticate(): void
    {
        $field = $this->usesMbsp() ? 'staff_number' : 'email';
        $key = 'login:'.hash('sha256', Str::lower(trim($this->string($field)->toString())).'|'.$this->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                $field => "Terlalu banyak percubaan log masuk. Cuba semula dalam {$seconds} saat.",
            ]);
        }

        $failureMessage = 'Maklumat log masuk tidak sah atau akses belum dibenarkan.';

        if ($this->usesMbsp()) {
            try {
                $profile = app(StaffAuthenticator::class)->authenticate(
                    $this->string('staff_number')->toString(),
                    $this->string('password')->toString(),
                );
            } catch (RuntimeException $exception) {
                RateLimiter::hit($key, 60);

                throw ValidationException::withMessages([$field => $exception->getMessage()]);
            }

            $user = $profile === null ? null : User::query()
                ->where('staff_number', $profile['staff_number'])->where('is_active', true)->first();
            $authenticated = $user?->canAccessStaffWorkspace() === true;
            $failureMessage = $profile === null
                ? 'MBSP tidak mengesahkan nombor kakitangan atau kata laluan ini. Sila semak maklumat log masuk MBSP anda.'
                : 'Pengesahan MBSP berjaya, tetapi akses iNDECS belum dibenarkan. Sila hubungi pentadbir iNDECS.';

            if ($authenticated) {
                Auth::login($user, false);
            }
        } else {
            $authenticated = app()->environment(['local', 'testing'])
                && config('indecs.staff_auth_driver') === 'local'
                && Auth::attempt([
                    'email' => $this->string('email')->toString(),
                    'password' => $this->string('password')->toString(),
                    'is_active' => true,
                ], $this->boolean('remember'));
        }

        if (! $authenticated) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                $field => $failureMessage,
            ]);
        }

        RateLimiter::clear($key);
    }

    private function usesMbsp(): bool
    {
        return config('indecs.staff_auth_driver') !== 'local';
    }
}
