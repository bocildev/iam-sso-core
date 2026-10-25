<?php

namespace App\Domain\Oidc;

use App\Domain\Access\AccessResolver;
use App\Domain\Audit\AuditLogger;
use App\Models\Application;
use App\Models\SsoSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TokenService
{
    public function __construct(
        private TokenIssuer $issuer,
        private AccessResolver $access,
        private AuditLogger $audit,
    ) {}

    /** Autentikasi client: confidential wajib secret, public cukup client_id. */
    public function authenticateClient(?string $clientId, ?string $secret): Application
    {
        $app = $clientId ? Application::where('client_id', $clientId)->first() : null;
        if (! $app || ! $app->isActive()) {
            throw new OidcException('invalid_client', 'Client tidak valid.', 401);
        }
        if (! $app->isPublicClient()) {
            if (! $secret || ! hash_equals($app->client_secret_hash, hash('sha256', $secret))) {
                $this->audit->log('CLIENT_AUTH_FAILED', 'client', $app->client_id, $app->slug, 'failure', 'warning');
                throw new OidcException('invalid_client', 'Autentikasi client gagal.', 401);
            }
        }

        return $app;
    }

    public function handle(array $in, Application $app): array
    {
        return match ($in['grant_type'] ?? '') {
            'authorization_code' => $this->exchangeCode($in, $app),
            'refresh_token' => $this->refresh($in, $app),
            default => throw new OidcException('unsupported_grant_type', 'grant_type tidak didukung.'),
        };
    }

    private function exchangeCode(array $in, Application $app): array
    {
        $hash = hash('sha256', (string) ($in['code'] ?? ''));

        $row = DB::transaction(function () use ($hash) {
            $row = DB::table('oauth_authorization_codes')->where('code_hash', $hash)->lockForUpdate()->first();
            if ($row && $row->used_at === null) {
                DB::table('oauth_authorization_codes')->where('code_hash', $hash)->update(['used_at' => now()]);
                $row->first_use = true;
            } elseif ($row) {
                $row->first_use = false;
            }

            return $row;
        });

        if (! $row || (int) $row->application_id !== (int) $app->id) {
            throw new OidcException('invalid_grant', 'Kode tidak valid.');
        }
        if (! $row->first_use) {
            // Replay kode: cabut token turunan sesi ini
            DB::table('oauth_refresh_tokens')->where('sso_session_id', $row->sso_session_id)
                ->where('application_id', $app->id)->update(['revoked_at' => now()]);
            $this->audit->log('AUTH_CODE_REPLAY', 'client', $app->client_id, $app->slug, 'failure', 'high');
            throw new OidcException('invalid_grant', 'Kode sudah dipakai.');
        }
        if (now()->gt($row->expires_at)) {
            throw new OidcException('invalid_grant', 'Kode kedaluwarsa.');
        }
        if (($in['redirect_uri'] ?? '') !== $row->redirect_uri) {
            throw new OidcException('invalid_grant', 'redirect_uri tidak cocok.');
        }
        if ($row->code_challenge !== null
            && ! Pkce::verify((string) ($in['code_verifier'] ?? ''), $row->code_challenge)) {
            throw new OidcException('invalid_grant', 'PKCE verifier salah.');
        }

        $user = User::find($row->user_id);
        $session = SsoSession::find($row->sso_session_id);
        $this->assertUsable($user, $session, $app);

        return $this->mint($user, $app, $session, $row->scope, $row->nonce, (string) Str::uuid(), $session->created_at->timestamp);
    }

    private function refresh(array $in, Application $app): array
    {
        $hash = hash('sha256', (string) ($in['refresh_token'] ?? ''));

        $row = DB::transaction(function () use ($hash) {
            $row = DB::table('oauth_refresh_tokens')->where('token_hash', $hash)->lockForUpdate()->first();
            if ($row && $row->used_at === null && $row->revoked_at === null) {
                DB::table('oauth_refresh_tokens')->where('token_hash', $hash)->update(['used_at' => now()]);
                $row->first_use = true;
            } elseif ($row) {
                $row->first_use = false;
            }

            return $row;
        });

        if (! $row || (int) $row->application_id !== (int) $app->id) {
            throw new OidcException('invalid_grant', 'Refresh token tidak valid.');
        }
        if (! $row->first_use) {
            // Reuse terdeteksi: cabut seluruh keluarga token
            DB::table('oauth_refresh_tokens')->where('family_id', $row->family_id)->update(['revoked_at' => now()]);
            $this->audit->log('REFRESH_TOKEN_REUSE', 'client', $app->client_id, $app->slug, 'failure', 'high');
            throw new OidcException('invalid_grant', 'Refresh token tidak valid.');
        }
        if (now()->gt($row->expires_at)) {
            throw new OidcException('invalid_grant', 'Refresh token kedaluwarsa.');
        }

        $user = User::find($row->user_id);
        $session = SsoSession::find($row->sso_session_id);
        $this->assertUsable($user, $session, $app);

        return $this->mint($user, $app, $session, $row->scope, null, $row->family_id, $session->created_at->timestamp);
    }

    private function assertUsable(?User $user, ?SsoSession $session, Application $app): void
    {
        if (! $user || ! $user->isActive() || ! $session || ! $session->isValid()
            || ! $this->access->hasAppAccess($user, $app)) {
            throw new OidcException('invalid_grant', 'Sesi atau akses tidak lagi valid.');
        }
    }

    private function mint(User $user, Application $app, SsoSession $session, string $scope, ?string $nonce, string $family, int $authTime): array
    {
        $access = $this->issuer->accessToken($user, $app, $session->id, $scope);
        $refresh = Str::random(64);
        DB::table('oauth_refresh_tokens')->insert([
            'token_hash' => hash('sha256', $refresh),
            'family_id' => $family,
            'application_id' => $app->id,
            'user_id' => $user->id,
            'sso_session_id' => $session->id,
            'scope' => $scope,
            'expires_at' => now()->addSeconds(config('iam.refresh_token_ttl')),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $out = [
            'token_type' => 'Bearer',
            'access_token' => $access,
            'expires_in' => config('iam.access_token_ttl'),
            'refresh_token' => $refresh,
            'scope' => $scope,
        ];
        if (in_array('openid', explode(' ', $scope), true)) {
            $out['id_token'] = $this->issuer->idToken($user, $app, $session->id, $scope, $nonce, $access, $authTime);
        }
        $this->audit->log('TOKEN_ISSUED', 'user', $user->id, $app->slug);

        return $out;
    }
}

