<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_redirected_and_non_admin_forbidden(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_admin_pages_render_and_app_creation_shows_secret_once(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($admin);
        foreach (['/admin', '/admin/apps', '/admin/users', '/admin/audit', '/admin/security', '/admin/glossary'] as $u) {
            $this->get($u)->assertOk();
        }
        $this->get('/admin/glossary')->assertSee('Kamus Istilah')->assertSee('Redirect URI');
        $this->get('/admin')->assertSee('Apa ini?');
        $res = $this->post('/admin/apps', [
            'name' => 'Mochi',
            'slug' => 'mochi',
            'redirect_uris' => "http://localhost:9000/cb",
            'post_logout_redirect_uris' => "http://localhost:9000/",
            'allowed_origins' => "http://localhost:9000",
            'confidential' => 1,
        ]);
        $app = Application::where('slug', 'mochi')->firstOrFail();
        $this->assertEquals(['http://localhost:9000'], $app->allowed_origins);
        $this->assertEquals(['http://localhost:9000/'], $app->post_logout_redirect_uris);
        $res->assertRedirect("/admin/apps/{$app->id}")->assertSessionHas('secret');
        $this->get("/admin/apps/{$app->id}")->assertOk()->assertSee('Cara Menyambungkan ke Kodingan Aplikasi Anda');
        $this->assertDatabaseHas('audit_logs', ['action' => 'APP_CREATED']);
    }

    public function test_cannot_demote_last_super_admin(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($admin)->post("/admin/users/{$admin->id}", ['name' => 'X', 'status' => 'suspended'])
            ->assertSessionHasErrors('status');
        $this->assertTrue($admin->fresh()->isActive());
    }

    public function test_role_and_permission_save(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $app = Application::create(['name' => 'P', 'slug' => 'p', 'redirect_uris' => ['http://x/cb']]);
        $this->actingAs($admin)->post("/admin/apps/{$app->id}/roles", ['key' => 'editor', 'name' => 'Editor', 'permissions' => 'project.read, project.write'])->assertRedirect();
        $this->assertDatabaseHas('permissions', ['application_id' => $app->id, 'key' => 'project.write']);
    }
}
