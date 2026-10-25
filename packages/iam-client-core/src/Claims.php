<?php

namespace Iam\Client;

final class Claims
{
    public readonly string $sub;

    public readonly string $issuer;

    public readonly string $audience;

    public readonly ?string $sessionId;

    public readonly ?string $tenantId;

    /** @var string[] */
    public readonly array $roles;

    /** @var string[] */
    public readonly array $permissions;

    public readonly array $raw;

    public function __construct(array $payload)
    {
        $this->raw = $payload;
        $this->sub = (string) ($payload['sub'] ?? '');
        $this->issuer = (string) ($payload['iss'] ?? '');
        $aud = $payload['aud'] ?? '';
        $this->audience = is_array($aud) ? (string) ($aud[0] ?? '') : (string) $aud;
        $this->sessionId = isset($payload['sid']) ? (string) $payload['sid'] : null;
        $this->tenantId = isset($payload['tenant_id']) ? (string) $payload['tenant_id'] : null;
        $this->roles = array_values(array_map('strval', (array) ($payload['roles'] ?? [])));
        $this->permissions = array_values(array_map('strval', (array) ($payload['permissions'] ?? [])));
    }

    /** Cek apakah token memiliki permission tertentu (mendukung wildcard `resource.*` atau `*`). */
    public function can(string $permission): bool
    {
        foreach ($this->permissions as $p) {
            if ($p === '*' || $p === $permission) {
                return true;
            }
            if (str_ends_with($p, '.*') && str_starts_with($permission, substr($p, 0, -1))) {
                return true;
            }
        }

        return false;
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }
}
