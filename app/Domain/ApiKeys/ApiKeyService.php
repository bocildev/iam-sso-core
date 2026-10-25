<?php

namespace App\Domain\ApiKeys;

use App\Models\ApiKey;

class ApiKeyService
{
    /** Cari key aktif dari plaintext. Hanya hash yang dibandingkan. */
    public function authenticate(string $plain): ?ApiKey
    {
        if (! str_starts_with($plain, 'iam_live_') || strlen($plain) > 128) {
            return null;
        }
        $key = ApiKey::with('application')->where('key_hash', hash('sha256', $plain))->first();
        if (! $key || $key->status !== 'active') {
            return null;
        }
        if ($key->expires_at && $key->expires_at->isPast()) {
            return null;
        }
        if (! $key->application || ! $key->application->isActive()) {
            return null;
        }

        return $key;
    }

    /** Scope: `*`, persis, atau wildcard resource (`portfolio:*`). */
    public function allows(ApiKey $key, string $required): bool
    {
        foreach ($key->scopes ?? [] as $s) {
            if ($s === '*' || $s === $required) {
                return true;
            }
            if (str_ends_with($s, ':*') && str_starts_with($required, substr($s, 0, -1))) {
                return true;
            }
        }

        return false;
    }
}
