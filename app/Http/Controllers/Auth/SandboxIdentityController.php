<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\Identity\MyDigitalIdClient;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Identity\EmailOtpService;
use App\Services\Identity\ExternalIdentityLinker;
use App\Services\Identity\SandboxIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

final class SandboxIdentityController extends Controller
{
    public function __construct(private SandboxIdentity $identity, private EmailOtpService $otp, private ExternalIdentityLinker $linker) {}

    public function page(Request $request)
    {
        $maskedIdentity = null;
        if ($request->user() && $request->session()->has('identity_transaction')) {
            try {
                $proof = $this->linker->transaction($request->session()->get('identity_transaction'), $request->user(), 'confirm')['proof'];
                $maskedIdentity = mb_substr($proof['identity']['name'], 0, 1).'…';
            } catch (Throwable) {
                // Other transaction stages do not expose pending identity information.
            }
        }

        return view('public.identity-sandbox', compact('maskedIdentity'));
    }

    private function owner(Request $request): User
    {
        abort_unless($this->identity->eligible($request->user()), 403);

        return $request->user();
    }

    public function enrol(Request $request)
    {
        $this->identity->guard('enrolment');
        $user = $this->owner($request);
        $request->session()->forget('identity_transaction');
        $id = $this->otp->issue($user->email, 'mydigitalid_enrolment', $user);
        $request->session()->put('identity_enrolment_challenge', $id);

        return $this->message();
    }

    public function verifyEnrolment(Request $request)
    {
        $this->identity->guard('enrolment');
        $owner = $this->owner($request);
        $code = $request->validate(['code' => ['required', 'regex:/^[0-9]{6}$/']])['code'];
        if (! $this->otp->consume((string) $request->session()->get('identity_enrolment_challenge'), $code, 'mydigitalid_enrolment', $owner)) {
            return $this->failed();
        }
        $request->session()->forget('identity_enrolment_challenge');
        $request->session()->put('identity_transaction', $this->linker->enrolment($owner));

        return back()->with('status', 'Emel disahkan. Teruskan untuk memautkan MyDigital ID.');
    }

    public function link(Request $request, MyDigitalIdClient $client)
    {
        $this->identity->guard('enrolment');
        $owner = $this->owner($request);
        try {
            $proof = $this->linker->beginLink((string) $request->session()->get('identity_transaction'), $owner);

            return redirect()->away($client->authorizationUrl($proof));
        } catch (Throwable) {
            $request->session()->forget('identity_transaction');

            return $this->failed();
        }
    }

    public function login(Request $request, MyDigitalIdClient $client)
    {
        $this->identity->guard('login');
        abort_if($request->user() !== null, 403);
        try {
            $id = $this->linker->login();
            $request->session()->put('identity_transaction', $id);
            $transaction = $this->linker->transaction($id, null, 'oidc');

            return redirect()->away($client->authorizationUrl($transaction['proof']));
        } catch (Throwable) {
            $request->session()->forget('identity_transaction');

            return $this->failed();
        }
    }

    public function callback(Request $request, MyDigitalIdClient $client)
    {
        try {
            $id = (string) $request->session()->get('identity_transaction');
            $transaction = $this->linker->takeCallback($id, $request->user(), (string) $request->query('state'));
            $intent = $transaction['row']->intent;
            $this->identity->guard($intent === 'link' ? 'enrolment' : 'login');
            if (! $transaction['valid_state'] || $request->has('error') || ! is_string($request->query('code')) || $request->query('code') === '') {
                throw new \RuntimeException;
            }
            $verified = $client->complete($request->query('code'), $transaction['proof']);
            if ($intent === 'link') {
                $this->linker->pending($id, $transaction['proof'], $verified);

                return redirect()->route('portal.identity.page')->with('status', 'Identiti disahkan. Sahkan pautan untuk akaun ini.');
            }
            $request->session()->forget('identity_transaction');
            $link = DB::table('core.external_identities')->where('provider', 'mydigitalid')->where('environment', 'sandbox')
                ->where('issuer', config('mydigitalid.issuer'))->where('issuer', $verified->issuer)->where('subject', $verified->subject)->where('active', true)->first();
            $user = $link ? User::find($link->user_id) : null;
            if (! $this->identity->eligible($user)) {
                throw new \RuntimeException;
            }
            $this->identity->audit($user, 'sso_authenticated');

            return $this->authenticate($request, $user, 'mydigitalid');
        } catch (Throwable) {
            $request->session()->forget('identity_transaction');

            return $this->failed();
        }
    }

    public function confirm(Request $request)
    {
        $this->identity->guard('enrolment');
        $owner = $this->owner($request);
        try {
            $this->linker->confirm((string) $request->session()->get('identity_transaction'), $owner);
            $request->session()->forget('identity_transaction');
            $request->session()->regenerate();

            return back()->with('status', 'MyDigital ID dan emel OTP telah dipautkan.');
        } catch (Throwable) {
            $request->session()->forget('identity_transaction');

            return $this->failed();
        }
    }

    public function requestOtp(Request $request)
    {
        $this->identity->guard('login');
        abort_if($request->user() !== null, 403);
        $email = $request->validate(['email' => ['required', 'email', 'max:255']])['email'];
        $request->session()->put('identity_login_challenge', $this->otp->issue($email, 'email_login'));

        return $this->message();
    }

    public function verifyOtp(Request $request)
    {
        $this->identity->guard('login');
        abort_if($request->user() !== null, 403);
        $code = $request->validate(['code' => ['required', 'regex:/^[0-9]{6}$/']])['code'];
        $user = $this->otp->consume((string) $request->session()->get('identity_login_challenge'), $code, 'email_login');
        if (! $user) {
            return $this->failed();
        }

        return $this->authenticate($request, $user, 'email_otp');
    }

    private function authenticate(Request $request, User $user, string $method)
    {
        Auth::login($user, false);
        $request->session()->forget(['identity_login_challenge', 'identity_transaction', 'identity_enrolment_challenge']);
        $request->session()->regenerate();
        $request->session()->put('sandbox_identity_auth', ['user' => $user->id, 'method' => $method, 'started' => now()->timestamp, 'last' => now()->timestamp]);

        return redirect()->route('portal.home');
    }

    private function message()
    {
        return back()->with('status', 'Jika maklumat ini layak untuk log masuk, kod OTP akan dihantar. Sila semak emel anda.');
    }

    private function failed()
    {
        RateLimiter::attempt('identity:rejection-log', 20, fn () => logger()->notice('identity.sandbox.rejected'), 60);

        return redirect()->route('portal.identity.page')->withErrors(['identity' => 'Akses tidak dapat disahkan. Sila cuba semula atau log masuk melalui kaedah sedia ada.']);
    }
}
