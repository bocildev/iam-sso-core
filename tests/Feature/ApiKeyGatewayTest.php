<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiKeyGatewayTest extends TestCase
{
    use RefreshDatabase;

    private function key(array $scopes = ['iam:users.read'], string $status = 'active', string $appStatus = 'active'): array
    {
        $app = Application::create(['name' => 'P', 'slug' => 'p', 'redirect_uris' => ['http://x/cb'], 'status' => $appStatus]);
        $plain = 'iam_live_'.bin2hex(random_bytes(16));
        $k = ApiKey::create(['application_id' => $app->id, 'name' => 't', 'prefix' => substr($plain, 0, 13),
            'key_hash' => hash('sha256', $plain), 'scopes' => $scopes, 'status' => $status]);

        return [$app, $plain, $k];
    }

    public function test_requires_key_and_rejects_invalid(): void
    {
        $this->getJson('/api/v1/me')->assertStatus(401);
        $this->withHeader('Authorization', 'Bearer iam_live_wrong')->getJson('/api/v1/me')->assertStatus(401);
        $this->assertDatabaseHas('audit_logs', ['action' => 'API_KEY_REJECTED']);
    }

    public function test_query_string_token_is_not_accepted(): void
    {
        [, $plain] = $this->key();
        $this->getJson('/api/v1/me?api_token='.$plain)->assertStatus(401);
    }

    public function test_valid_key_works_via_bearer_and_x_api_key(): void
    {
        [, $plain] = $this->key();
        $this->withHeader('Authorization', "Bearer $plain")->getJson('/api/v1/me')->assertOk()->assertJsonPath('application.slug', 'p');
        $this->withHeader('X-API-Key', $plain)->getJson('/api/v1/me')->assertOk();
    }

    public function test_revoked_key_and_inactive_app_rejected(): void
    {
        [, $p1] = $this->key(status: 'revoked');
        $this->withHeader('Authorization', "Bearer $p1")->getJson('/api/v1/me')->assertStatus(401);
        DB::table('applications')->delete();
        DB::table('api_keys')->delete();
        [, $p2] = $this->key(appStatus: 'suspended');
        $this->withHeader('Authorization', "Bearer $p2")->getJson('/api/v1/me')->assertStatus(401);
    }

    public function test_scope_enforced_and_wildcards(): void
    {
        [$app, $plain] = $this->key(['portfolio:read']);
        $user = User::factory()->create();
        DB::table('user_applications')->insert(['user_id' => $user->id, 'application_id' => $app->id]);
        $this->withHeader('Authorization', "Bearer $plain")->getJson("/api/v1/users/{$user->sub}/access")->assertStatus(403);
        $this->assertDatabaseHas('audit_logs', ['action' => 'API_KEY_SCOPE_DENIED']);
    }

    public function test_user_access_is_scoped_to_key_application(): void
    {
        [$app, $plain] = $this->key(['iam:*']);
        $user = User::factory()->create();
        $this->withHeader('Authorization', "Bearer $plain")->getJson("/api/v1/users/{$user->sub}/access")->assertStatus(404);
        DB::table('user_applications')->insert(['user_id' => $user->id, 'application_id' => $app->id]);
        $this->withHeader('Authorization', "Bearer $plain")->getJson("/api/v1/users/{$user->sub}/access")
            ->assertOk()->assertJsonPath('sub', $user->sub)->assertJsonStructure(['roles', 'permissions', 'tenant_id']);
    }

    public function test_rate_limit_per_key(): void
    {
        config(['iam.api_key_rate_per_minute' => 3]);
        [, $plain] = $this->key();
        for ($i = 0; $i < 3; $i++) {
            $this->withHeader('Authorization', "Bearer $plain")->getJson('/api/v1/me')->assertOk();
        }
        $this->withHeader('Authorization', "Bearer $plain")->getJson('/api/v1/me')->assertStatus(429);
    }
}

