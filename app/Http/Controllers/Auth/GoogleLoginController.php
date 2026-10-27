<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PublicIdentityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleLoginController extends Controller
{
    public function redirect(Request $request)
    {
        abort_unless(config('services.google.client_id') && config('services.google.client_secret'), 503, 'Log masuk Google belum tersedia.');
        $request->session()->forget('google_link_user');
        if ($request->routeIs('portal.google.link')) {
            abort_unless($request->user()?->isPublicAccount() && $request->user()->email_verified_at, 403);
            $request->session()->put('google_link_user', $request->user()->id);
        }

        return Socialite::driver('google')->scopes(['openid', 'profile', 'email'])->redirect();
    }

    public function callback(Request $request, PublicIdentityService $identity)
    {
        $linkId = $request->session()->pull('google_link_user');
        try {
            $google = Socialite::driver('google')->user();
            $raw = $google->getRaw();
            if (($raw['email_verified'] ?? $raw['verified_email'] ?? false) !== true
                || ! is_string($google->getId()) || $google->getId() === ''
                || ! filter_var($google->getEmail(), FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException;
            }
            $email = Str::lower($google->getEmail());
            $user = DB::transaction(function () use ($identity, $google, $email, $linkId, $request) {
                DB::select('select pg_advisory_xact_lock(hashtext(?))', ['public-email:'.$email]);
                $linked = User::query()->where('google_subject', $google->getId())->first();
                if ($linkId !== null) {
                    $owner = $request->user();
                    if (! $owner?->isPublicAccount() || $owner->id !== $linkId || ! $owner->email_verified_at
                        || Str::lower($owner->email) !== $email || ($linked && $linked->id !== $owner->id)
                        || ($owner->google_subject && $owner->google_subject !== $google->getId())) {
                        throw new \RuntimeException;
                    }
                    $user = $owner;
                } else {
                    if ($linked) {
                        if (! $linked->isPublicAccount()) {
                            throw new \RuntimeException;
                        }

                        return $linked;
                    }
                    if ($identity->findByEmail($email)) {
                        throw ValidationException::withMessages(['email' => 'Emel ini telah mempunyai akaun. Log masuk melalui emel dahulu untuk memautkan Google.']);
                    }
                    $user = $identity->create($email, mb_substr($google->getName() ?: 'Pemohon', 0, 255));
                }
                $user->forceFill(['google_subject' => $google->getId(), 'email_verified_at' => now()])->save();

                return $user;
            });
        } catch (ValidationException $exception) {
            return redirect()->route('portal.login')->withErrors($exception->errors());
        } catch (Throwable) {
            return redirect()->route('portal.login')->withErrors(['email' => 'Log masuk Google tidak berjaya. Sila cuba semula.']);
        }
        Auth::login($user, false);
        $request->session()->regenerate();

        return redirect()->route('portal.home');
    }
}
