<?php

namespace Tests\Feature;

use App\Domain\Identity\TotpService;
use App\Domain\Oidc\SessionService;
use App\Models\Application;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class MfaPasswordResetAndSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_enroll_mfa_and_login_requires_totp_or_recovery_code(): void
    {
        $totp = app(TotpService::class);
        $admin = User::factory()->create([
            'email' => 'sec@iam.test',
            'password' => 'SuperSecretPassword!123',
            'is_super_admin' => true,
        ]);

        // 1. Prepare & Confirm MFA in Admin Security page
        $this->actingAs($admin)
            ->post('/admin/security/mfa/prepare')
            ->assertRedirect('/admin/security');

        $admin->refresh();
        $this->assertFalse($admin->hasMfaEnabled());
        $secret = $totp->decryptSecret($admin);
        $this->assertNotEmpty($secret);

        $validCode = $totp->code($secret);
        $confirmRes = $this->actingAs($admin)
            ->post('/admin/security/mfa/confirm', ['code' => $validCode]);
        $confirmRes->assertRedirect('/admin/security')->assertSessionHas('recovery_codes');
        $recoveryCodes = session('recovery_codes');
        $this->assertCount(8, $recoveryCodes);

        $admin->refresh();
        $this->assertTrue($admin->hasMfaEnabled());

        // 2. Logout and test 2-step login with TOTP code
        $this->post('/logout');
        $this->assertGuest();

        $this->post('/login', [
            'email' => 'sec@iam.test',
            'password' => 'SuperSecretPassword!123',
        ])->assertRedirect('/login/mfa');
        $this->assertGuest();

        // Invalid TOTP rejected
        $this->post('/login/mfa', ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertGuest();

        // Valid TOTP completes login
        $this->post('/login/mfa', ['code' => $totp->code($secret)])
            ->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);

        // 3. Logout and test one-time Recovery Code consumption
        $this->post('/logout');
        $this->post('/login', [
            'email' => 'sec@iam.test',
            'password' => 'SuperSecretPassword!123',
        ])->assertRedirect('/login/mfa');

        $oneRecovery = $recoveryCodes[0];
        $this->post('/login/mfa', ['code' => $oneRecovery])
            ->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
        $this->assertCount(7, $admin->fresh()->recovery_code_hashes);

        // Same recovery code cannot be reused
        $this->post('/logout');
        $this->post('/login', [
            'email' => 'sec@iam.test',
            'password' => 'SuperSecretPassword!123',
        ])->assertRedirect('/login/mfa');
        $this->post('/login/mfa', ['code' => $oneRecovery])
            ->assertSessionHasErrors('code');
    }

    public function test_password_reset_flow_updates_password_and_revokes_sso_sessions(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'reset@iam.test',
            'password' => 'OldPassword!12345',
        ]);

        // Create an active SSO session for user via login
        $this->post('/login', ['email' => 'reset@iam.test', 'password' => 'OldPassword!12345'])->assertRedirect('/admin');
        $this->post('/logout');

        $sso = \App\Models\SsoSession::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'expires_at' => now()->addHour(),
        ]);
        $this->assertTrue($sso->fresh()->isValid());

        $this->get('/forgot-password')->assertOk();
        $this->post('/forgot-password', ['email' => 'reset@iam.test'])
            ->assertSessionHas('ok');
        $this->assertDatabaseHas('audit_logs', ['action' => 'PASSWORD_RESET_REQUESTED']);

        $token = Password::createToken($user);
        $this->get("/reset-password/{$token}?email=reset@iam.test")->assertOk();

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'reset@iam.test',
            'password' => 'NewStrongPassword!999',
            'password_confirmation' => 'NewStrongPassword!999',
        ])->assertRedirect('/login');

        $this->assertFalse($sso->fresh()->isValid());
        $this->assertDatabaseHas('audit_logs', ['action' => 'PASSWORD_RESET', 'actor_id' => (string) $user->id]);
    }

    public function test_database_seeder_is_idempotent_and_seeds_initial_ecosystem(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class); // Run twice to verify idempotency

        $this->assertDatabaseHas('organizations', ['slug' => 'vyrnforge']);
        $this->assertDatabaseHas('users', ['email' => 'admin@iam-sso.local', 'is_super_admin' => true]);
        $this->assertDatabaseHas('applications', ['slug' => 'motion-portfolio', 'client_id' => 'APP-0001']);
        $this->assertDatabaseHas('applications', ['slug' => 'mochi-finance', 'client_id' => 'app_mochi_finance']);

        $motion = Application::where('slug', 'motion-portfolio')->firstOrFail();
        $this->assertDatabaseHas('roles', ['application_id' => $motion->id, 'key' => 'admin']);
        $this->assertDatabaseHas('permissions', ['application_id' => $motion->id, 'key' => 'portfolio.write']);
        $this->assertEquals(1, Organization::where('slug', 'vyrnforge')->count());
    }
}
