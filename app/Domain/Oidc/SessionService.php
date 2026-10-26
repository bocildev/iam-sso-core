<?php

namespace App\Domain\Oidc;

use App\Domain\Audit\AuditLogger;
use App\Models\Application;
use App\Models\SsoSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SessionService
{
    public function __construct(private TokenIssuer $issuer, private AuditLogger $audit) {}

    public function start(User $user, Request $request): SsoSession
    {
        $session = SsoSession::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 250),
            'expires_at' => now()->addSeconds(config('iam.sso_session_ttl')),
        ]);
        $user->forceFill(['last_login_at' => now()])->save();
        if ($request->hasSession()) {
            $request->session()->put('iam_sid', $session->id);
        }

        return $session;
    }

    /** Buat sesi khusus aplikasi klien (App-Scoped) tanpa mengubah cookie sesi pusat IAM. */
    public function startClientAppSession(User $user, Request $request): SsoSession
    {
        $session = SsoSession::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 250),
            'expires_at' => now()->addSeconds(config('iam.sso_session_ttl')),
        ]);
        $user->forceFill(['last_login_at' => now()])->save();

        return $session;
    }

    /** Sesi SSO valid milik browser ini (atau null). */
    public function current(Request $request): ?SsoSession
    {
        $sid = $request->session()->get('iam_sid');
        $session = $sid ? SsoSession::find($sid) : null;
        if (! $session || ! $session->isValid()) {
            return null;
        }
        $user = User::find($session->user_id);

        return $user && $user->isActive() ? $session : null;
    }

    /** Cabut sesi, semua refresh token-nya, dan beritahu app via back-channel. */
    public function terminate(SsoSession $session, string $reason = 'logout'): void
    {
        $session->forceFill(['revoked_at' => $session->revoked_at ?? now()])->save();

        DB::table('oauth_refresh_tokens')->where('sso_session_id', $session->id)
            ->whereNull('revoked_at')->update(['revoked_at' => now()]);

        $user = User::find($session->user_id);
        $appIds = DB::table('oauth_refresh_tokens')->where('sso_session_id', $session->id)->pluck('application_id')
            ->merge(DB::table('oauth_authorization_codes')->where('sso_session_id', $session->id)->pluck('application_id'))
            ->unique();

        foreach (Application::whereIn('id', $appIds)->whereNotNull('backchannel_logout_uri')->get() as $app) {
            try {
                Http::timeout(3)->asForm()->post($app->backchannel_logout_uri, [
                    'logout_token' => $this->issuer->logoutToken($app, (string) $user?->sub, $session->id),
                ]);
            } catch (\Throwable $e) {
                $this->audit->log('BACKCHANNEL_LOGOUT_FAILED', 'system', null, $app->slug, 'failure', 'warning', ['error' => $e->getMessage()]);
            }
        }

        $this->audit->log('SESSION_TERMINATION', 'user', $user?->id, $session->id, 'success', 'info', ['reason' => $reason]);
    }

    public function terminateAllForUser(User $user, string $reason): void
    {
        SsoSession::where('user_id', $user->id)->whereNull('revoked_at')->get()
            ->each(fn ($s) => $this->terminate($s, $reason));
    }
}
