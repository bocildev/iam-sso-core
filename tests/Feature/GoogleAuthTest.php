<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Organization::create(['name' => 'VyrnForge Ecosystem', 'slug' => 'vyrnforge']);
    }

    public function test_google_redirect_opens_sandbox_when_unconfigured(): void
    {
        config(['services.google.client_id' => null]);

        $res = $this->get('/auth/google');
        $res->assertOk();
        $res->assertSee('Simulasi Login Google');
    }

    public function test_google_callback_auto_registers_new_user_and_logs_in(): void
    {
        $res = $this->post('/auth/google/callback', [
            'mock' => 1,
            'mock_email' => 'newuser.google@gmail.com',
            'mock_name' => 'New Google User',
        ]);

        $res->assertRedirect('/');
        $this->assertAuthenticated();

        $user = User::where('email', 'newuser.google@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('New Google User', $user->name);
        $this->assertNotNull($user->google_id);

        $this->assertDatabaseHas('audit_logs', ['action' => 'GOOGLE_REGISTER']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'LOGIN']);
    }

    public function test_google_callback_get_mock_as_owner_redirects_to_admin(): void
    {
        $res = $this->get('/auth/google/callback?mock=1&mock_email=athallahrich@gmail.com&mock_name=Owner');
        $res->assertRedirect('/admin');
        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->is_super_admin);
    }

    public function test_google_callback_links_existing_user(): void
    {
        $existing = User::factory()->create([
            'email' => 'lama@example.com',
            'name' => 'Akun Lama',
            'google_id' => null,
        ]);

        $res = $this->post('/auth/google/callback', [
            'mock' => 1,
            'mock_email' => 'lama@example.com',
            'mock_name' => 'Akun Lama Google',
        ]);

        $res->assertRedirect('/');
        $this->assertAuthenticatedAs($existing);

        $existing->refresh();
        $this->assertNotNull($existing->google_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'GOOGLE_ACCOUNT_LINKED']);
    }

    public function test_google_login_preserves_client_app_redirect(): void
    {
        $app = Application::create([
            'name' => 'Mochi Finance Client',
            'slug' => 'mochi-finance',
            'client_id' => 'app_mochi_finance',
            'redirect_uris' => ['http://localhost:3001/auth/callback'],
        ]);

        $redirectRes = $this->get('/auth/google?client_id=app_mochi_finance');
        $redirectRes->assertOk();
        $redirectRes->assertSessionHas('iam_intended');
        $intended = session('iam_intended');

        $callbackRes = $this->withSession(['iam_intended' => $intended])->post('/auth/google/callback', [
            'mock' => 1,
            'mock_email' => 'client.user@gmail.com',
            'mock_name' => 'Client App User',
        ]);

        $this->assertAuthenticated();
        // Redirect harus menuju ke alur authorize aplikasi klien
        $callbackRes->assertRedirect();
        $target = $callbackRes->headers->get('Location');
        $this->assertStringContainsString('/oauth/authorize', $target);
        $this->assertStringContainsString('client_id=app_mochi_finance', $target);
    }

    public function test_google_login_rejected_for_showcase_apps(): void
    {
        $showcase = Application::create([
            'name' => 'Motion Portfolio Web',
            'slug' => 'motion-portfolio',
            'client_id' => 'APP-0001',
            'category' => 'public_no_auth',
            'redirect_uris' => ['none'],
        ]);

        $res = $this->get('/auth/google?client_id=APP-0001');
        $res->assertRedirect('/login');
        $res->assertSessionHasErrors('email');
    }

    public function test_google_login_rejected_for_internal_admin_apps_if_non_admin(): void
    {
        $adminApp = Application::create([
            'name' => 'Mochi Finance Admin',
            'slug' => 'mochi-finance-admin',
            'client_id' => 'app_mochi_finance_admin',
            'category' => 'internal_admin',
            'redirect_uris' => ['http://mochi-finance-admin.test:8888/auth/callback'],
        ]);

        $this->get('/auth/google?client_id=app_mochi_finance_admin');
        $appId = session('google_auth_app_id');
        $this->assertEquals($adminApp->id, $appId);

        $res = $this->withSession(['google_auth_app_id' => $appId])->get('/auth/google/callback?mock=1&mock_email=stranger@gmail.com&mock_name=Stranger');
        $res->assertRedirect('/login');
        $res->assertSessionHasErrors('email');
    }
}
