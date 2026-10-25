<?php

namespace App\Domain\Oidc;

use App\Models\SigningKey;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class KeyManager
{
    public function activeKey(): SigningKey
    {
        return SigningKey::where('active', true)->latest('id')->first() ?? $this->generate();
    }

    public function generate(): SigningKey
    {
        $opts = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA] + $this->opensslConf();
        $res = openssl_pkey_new($opts);
        if ($res === false) {
            throw new \RuntimeException('Gagal membuat RSA key: '.openssl_error_string().' (set IAM_OPENSSL_CONF ke openssl.cnf)');
        }
        openssl_pkey_export($res, $private, null, $this->opensslConf());
        $public = openssl_pkey_get_details($res)['key'];

        return SigningKey::create([
            'kid' => Str::lower(Str::random(16)),
            'public_pem' => $public,
            'private_pem_encrypted' => Crypt::encryptString($private),
            'active' => true,
        ]);
    }

    /** Windows/Laragon sering butuh path openssl.cnf eksplisit. */
    private function opensslConf(): array
    {
        $cnf = env('IAM_OPENSSL_CONF') ?: dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';

        return PHP_OS_FAMILY === 'Windows' && is_file($cnf) ? ['config' => $cnf] : [];
    }

    /** Key lama tetap di JWKS (masa tenggang) sampai dihapus manual. */
    public function rotate(): SigningKey
    {
        SigningKey::where('active', true)->update(['active' => false]);

        return $this->generate();
    }

    public function sign(array $claims): string
    {
        $key = $this->activeKey();

        return JWT::encode($claims, Crypt::decryptString($key->private_pem_encrypted), 'RS256', $key->kid);
    }

    /** @throws \Throwable bila tidak valid */
    public function verify(string $jwt): array
    {
        $keys = [];
        foreach (SigningKey::all() as $k) {
            $keys[$k->kid] = new Key($k->public_pem, 'RS256');
        }

        return (array) JWT::decode($jwt, $keys);
    }

    public function jwks(): array
    {
        $this->activeKey(); // pastikan minimal satu key ada
        $out = [];
        foreach (SigningKey::orderBy('id')->get() as $k) {
            $d = openssl_pkey_get_details(openssl_pkey_get_public($k->public_pem));
            $out[] = [
                'kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => $k->kid,
                'n' => rtrim(strtr(base64_encode($d['rsa']['n']), '+/', '-_'), '='),
                'e' => rtrim(strtr(base64_encode($d['rsa']['e']), '+/', '-_'), '='),
            ];
        }

        return ['keys' => $out];
    }
}


