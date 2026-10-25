<?php

namespace Tests\Feature;

use App\Domain\Oidc\KeyManager;
use App\Domain\Oidc\TokenIssuer;
use App\Models\Application;
use App\Models\OutboxEvent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Iam\Client\TokenVerifier;
use Iam\Client\WebhookVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OutboxAndClientCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_suspend_and_role_change_dispatches_signed_hmac_webhooks(): void
    {
        $captured = [];
        Http::fake(function ($request) use (&$captured) {
            $captured[] = $request;

            return Http::response(['ok' => true], 200);
        });

        $admin = User::factory()->create(['is_super_admin' => true]);
        $user = User::factory()->create(['email' => 'u@x.test']);
        $secret = 'whsec_test_secret_123';
        $app = Application::create([
            'name' => 'Motion',
            'slug' => 'motion-portfolio',
            'redirect_uris' => ['http://localhost:3000/cb'],
            'webhook_url' => 'http://localhost:3000/api/iam/webhook',
            'webhook_secret_encrypted' => Crypt::encryptString($secret),
        ]);
        $role = Role::create(['application_id' => $app->id, 'key' => 'editor', 'name' => 'Editor']);
        DB::table('user_applications')->insert(['user_id' => $user->id, 'application_id' => $app->id]);

        $this->actingAs($admin)->post("/admin/users/{$user->id}", [
            'name' => 'Updated Name',
            'status' => 'suspended',
            'apps' => [$app->id],
            'roles' => [$role->id],
        ])->assertRedirect();

        $this->assertGreaterThanOrEqual(2, count($captured));
        $events = OutboxEvent::pluck('event_type')->all();
        $this->assertContains('user.disabled', $events);
        $this->assertContains('role.changed', $events);

        // Verifikasi tanda tangan HMAC menggunakan SDK klien
        $first = $captured[0];
        $this->assertTrue(WebhookVerifier::verify(
            $first->body(),
            $first->header('X-IAM-Timestamp')[0],
            $first->header('X-IAM-Signature')[0],
            $secret
        ));
        $this->assertFalse(WebhookVerifier::verify(
            $first->body(),
            $first->header('X-IAM-Timestamp')[0],
            'sha256=invalid',
            $secret
        ));
    }

    public function test_client_sdk_verifies_jwt_locally_via_jwks_and_checks_permissions(): void
    {
        $app = Application::create([
            'name' => 'Portfolio',
            'slug' => 'portfolio',
            'redirect_uris' => ['http://localhost:3000/cb'],
        ]);
        $p1 = Permission::create(['application_id' => $app->id, 'key' => 'portfolio.write']);
        $p2 = Permission::create(['application_id' => $app->id, 'key' => 'project.*']);
        $role = Role::create(['application_id' => $app->id, 'key' => 'owner', 'name' => 'Owner']);
        $role->permissions()->attach([$p1->id, $p2->id]);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        DB::table('user_applications')->insert(['user_id' => $user->id, 'application_id' => $app->id]);

        $jwt = app(TokenIssuer::class)->accessToken($user, $app, 'sid-1', 'openid profile');
        $jwks = app(KeyManager::class)->jwks();

        $verifier = new TokenVerifier(config('iam.issuer'), $app->client_id, $jwks);
        $claims = $verifier->verifyAccessToken($jwt);

        $this->assertSame($user->sub, $claims->sub);
        $this->assertTrue($claims->hasRole('owner'));
        $this->assertTrue($claims->can('portfolio.write'));
        $this->assertTrue($claims->can('project.delete'));
        $this->assertFalse($claims->can('billing.manage'));
    }

    public function test_cors_and_security_headers_applied(): void
    {
        Application::create([
            'name' => 'Portfolio',
            'slug' => 'portfolio',
            'redirect_uris' => ['http://porto.test:3000/cb'],
        ]);

        $this->withHeader('Origin', 'http://porto.test:3000')
            ->postJson('/oauth/token', [])
            ->assertHeader('Access-Control-Allow-Origin', 'http://porto.test:3000')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');

        $res = $this->withHeader('Origin', 'https://evil.example.com')->postJson('/oauth/token', []);
        $this->assertNull($res->headers->get('Access-Control-Allow-Origin'));
    }
}
