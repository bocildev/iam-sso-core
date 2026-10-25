<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Oidc\SessionService;
use App\Domain\Tenancy\OutboxService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordResetController extends Controller
{
    public function __construct(
        private SessionService $sessions,
        private OutboxService $outbox,
        private AuditLogger $audit,
    ) {}

    public function showForgot()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $email = strtolower($data['email']);

        PasswordBroker::sendResetLink(['email' => $email]);
        $this->audit->log('PASSWORD_RESET_REQUESTED', 'user', $email);

        return back()->with('ok', 'Jika email terdaftar, tautan reset password telah dikirim ke kotak masuk Anda.');
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        $status = PasswordBroker::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $this->sessions->terminateAllForUser($user, 'password_reset');
                $this->outbox->publishForUser($user, 'user.updated');
                $this->audit->log('PASSWORD_RESET', 'user', $user->id, $user->sub, 'success', 'warning');
            }
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            $this->audit->log('PASSWORD_RESET_FAILED', 'user', strtolower((string) $request->input('email')), null, 'failure', 'warning');

            return back()->withErrors(['email' => 'Tautan reset password tidak valid atau sudah kedaluwarsa.'])->onlyInput('email');
        }

        return redirect('/login')->with('ok', 'Password berhasil diperbarui. Silakan masuk dengan password baru Anda.');
    }
}
