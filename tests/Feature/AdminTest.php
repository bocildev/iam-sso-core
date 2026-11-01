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

    public function test_sessions_treetable_view_and_revocations(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $app = Application::create(['name' => 'App Test', 'slug' => 'app-test', 'redirect_uris' => ['http://x/cb']]);

        $this->actingAs($admin)->get('/admin/sessions')->assertOk()->assertSee('Manajemen Sesi Aktif Klien');

        // Test revoke app sessions
        $this->actingAs($admin)->post('/admin/app-sessions/revoke-app', [
            'application_id' => $app->id,
        ])->assertRedirect()->assertSessionHas('ok');

        // Test revoke all client sessions
        $this->actingAs($admin)->post('/admin/app-sessions/revoke-all')
            ->assertRedirect()->assertSessionHas('ok');
    }

    public function test_user_deletion_with_protections(): void
    {
        $admin1 = User::factory()->create(['is_super_admin' => true, 'status' => 'active']);
        $admin2 = User::factory()->create(['is_super_admin' => true, 'status' => 'active']);
        $member = User::factory()->create(['is_super_admin' => false, 'status' => 'active']);

        // Protection 1: Cannot delete self
        $this->actingAs($admin1)->delete("/admin/users/{$admin1->id}")
            ->assertSessionHasErrors('status');
        $this->assertDatabaseHas('users', ['id' => $admin1->id]);

        // Delete regular member succeeds
        $this->actingAs($admin1)->delete("/admin/users/{$member->id}")
            ->assertRedirect('/admin/users')->assertSessionHas('ok');
        $this->assertDatabaseMissing('users', ['id' => $member->id]);

        // Delete admin2 succeeds because admin1 still exists
        $this->actingAs($admin1)->delete("/admin/users/{$admin2->id}")
            ->assertRedirect('/admin/users')->assertSessionHas('ok');
        $this->assertDatabaseMissing('users', ['id' => $admin2->id]);
    }

    public function test_application_and_organization_deletion(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $app = Application::create(['name' => 'To Delete', 'slug' => 'to-delete', 'redirect_uris' => ['http://del/cb']]);
        $org = \App\Models\Organization::create(['name' => 'Org Delete', 'slug' => 'org-del']);

        $this->actingAs($admin)->delete("/admin/apps/{$app->id}")
            ->assertRedirect('/admin/apps')->assertSessionHas('ok');
        $this->assertDatabaseMissing('applications', ['id' => $app->id]);

        $this->actingAs($admin)->delete("/admin/orgs/{$org->id}")
            ->assertRedirect('/admin/users')->assertSessionHas('ok');
        $this->assertDatabaseMissing('organizations', ['id' => $org->id]);
    }
}
