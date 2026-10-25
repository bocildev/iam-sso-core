<?php

namespace App\Domain\Identity;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;

class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    public function decryptSecret(User $user): ?string
    {
        if (empty($user->totp_secret_encrypted)) {
            return null;
        }
        try {
            return Crypt::decryptString($user->totp_secret_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function provisioningUri(User $user, string $base32Secret): string
    {
        $issuer = rawurlencode((string) config('app.name', 'IAM-SSO-CORE'));
        $label = rawurlencode( config('app.name', 'IAM-SSO-CORE').':'.$user->email);

        return "otpauth://totp/{$label}?secret={$base32Secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    }

    public function code(string $base32Secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $counter = intdiv($timestamp, 30);
        $binarySecret = $this->base32Decode($base32Secret);
        $counterBytes = pack('N*', 0).pack('N*', $counter);
        $hash = hash_hmac('sha1', $counterBytes, $binarySecret, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % 1_000_000;

        return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }

    public function verify(string $base32Secret, string $code, int $window = 1, ?int $timestamp = null): bool
    {
        $clean = preg_replace('/\s+/', '', $code) ?? '';
        if (! preg_match('/^\d{6}$/', $clean)) {
            return false;
        }
        $timestamp ??= time();
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals($this->code($base32Secret, $timestamp + ($i * 30)), $clean)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{plain: list<string>, hashes: list<string>}
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $plain = [];
        $hashes = [];
        for ($i = 0; $i < $count; $i++) {
            $raw = strtoupper(bin2hex(random_bytes(4)));
            $formatted = substr($raw, 0, 4).'-'.substr($raw, 4, 4);
            $plain[] = $formatted;
            $hashes[] = $this->hashRecoveryCode($formatted);
        }

        return ['plain' => $plain, 'hashes' => $hashes];
    }

    public function consumeRecoveryCode(User $user, string $input): bool
    {
        $hashes = $user->recovery_code_hashes ?? [];
        if (empty($hashes)) {
            return false;
        }
        $candidate = $this->hashRecoveryCode($input);
        foreach ($hashes as $idx => $storedHash) {
            if (hash_equals((string) $storedHash, $candidate)) {
                unset($hashes[$idx]);
                $user->forceFill(['recovery_code_hashes' => array_values($hashes)])->save();

                return true;
            }
        }

        return false;
    }

    private function hashRecoveryCode(string $code): string
    {
        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');

        return hash('sha256', 'iam-recovery:'.$normalized);
    }

    private function base32Encode(string $bytes): string
    {
        $bits = '';
        for ($i = 0, $len = strlen($bytes); $i < $len; $i++) {
            $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        for ($i = 0, $len = strlen($bits); $i < $len; $i += 5) {
            $chunk = str_pad(substr($bits, $i, 5), 5, '0', STR_PAD_RIGHT);
            $out .= self::ALPHABET[bindec($chunk)];
        }

        return $out;
    }

    private function base32Decode(string $base32): string
    {
        $clean = strtoupper(preg_replace('/[^A-Z2-7]/', '', $base32) ?? '');
        $bits = '';
        for ($i = 0, $len = strlen($clean); $i < $len; $i++) {
            $pos = strpos(self::ALPHABET, $clean[$i]);
            if ($pos !== false) {
                $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
            }
        }
        $out = '';
        for ($i = 0, $len = strlen($bits); $i + 8 <= $len; $i += 8) {
            $out .= chr(bindec(substr($bits, $i, 8)));
        }

        return $out;
    }
}
