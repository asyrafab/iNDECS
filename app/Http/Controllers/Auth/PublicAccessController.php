<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PublicIdentityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PublicAccessController extends Controller
{
    public function page(string $page = 'login')
    {
        return view('public.access', compact('page'));
    }

    public function register(Request $request, PublicIdentityService $identity)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);
        $email = Str::lower(trim($data['email']));
        $user = DB::transaction(function () use ($identity, $data, $email) {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['public-email:'.$email]);
            $existing = $identity->findByEmail($email);
            if ($existing) {
                return $existing->isPublicAccount() && ! $existing->email_verified_at ? $existing : null;
            }

            return $identity->create($email, $data['name'], $data['password']);
        });
        if ($user) {
            $this->sendLink($identity, $user, 'verify');
        }

        return redirect()->route('portal.login')->with('status', 'Jika emel layak, pautan pengesahan telah dihantar. Semak peti masuk anda.');
    }

    public function login(Request $request, PublicIdentityService $identity)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $key = 'public-login:'.hash('sha256', Str::lower($data['email']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Terlalu banyak percubaan. Cuba semula selepas satu minit.']);
        }
        $user = $identity->findByEmail($data['email']);
        if (! $user?->isPublicAccount() || ! $user->has_password || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Emel atau kata laluan tidak sah.']);
        }
        RateLimiter::clear($key);
        if (! $user->email_verified_at) {
            throw ValidationException::withMessages(['email' => 'Sahkan emel dahulu melalui pautan akses emel.']);
        }
        Auth::login($user, false);
        $request->session()->regenerate();

        return redirect()->route('portal.home');
    }

    public function requestLink(Request $request, PublicIdentityService $identity, string $purpose)
    {
        abort_unless(in_array($purpose, ['login', 'reset', 'verify'], true), 404);
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = Str::lower(trim($data['email']));
        $key = 'public-mail:'.hash('sha256', $email);
        if (! RateLimiter::tooManyAttempts($key, 1)) {
            RateLimiter::hit($key, 60);
            $user = DB::transaction(function () use ($identity, $email, $purpose) {
                DB::select('select pg_advisory_xact_lock(hashtext(?))', ['public-email:'.$email]);

                return $identity->findByEmail($email)
                    ?? ($purpose === 'login' ? $identity->create($email, 'Pemohon', null, 'guest') : null);
            });
            if ($user?->isPublicAccount()) {
                $this->sendLink($identity, $user, $purpose);
            }
        }

        return back()->with('status', 'Jika emel layak, pautan telah dihantar. Semak peti masuk anda.');
    }

    public function showLink(string $purpose, string $token)
    {
        abort_unless(in_array($purpose, ['login', 'reset', 'verify'], true) && preg_match('/^[a-f0-9]{64}$/', $token), 404);

        return response()->view('public.link', compact('purpose', 'token'))
            ->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store');
    }

    public function consumeLink(Request $request, PublicIdentityService $identity, string $purpose, string $token)
    {
        abort_unless(in_array($purpose, ['login', 'reset', 'verify'], true), 404);
        $data = $purpose === 'reset'
            ? $request->validate(['password' => ['required', 'confirmed', Password::min(12)]]) : [];
        $user = $identity->consume($token, $purpose, $data['password'] ?? null);
        Auth::login($user, false);
        $request->session()->regenerate();

        return redirect()->route('portal.home')->with('status', 'Akses emel telah disahkan.');
    }

    public function home(Request $request)
    {
        return view('public.home', ['user' => $request->user()]);
    }

    public function claim(Request $request)
    {
        $user = $request->user();
        abort_unless($user->email_verified_at && ! $user->has_password, 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'password' => ['required', 'confirmed', Password::min(12)]]);
        $user->forceFill($data + ['account_type' => 'public', 'has_password' => true])->save();

        return back()->with('status', 'Akaun berdaftar telah disimpan.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }

    private function sendLink(PublicIdentityService $identity, User $user, string $purpose): void
    {
        try {
            $identity->issue($user, $purpose);
        } catch (TransportExceptionInterface) {
            throw ValidationException::withMessages(['email' => 'Emel tidak dapat dihantar. Sila cuba semula kemudian.']);
        }
    }
}
