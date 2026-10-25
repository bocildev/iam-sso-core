<?php

namespace App\Domain\Oidc;

use App\Domain\Access\AccessResolver;
use App\Models\Application;
use App\Models\SsoSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthorizationService
{
    public function __construct(private AccessResolver $access) {}

    /**
     * Validasi request /oauth/authorize.
     * Error `client`/`redirect_uri` TIDAK boleh di-redirect (status 400 langsung).
     *
     * @return array{app: Application, params: array}
     */
    public function validate(array $q): array
    {
        $app = Application::where('client_id', (string) ($q['client_id'] ?? ''))->first();
        if (! $app || ! $app->isActive()) {
            throw new OidcException('invalid_client', 'Aplikasi tidak dikenal atau nonaktif.', 400);
        }
        $redirect = (string) ($q['redirect_uri'] ?? '');
        if (! in_array($redirect, $app->redirect_uris ?? [], true)) {
            throw new OidcException('invalid_request', 'redirect_uri tidak terdaftar.', 400);
        }

        $fail = fn (string $e, string $d) => (new OidcException($e, $d, 302))->withRedirect($redirect, $q['state'] ?? null);

        if (($q['response_type'] ?? '') !== 'code') {
            throw $fail('unsupported_response_type', 'Hanya response_type=code.');
        }
        $scopes = array_filter(explode(' ', (string) ($q['scope'] ?? '')));
        if (! in_array('openid', $scopes, true)) {
            throw $fail('invalid_scope', 'Scope openid wajib.');
        }
        $scopes = array_values(array_intersect($scopes, config('iam.scopes_supported')));

        $challenge = $q['code_challenge'] ?? null;
        if ($challenge !== null) {
            if (($q['code_challenge_method'] ?? '') !== 'S256') {
                throw $fail('invalid_request', 'code_challenge_method harus S256.');
            }
        } elseif ($app->isPublicClient()) {
            throw $fail('invalid_request', 'PKCE wajib untuk public client.');
        }

        return ['app' => $app, 'params' => [
            'redirect_uri' => $redirect,
            'scope' => implode(' ', $scopes),
            'state' => $q['state'] ?? null,
            'nonce' => $q['nonce'] ?? null,
            'code_challenge' => $challenge,
        ]];
    }

    public function mayAccess(User $user, Application $app): bool
    {
        return $user->isActive() && $this->access->hasAppAccess($user, $app);
    }

    /** @return string kode plaintext (hanya hash disimpan) */
    public function issueCode(Application $app, User $user, SsoSession $session, array $params): string
    {
        $code = Str::random(48);
        DB::table('oauth_authorization_codes')->insert([
            'code_hash' => hash('sha256', $code),
            'application_id' => $app->id,
            'user_id' => $user->id,
            'sso_session_id' => $session->id,
            'redirect_uri' => $params['redirect_uri'],
            'code_challenge' => $params['code_challenge'],
            'nonce' => $params['nonce'],
            'scope' => $params['scope'],
            'expires_at' => now()->addSeconds(config('iam.auth_code_ttl')),
        ]);

        return $code;
    }
}
