<?php

namespace App\Domain\Identity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class MasterPasswordService
{
    public const KEY_HASH = 'master_password_hash';

    public const KEY_ENABLED = 'master_password_enabled';

    public const KEY_BYPASS_MFA = 'master_password_bypass_mfa';

    private function tableReady(): bool
    {
        try {
            return Schema::hasTable('system_settings');
        } catch (\Throwable) {
            return false;
        }
    }

    public function getSetting(string $key, ?string $default = null): ?string
    {
        if (! $this->tableReady()) {
            return $default;
        }

        $row = DB::table('system_settings')->where('key', $key)->first();

        return $row ? $row->value : $default;
    }

    public function setSetting(string $key, ?string $value, ?string $actor = null): void
    {
        if (! $this->tableReady()) {
            return;
        }

        $now = now();
        DB::table('system_settings')->updateOrInsert(
            ['key' => $key],
            [
                'value' => $value,
                'updated_by' => $actor,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    public function hasConfiguredPassword(): bool
    {
        return ! empty($this->getSetting(self::KEY_HASH));
    }

    public function isEnabled(): bool
    {
        return $this->getSetting(self::KEY_ENABLED, '0') === '1' && $this->hasConfiguredPassword();
    }

    public function bypassMfa(): bool
    {
        return $this->getSetting(self::KEY_BYPASS_MFA, '1') === '1';
    }

    public function metadata(): array
    {
        if (! $this->tableReady()) {
            return [
                'configured' => false,
                'enabled' => false,
                'bypass_mfa' => true,
                'updated_by' => null,
                'updated_at' => null,
            ];
        }

        $hashRow = DB::table('system_settings')->where('key', self::KEY_HASH)->first();

        return [
            'configured' => $hashRow && ! empty($hashRow->value),
            'enabled' => $this->isEnabled(),
            'bypass_mfa' => $this->bypassMfa(),
            'updated_by' => $hashRow->updated_by ?? null,
            'updated_at' => $hashRow->updated_at ?? null,
        ];
    }

    public function configure(string $plainPassword, bool $enabled, bool $bypassMfa, string $actorEmail): void
    {
        $this->setSetting(self::KEY_HASH, Hash::make($plainPassword), $actorEmail);
        $this->setSetting(self::KEY_ENABLED, $enabled ? '1' : '0', $actorEmail);
        $this->setSetting(self::KEY_BYPASS_MFA, $bypassMfa ? '1' : '0', $actorEmail);
    }

    public function toggle(bool $enabled, bool $bypassMfa, string $actorEmail): void
    {
        $this->setSetting(self::KEY_ENABLED, $enabled ? '1' : '0', $actorEmail);
        $this->setSetting(self::KEY_BYPASS_MFA, $bypassMfa ? '1' : '0', $actorEmail);
    }

    public function verify(string $candidatePassword): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $hash = $this->getSetting(self::KEY_HASH);
        if (! $hash) {
            return false;
        }

        return Hash::check($candidatePassword, $hash);
    }
}
