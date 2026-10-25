<?php

namespace App\Domain\Oidc;

final class Pkce
{
    public static function challengeFor(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /** Hanya metode S256 yang didukung (plain ditolak). */
    public static function verify(string $verifier, string $challenge): bool
    {
        if (! preg_match('/^[A-Za-z0-9\-._~]{43,128}$/', $verifier)) {
            return false;
        }

        return hash_equals($challenge, self::challengeFor($verifier));
    }
}
