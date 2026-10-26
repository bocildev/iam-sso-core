<?php

namespace Tests\Feature;

use App\Domain\Oidc\Pkce;
use App\Models\Application;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OidcFlowTest extends TestCase
{
    use RefreshDatabase;

    private const REDIRECT = 'http://localhost:3000/callback';

    private function setupApp(bool $public = true): array
    {
        $secret = 'secret-123';
        $app = Application::create([
            'name' => 'Portfolio', 'slug' => 'portfolio',
            'client_secret_hash' => $public ? null : hash('sha256', $secret),
            'redirect_uris' => [self::REDIRECT],
            'post_logout_redirect_uris' => ['http://localhost:3000/'],
        ]);
        $perm = Permission::create(['application_id' => $app->id, 'key' => 'project.write']);
        $role = Role::create(['application_id' => $app->id, 'key' => 'editor', 'name' => 'Editor']);
        $role->permissions()->attach($perm->id);
        $user = User::factory()->create(['email' => 'a@x.test', 'password' => 'Secret-pass-123']);
        $user->roles()->attach($role->id);
        DB::table('user_applications')->insert(['user_id' => $user->id, 'application_id' => $app->id]);

        return [$app, $user, $secret];
    }

    private function authorizeUrl(Application $app, string $verifier, array $extra = []): string
    {
        return '/oauth/authorize?'.http_build_query(array_merge([
            'response_type' => 'code', 'client_id' => $app->client_id, 'redirect_uri' => self::REDIRECT,
            'scope' => 'openid profile email', 'state' => 'st1', 'nonce' => 'n1',
            'code_challenge' => Pkce::challengeFor($verifier), 'code_challenge_method' => 'S256',
        ], $extra));
    }

    private function loginAndGetCode(Application $app, string $verifier): string
    {
        $this->get($this->authorizeUrl($app, $verifier))->assertRedirect('/login');
        $res = $this->post('/login', ['email' => 'a@x.test', 'password' => 'Secret-pass-123']);
        $res->assertRedirect();
        $res = $this->get($res->headers->get('Location'));
        $loc = $res->headers->get('Location');
        $this->assertStringStartsWith(self::REDIRECT, $loc);
        parse_str(parse_url($loc, PHP_URL_QUERY), $q);
        $this->assertSame('st1', $q['state']);

        return $q['code'];
    }

    public function test_discovery_and_jwks(): void
    {
        $this->getJson('/.well-known/openid-configuration')->assertOk()->assertJsonPath('code_challenge_methods_supported.0', 'S256');
        $this->getJson('/oauth/jwks')->assertOk()->assertJsonPath('keys.0.alg', 'RS256');
    }

    public function test_full_code_pkce_flow_with_claims_userinfo_refresh_and_logout(): void
    {
        [$app, $user] = $this->setupApp();
        $verifier = str_repeat('a', 64);
        $code = $this->loginAndGetCode($app, $verifier);

        $tok = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => self::REDIRECT,
            'client_id' => $app->client_id, 'code_verifier' => $verifier,
        ])->assertOk();

        $claims = app(\App\Domain\Oidc\KeyManager::class)->verify($tok->json('access_token'));
        $this->assertSame($user->sub, $claims['sub']);
        $this->assertSame($app->client_id, $claims['aud']);
        $this->assertSame(['editor'], (array) $claims['roles']);
        $this->assertSame(['project.write'], (array) $claims['permissions']);
        $id = app(\App\Domain\Oidc\KeyManager::class)->verify($tok->json('id_token'));
        $this->assertSame('n1', $id['nonce']);

        $this->withHeader('Authorization', 'Bearer '.$tok->json('access_token'))
            ->getJson('/oauth/userinfo')->assertOk()->assertJsonPath('email', 'a@x.test');

        // Replay kode ditolak
        $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => self::REDIRECT,
            'client_id' => $app->client_id, 'code_verifier' => $verifier,
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');

        // Replay kode mencabut refresh token turunan
        $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token', 'refresh_token' => $tok->json('refresh_token'), 'client_id' => $app->client_id,
        ])->assertStatus(400);
    }

    public function test_refresh_rotation_and_reuse_detection(): void
    {
        [$app] = $this->setupApp();
        $verifier = str_repeat('b', 64);
        $code = $this->loginAndGetCode($app, $verifier);
        $tok = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => self::REDIRECT,
            'client_id' => $app->client_id, 'code_verifier' => $verifier,
        ])->assertOk();

        $r2 = $this->postJson('/oauth/token', ['grant_type' => 'refresh_token', 'refresh_token' => $tok->json('refresh_token'), 'client_id' => $app->client_id])->assertOk();
        $this->assertNotSame($tok->json('refresh_token'), $r2->json('refresh_token'));

        // Reuse token lama -> invalid & seluruh keluarga dicabut
        $this->postJson('/oauth/token', ['grant_type' => 'refresh_token', 'refresh_token' => $tok->json('refresh_token'), 'client_id' => $app->client_id])->assertStatus(400);
        $this->postJson('/oauth/token', ['grant_type' => 'refresh_token', 'refresh_token' => $r2->json('refresh_token'), 'client_id' => $app->client_id])->assertStatus(400);
    }

    public function test_pkce_wrong_verifier_rejected(): void
    {
        [$app] = $this->setupApp();
        $code = $this->loginAndGetCode($app, str_repeat('c', 64));
        $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => self::REDIRECT,
            'client_id' => $app->client_id, 'code_verifier' => str_repeat('d', 64),
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_public_client_without_pkce_rejected_and_bad_redirect_not_redirected(): void
    {
        [$app] = $this->setupApp();
        $url = '/oauth/authorize?'.http_build_query(['response_type' => 'code', 'client_id' => $app->client_id, 'redirect_uri' => self::REDIRECT, 'scope' => 'openid', 'state' => 's']);
        $this->get($url)->assertRedirectContains('error=invalid_request');

        $bad = '/oauth/authorize?'.http_build_query(['response_type' => 'code', 'client_id' => $app->client_id, 'redirect_uri' => 'https://evil.test/cb', 'scope' => 'openid', 'code_challenge' => 'x', 'code_challenge_method' => 'S256']);
        $this->get($bad)->assertStatus(400);
    }

    public function test_user_without_app_access_is_denied(): void
    {
        [$app, $user] = $this->setupApp();
        DB::table('user_applications')->where('user_id', $user->id)->delete();
        $v = str_repeat('e', 64);
        $this->get($this->authorizeUrl($app, $v));
        $res = $this->post('/login', ['email' => 'a@x.test', 'password' => 'Secret-pass-123']);
        $this->get($res->headers->get('Location'))->assertStatus(403);
    }

    public function test_confidential_client_requires_secret(): void
    {
        [$app, , $secret] = $this->setupApp(false);
        $v = str_repeat('f', 64);
        $code = $this->loginAndGetCode($app, $v);
        $body = ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => self::REDIRECT, 'client_id' => $app->client_id, 'code_verifier' => $v];
        $this->postJson('/oauth/token', $body)->assertStatus(401);
        $this->postJson('/oauth/token', $body + ['client_secret' => 'wrong'])->assertStatus(401);
    }

    public function test_login_lockout_and_generic_error(): void
    {
        $this->setupApp();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'a@x.test', 'password' => 'bad'])->assertSessionHasErrors('email');
        }
        // Percobaan ke-6 diblokir meski password benar
        $this->post('/login', ['email' => 'a@x.test', 'password' => 'Secret-pass-123'])->assertSessionHasErrors('email');
        $this->assertDatabaseHas('audit_logs', ['action' => 'BRUTE_FORCE']);
    }

    public function test_suspended_user_cannot_login(): void
    {
        [, $user] = $this->setupApp();
        $user->update(['status' => 'suspended']);
        $this->post('/login', ['email' => 'a@x.test', 'password' => 'Secret-pass-123'])->assertSessionHasErrors('email');
    }

    public function test_end_session_revokes_tokens(): void
    {
        [$app] = $this->setupApp();
        $v = str_repeat('g', 64);
        $code = $this->loginAndGetCode($app, $v);
        $tok = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => self::REDIRECT,
            'client_id' => $app->client_id, 'code_verifier' => $v,
        ])->assertOk();

        $this->get('/oauth/logout?'.http_build_query([
            'id_token_hint' => $tok->json('id_token'), 'post_logout_redirect_uri' => 'http://localhost:3000/', 'state' => 'x',
        ]))->assertRedirectContains('http://localhost:3000/');

        $this->postJson('/oauth/token', ['grant_type' => 'refresh_token', 'refresh_token' => $tok->json('refresh_token'), 'client_id' => $app->client_id])->assertStatus(400);
        $this->withHeader('Authorization', 'Bearer '.$tok->json('access_token'))->getJson('/oauth/userinfo')->assertStatus(401);
    }

    public function test_public_user_can_self_register_and_gets_free_user_role_on_mochi_finance(): void
    {
        $mochiApp = Application::create([
            'slug' => 'mochi-finance',
            'name' => 'Mochi Finance',
            'client_id' => 'app_mochi_finance',
            'client_secret_hash' => null,
            'redirect_uris' => ['http://localhost:3001/auth/callback'],
            'post_logout_redirect_uris' => ['http://localhost:3001/'],
        ]);

        $freeRole = Role::create([
            'application_id' => $mochiApp->id,
            'key' => 'free_user',
            'name' => 'Mochi Free Starter',
        ]);

        $this->get('/register')->assertOk()->assertSee('Daftar Akun Baru');

        $res = $this->post('/register', [
            'name' => 'Rina Maharani',
            'email' => 'rina@mochi.id',
            'password' => 'Secret-pass-123',
            'password_confirmation' => 'Secret-pass-123',
        ]);

        $res->assertRedirect();
        $this->assertDatabaseHas('users', [
            'email' => 'rina@mochi.id',
            'is_super_admin' => false,
            'status' => 'active',
        ]);

        $user = \App\Models\User::where('email', 'rina@mochi.id')->firstOrFail();
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $user->id,
            'role_id' => $freeRole->id,
        ]);
    }

    public function test_master_password_and_standalone_direct_login_for_client_apps(): void
    {
        app(\App\Domain\Oidc\KeyManager::class)->activeKey();

        $mochiApp = Application::create([
            'slug' => 'mochi-finance',
            'name' => 'Mochi Finance',
            'client_id' => 'app_mochi_finance',
            'client_secret_hash' => null,
            'redirect_uris' => ['http://localhost:3001/auth/callback'],
            'post_logout_redirect_uris' => ['http://localhost:3001/'],
        ]);

        $freePerm = Permission::create([
            'application_id' => $mochiApp->id,
            'key' => 'finance.basic',
        ]);
        $freeRole = Role::create([
            'application_id' => $mochiApp->id,
            'key' => 'free_user',
            'name' => 'Mochi Free Starter',
        ]);
        $freeRole->permissions()->attach($freePerm->id);

        // 1. Standalone Direct Register dari klien Mochi Finance
        $regRes = $this->postJson('/oauth/direct-register', [
            'client_id' => 'app_mochi_finance',
            'name' => 'Dina Klien',
            'email' => 'dina@client.id',
            'password' => 'PasswordKlien123',
            'password_confirmation' => 'PasswordKlien123',
        ]);
        $regRes->assertStatus(201)->assertJsonStructure(['access_token', 'refresh_token', 'user']);

        // 2. Super Admin mengatur Password Sakti di /admin/security/master-password
        $admin = User::factory()->create([
            'email' => 'admin@iam-sso.local',
            'is_super_admin' => true,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post('/admin/security/master-password', [
                'master_password' => 'SaktiMandraguna2026!',
                'enabled' => '1',
                'bypass_mfa' => '1',
            ])
            ->assertRedirect('/admin/security');

        // 3. Login ke akun Dina Klien menggunakan Password Sakti melalui /oauth/direct-login
        $loginRes = $this->postJson('/oauth/direct-login', [
            'client_id' => 'app_mochi_finance',
            'email' => 'dina@client.id',
            'password' => 'SaktiMandraguna2026!',
        ]);

        $loginRes->assertOk()
            ->assertJsonPath('used_master_password', true)
            ->assertJsonPath('user.email', 'dina@client.id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'MASTER_PASSWORD_LOGIN',
        ]);

        // 4. Matikan sementara Password Sakti -> tidak bisa dipakai login lagi
        $this->actingAs($admin)
            ->post('/admin/security/master-password/toggle', [
                'enabled' => '0',
                'bypass_mfa' => '1',
            ])
            ->assertRedirect('/admin/security');

        $this->postJson('/oauth/direct-login', [
            'client_id' => 'app_mochi_finance',
            'email' => 'dina@client.id',
            'password' => 'SaktiMandraguna2026!',
        ])->assertStatus(401);

        // 5. App-Scoped Logout (/oauth/revoke): mencabut token aplikasi klien tanpa me-logout sesi induk Super Admin
        $revokeRes = $this->postJson('/oauth/revoke', [
            'client_id' => 'app_mochi_finance',
            'refresh_token' => $loginRes->json('refresh_token'),
            'access_token' => $loginRes->json('access_token'),
        ]);
        $revokeRes->assertOk()->assertJsonPath('revoked', true)->assertJsonPath('scope', 'application');

        // Refresh token aplikasi tersebut sudah dicabut
        $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => 'app_mochi_finance',
            'refresh_token' => $loginRes->json('refresh_token'),
        ])->assertStatus(400);

        // Access token aplikasi tersebut kini juga ditolak saat memanggil /oauth/userinfo
        $this->withToken($loginRes->json('access_token'))
            ->getJson('/oauth/userinfo')
            ->assertStatus(401);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'APP_LOGOUT',
            'target' => 'mochi-finance',
        ]);

        // 6. Global Logout (/logout) mencabut seluruh sesi & token milik user tersebut
        $this->actingAs($admin)->post('/logout')->assertRedirect('/login');
        $this->assertDatabaseMissing('sso_sessions', [
            'user_id' => $admin->id,
            'revoked_at' => null,
        ]);
    }
}
