<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebuggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_debugger(): void
    {
        $response = $this->get('/admin/debugger');
        $response->assertRedirect('/login');
    }

    public function test_non_super_admin_cannot_access_debugger(): void
    {
        $user = User::factory()->create([
            'is_super_admin' => false,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/admin/debugger');
        $response->assertStatus(403);
    }

    public function test_super_admin_can_view_debugger_diagnostics(): void
    {
        $admin = User::factory()->create([
            'email' => 'athallahrich@gmail.com',
            'is_super_admin' => true,
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->get('/admin/debugger');
        $response->assertStatus(200);
        $response->assertSee('Debugger Server');
        $response->assertSee('Hak Akses Folder Writable');
        $response->assertSee('Aksi Cepat Server');
    }

    public function test_super_admin_can_run_preset_migrate_status(): void
    {
        $admin = User::factory()->create([
            'email' => 'athallahrich@gmail.com',
            'is_super_admin' => true,
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->post('/admin/debugger/run', [
            'action' => 'migrate:status',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('debugger_ran', true);
        $response->assertSessionHas('debugger_exit_code', 0);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'DEBUGGER_COMMAND_EXECUTED',
            'target' => 'migrate:status',
            'actor_id' => (string) $admin->id,
            'result' => 'success',
        ]);
    }

    public function test_super_admin_can_run_preset_optimize_clear(): void
    {
        $admin = User::factory()->create([
            'email' => 'athallahrich@gmail.com',
            'is_super_admin' => true,
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->post('/admin/debugger/run', [
            'action' => 'optimize:clear',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('debugger_ran', true);
        $response->assertSessionHas('debugger_exit_code', 0);
    }

    public function test_disallowed_custom_command_is_blocked(): void
    {
        $admin = User::factory()->create([
            'email' => 'athallahrich@gmail.com',
            'is_super_admin' => true,
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->post('/admin/debugger/run', [
            'action' => 'custom',
            'custom_command' => 'eval echo "hacked"',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('custom_command');
    }

    public function test_hosting_production_seeder_can_be_executed_and_creates_only_owner(): void
    {
        $this->seed(\Database\Seeders\HostingProductionSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'athallahrich@gmail.com',
            'is_super_admin' => true,
        ]);

        // Pastikan tidak ada akun dummy
        $this->assertDatabaseMissing('users', ['email' => 'admin@iam-sso.local']);
        $this->assertDatabaseMissing('users', ['email' => 'nadia.putri@gmail.com']);
        $this->assertDatabaseMissing('users', ['email' => 'budi.santoso@gmail.com']);

        // Pastikan 4 aplikasi ekosistem terdaftar
        $this->assertDatabaseHas('applications', ['slug' => 'motion-portfolio', 'client_id' => 'APP-0001']);
        $this->assertDatabaseHas('applications', ['slug' => 'portfolio-admin', 'client_id' => 'app_portfolio_admin']);
        $this->assertDatabaseHas('applications', ['slug' => 'mochi-finance', 'client_id' => 'app_mochi_finance']);
        $this->assertDatabaseHas('applications', ['slug' => 'mochi-finance-admin', 'client_id' => 'app_mochi_finance_admin']);
    }
}
