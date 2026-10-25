<?php

namespace App\Domain\Oidc;

use App\Domain\Access\AccessResolver;
use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Str;

class TokenIssuer
{
    public function __construct(private KeyManager $keys, private AccessResolver $access) {}

    public function accessToken(User $user, Application $app, string $sid, string $scope): string
    {
        $now = time();
        $claims = $this->access->claimsFor($user, $app);

        return $this->keys->sign([
            'iss' => config('iam.issuer'),
            'sub' => $user->sub,
            'aud' => $app->client_id,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + config('iam.access_token_ttl'),
            'jti' => (string) Str::uuid(),
            'sid' => $sid,
            'scope' => $scope,
            'typ' => 'access',
            'tenant_id' => $claims['tenant_id'],
            'roles' => $claims['roles'],
            'permissions' => $claims['permissions'],
        ]);
    }

    public function idToken(User $user, Application $app, string $sid, string $scope, ?string $nonce, string $accessToken, int $authTime): string
    {
        $now = time();
        $scopes = explode(' ', $scope);
        $claims = [
            'iss' => config('iam.issuer'),
            'sub' => $user->sub,
            'aud' => $app->client_id,
            'iat' => $now,
            'exp' => $now + config('iam.id_token_ttl'),
            'auth_time' => $authTime,
            'sid' => $sid,
            'at_hash' => rtrim(strtr(base64_encode(substr(hash('sha256', $accessToken, true), 0, 16)), '+/', '-_'), '='),
        ];
        if ($nonce) {
            $claims['nonce'] = $nonce;
        }
        if (in_array('profile', $scopes, true)) {
            $claims['name'] = $user->name;
        }
        if (in_array('email', $scopes, true)) {
            $claims['email'] = $user->email;
            $claims['email_verified'] = $user->email_verified_at !== null;
        }

        return $this->keys->sign($claims);
    }

    public function logoutToken(Application $app, string $sub, string $sid): string
    {
        return $this->keys->sign([
            'iss' => config('iam.issuer'),
            'aud' => $app->client_id,
            'iat' => time(),
            'jti' => (string) Str::uuid(),
            'sub' => $sub,
            'sid' => $sid,
            'events' => ['http://schemas.openid.net/event/backchannel-logout' => new \stdClass],
        ]);
    }
}
