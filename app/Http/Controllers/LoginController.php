<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\TotpService;
use App\Domain\Oidc\SessionService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class LoginController extends Controller
{
    public function __construct(
        private SessionService $sessions,
        private AuditLogger $audit,
        private TotpService $totp,
    ) {}

    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $email = strtolower($data['email']);
        $keys = ['login:ip:'.$request->ip(), 'login:acct:'.sha1($email)];

        foreach ($keys as $k) {
            if (RateLimiter::tooManyAttempts($k, config('iam.login_max_attempts'))) {
                $this->audit->log('BRUTE_FORCE', 'user', $email, null, 'failure', 'high');

                return back()->withErrors(['email' => 'Terlalu banyak percobaan. Coba lagi beberapa menit lagi.'])->onlyInput('email');
            }
        }

        $user = User::where('email', $email)->first();
        // Hash::check selalu dijalankan agar waktu respons seragam
        $ok = $user && Hash::check($data['password'], $user->password) && $user->isActive();

        if (! $ok) {
            foreach ($keys as $k) {
                RateLimiter::hit($k, config('iam.login_decay_seconds'));
            }
            $this->audit->log('FAILED_LOGIN', 'user', $email, null, 'failure', 'warning');

            return back()->withErrors(['email' => 'Email atau password salah.'])->onlyInput('email');
        }

        foreach ($keys as $k) {
            RateLimiter::clear($k);
        }
        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $data['password']])->save();
        }

        if ($user->hasMfaEnabled()) {
            $request->session()->put([
                'mfa_pending_user_id' => $user->id,
                'mfa_pending_expires_at' => now()->addMinutes(5)->timestamp,
            ]);
            $this->audit->log('MFA_CHALLENGE_ISSUED', 'user', $user->id);

            return redirect('/login/mfa');
        }

        return $this->completeLogin($request, $user);
    }

    public function showMfa(Request $request)
    {
        $user = $this->pendingMfaUser($request);
        if (! $user) {
            return redirect('/login')->withErrors(['email' => 'Sesi verifikasi MFA berakhir. Silakan masuk kembali.']);
        }

        return view('auth.mfa', ['email' => $user->email]);
    }

    public function verifyMfa(Request $request)
    {
        $user = $this->pendingMfaUser($request);
        if (! $user) {
            return redirect('/login')->withErrors(['email' => 'Sesi verifikasi MFA berakhir. Silakan masuk kembali.']);
        }

        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);
        $rateKey = 'mfa:acct:'.$user->id.':'.$request->ip();
        if (RateLimiter::tooManyAttempts($rateKey, config('iam.login_max_attempts'))) {
            $this->audit->log('MFA_BRUTE_FORCE', 'user', $user->id, null, 'failure', 'high');

            return back()->withErrors(['code' => 'Terlalu banyak percobaan MFA. Coba lagi beberapa menit lagi.']);
        }

        $secret = $this->totp->decryptSecret($user);
        $input = trim($data['code']);
        $usedRecovery = false;
        $valid = ($secret && $this->totp->verify($secret, $input));

        if (! $valid && $this->totp->consumeRecoveryCode($user, $input)) {
            $valid = true;
            $usedRecovery = true;
        }

        if (! $valid) {
            RateLimiter::hit($rateKey, config('iam.login_decay_seconds'));
            $this->audit->log('MFA_FAILED', 'user', $user->id, null, 'failure', 'warning');

            return back()->withErrors(['code' => 'Kode TOTP atau recovery code tidak valid.']);
        }

        RateLimiter::clear($rateKey);
        $request->session()->forget(['mfa_pending_user_id', 'mfa_pending_expires_at']);

        return $this->completeLogin($request, $user, [
            'mfa' => true,
            'recovery_code' => $usedRecovery,
        ]);
    }

    public function logout(Request $request)
    {
        if ($session = $this->sessions->current($request)) {
            $this->sessions->terminate($session, 'logout');
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    private function pendingMfaUser(Request $request): ?User
    {
        $uid = $request->session()->get('mfa_pending_user_id');
        $exp = (int) $request->session()->get('mfa_pending_expires_at', 0);
        if (! $uid || $exp < now()->timestamp) {
            $request->session()->forget(['mfa_pending_user_id', 'mfa_pending_expires_at']);

            return null;
        }
        $user = User::find($uid);

        return ($user && $user->isActive() && $user->hasMfaEnabled()) ? $user : null;
    }

    private function completeLogin(Request $request, User $user, array $meta = [])
    {
        $intended = $request->session()->get('iam_intended');
        $request->session()->regenerate();
        Auth::login($user);
        $this->sessions->start($user, $request);
        $this->audit->log('LOGIN', 'user', $user->id, null, 'success', 'info', $meta);

        if ($intended) {
            $request->session()->forget('iam_intended');

            return redirect()->to($intended);
        }

        return redirect('/admin');
    }
}
