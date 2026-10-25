<?php

namespace Iam\Client;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use RuntimeException;

final class TokenVerifier
{
    public function __construct(
        private string $issuer,
        private string $expectedAudience,
        /** @var array{keys: array<int, array<string, mixed>>} */
        private array $jwks,
    ) {}

    public function verifyAccessToken(string $jwt): Claims
    {
        $keySet = JWK::parseKeySet($this->jwks, 'RS256');
        $decoded = json_decode(json_encode(JWT::decode($jwt, $keySet)), true);

        if (($decoded['iss'] ?? '') !== rtrim($this->issuer, '/')) {
            throw new RuntimeException('Invalid token issuer.');
        }
        $aud = $decoded['aud'] ?? '';
        $audList = is_array($aud) ? $aud : [$aud];
        if (! in_array($this->expectedAudience, $audList, true)) {
            throw new RuntimeException('Invalid token audience.');
        }
        if (($decoded['typ'] ?? 'access') !== 'access') {
            throw new RuntimeException('Token is not an access token.');
        }

        return new Claims($decoded);
    }
}
