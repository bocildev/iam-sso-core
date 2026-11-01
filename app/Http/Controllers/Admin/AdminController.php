<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\MasterPasswordService;
use App\Domain\Identity\TotpService;
use App\Domain\Oidc\KeyManager;
use App\Domain\Oidc\SessionService;
use App\Domain\Tenancy\OutboxService;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Application;
use App\Models\MediaAsset;
use App\Models\Organization;
use App\Models\OutboxEvent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SigningKey;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AdminController extends Controller
{
    public function __construct(
        private AuditLogger $audit,
        private SessionService $sessions,
        private OutboxService $outbox,
        private TotpService $totp,
        private MasterPasswordService $masterPassword,
    ) {}

    public function dashboard()
    {
        $clientAppSessions = DB::table('oauth_refresh_tokens as ort')
            ->join('applications as a', 'a.id', '=', 'ort.application_id')
            ->join('users as u', 'u.id', '=', 'ort.user_id')
            ->leftJoin('sso_sessions as s', 's.id', '=', 'ort.sso_session_id')
            ->whereNull('ort.revoked_at')
            ->whereNull('ort.used_at')
            ->where('ort.expires_at', '>', now())
            ->select(
                'ort.token_hash',
                'ort.sso_session_id',
                'ort.application_id',
                'ort.created_at as issued_at',
                'ort.expires_at',
                'a.name as app_name',
                'a.slug as app_slug',
                'a.client_id',
                'u.name as user_name',
                'u.email as user_email',
                'u.sub as user_sub',
                's.ip'
            )
            ->orderByDesc('ort.created_at')
            ->limit(15)
            ->get();

        return view('admin.dashboard', [
            'apps' => Application::count(),
            'users' => User::count(),
            'orgs' => Organization::count(),
            'sessions' => DB::table('sso_sessions')->whereNull('revoked_at')->where('expires_at', '>', now())->count(),
            'clientAppSessions' => $clientAppSessions,
            'failed' => DB::table('audit_logs')->where('action', 'FAILED_LOGIN')->where('created_at', '>', now()->subDay())->count(),
            'outboxPending' => OutboxEvent::where('status', 'pending')->count(),
            'recentLogs' => DB::table('audit_logs')->orderByDesc('id')->limit(6)->get(),
            'activeKey' => SigningKey::where('active', true)->latest('id')->first(),
        ]);
    }

    public function revokeClientAppSession(Request $r)
    {
        $data = $r->validate([
            'sso_session_id' => ['required', 'string'],
            'application_id' => ['required', 'integer'],
        ]);

        $app = Application::find($data['application_id']);
        DB::table('oauth_refresh_tokens')
            ->where('sso_session_id', $data['sso_session_id'])
            ->where('application_id', $data['application_id'])
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $this->audit->log('APP_SESSION_REVOKED_BY_ADMIN', 'user', $r->user()->id, $app?->slug, 'success', 'warning', [
            'sso_session_id' => $data['sso_session_id'],
        ]);

        return back()->with('ok', 'Sesi aplikasi '.($app?->name ?? 'Client App').' berhasil dicabut.');
    }

    // ---------- Applications ----------
    public function apps()
    {
        return view('admin.apps', ['apps' => Application::orderBy('name')->get()]);
    }

    public function appStore(Request $r)
    {
        $category = $r->input('category', 'public_auth');
        $d = $r->validate([
            'name' => 'required|string|max:100',
            'slug' => 'required|alpha_dash|max:50|unique:applications,slug',
            'category' => 'nullable|in:public_no_auth,public_auth,internal_admin',
            'redirect_uris' => $category === 'public_no_auth' ? 'nullable|string' : 'required|string',
            'post_logout_redirect_uris' => 'nullable|string',
            'allowed_origins' => 'nullable|string',
            'allow_google_login' => 'nullable|boolean',
            'confidential' => 'nullable|boolean',
        ]);
        $category = $d['category'] ?? 'public_auth';

        $redirects = $this->lines($d['redirect_uris'] ?? '');
        if (empty($redirects) && $category === 'public_no_auth') {
            $redirects = ['none'];
        }

        $secret = null;
        $webhookSecret = 'whsec_'.bin2hex(random_bytes(16));
        $app = new Application([
            'name' => $d['name'],
            'slug' => $d['slug'],
            'category' => $category,
            'allow_google_login' => $category === 'public_auth' ? $r->boolean('allow_google_login', true) : false,
            'redirect_uris' => $redirects,
            'post_logout_redirect_uris' => $this->lines($d['post_logout_redirect_uris'] ?? ''),
            'allowed_origins' => $this->lines($d['allowed_origins'] ?? ''),
            'webhook_secret_encrypted' => Crypt::encryptString($webhookSecret),
        ]);
        if ($r->boolean('confidential')) {
            $secret = Str::random(48);
            $app->client_secret_hash = hash('sha256', $secret);
        }
        $app->save();
        $this->audit->log('APP_CREATED', 'user', $r->user()->id, $app->slug);

        return redirect("/admin/apps/{$app->id}")->with('secret', $secret)->with('webhook_secret', $webhookSecret);
    }

    public function appShow(Application $app)
    {
        $app->load('roles.permissions', 'permissions');

        return view('admin.app', [
            'app' => $app,
            'keys' => ApiKey::where('application_id', $app->id)->latest()->get(),
            'outbox' => OutboxEvent::where('application_id', $app->id)->latest()->limit(15)->get(),
        ]);
    }

    public function appUpdate(Request $r, Application $app)
    {
        $category = $r->input('category', $app->category ?? 'public_auth');
        $d = $r->validate([
            'name' => 'required|string|max:100',
            'category' => 'nullable|in:public_no_auth,public_auth,internal_admin',
            'redirect_uris' => $category === 'public_no_auth' ? 'nullable|string' : 'required|string',
            'post_logout_redirect_uris' => 'nullable|string',
            'backchannel_logout_uri' => 'nullable|url',
            'webhook_url' => 'nullable|url',
            'allowed_origins' => 'nullable|string',
            'allow_google_login' => 'nullable|boolean',
            'status' => 'required|in:active,suspended',
            'auth_mode' => 'required|in:legacy,oidc,both',
        ]);
        $category = $d['category'] ?? ($app->category ?? 'public_auth');

        $redirects = $this->lines($d['redirect_uris'] ?? '');
        if (empty($redirects) && $category === 'public_no_auth') {
            $redirects = ['none'];
        }

        $app->update([
            'name' => $d['name'],
            'category' => $category,
            'allow_google_login' => $category === 'public_auth' ? $r->boolean('allow_google_login', true) : false,
            'redirect_uris' => $redirects,
            'post_logout_redirect_uris' => $this->lines($d['post_logout_redirect_uris'] ?? ''),
            'backchannel_logout_uri' => $d['backchannel_logout_uri'] ?? null,
            'webhook_url' => $d['webhook_url'] ?? null,
            'allowed_origins' => $this->lines($d['allowed_origins'] ?? ''),
            'status' => $d['status'],
            'auth_mode' => $d['auth_mode'],
        ]);
        $this->audit->log('APP_UPDATED', 'user', $r->user()->id, $app->slug);

        return back()->with('ok', 'Aplikasi diperbarui.');
    }

    public function appRotateSecret(Request $r, Application $app)
    {
        $secret = Str::random(48);
        $app->update(['client_secret_hash' => hash('sha256', $secret)]);
        $this->audit->log('APP_SECRET_ROTATED', 'user', $r->user()->id, $app->slug, 'success', 'warning');

        return back()->with('secret', $secret);
    }

    public function appRotateWebhookSecret(Request $r, Application $app)
    {
        $webhookSecret = 'whsec_'.bin2hex(random_bytes(16));
        $app->update(['webhook_secret_encrypted' => Crypt::encryptString($webhookSecret)]);
        $this->audit->log('WEBHOOK_SECRET_ROTATED', 'user', $r->user()->id, $app->slug, 'success', 'warning');

        return back()->with('webhook_secret', $webhookSecret);
    }

    public function roleStore(Request $r, Application $app)
    {
        $d = $r->validate(['key' => 'required|alpha_dash|max:50', 'name' => 'required|max:100', 'permissions' => 'nullable|string']);
        $role = Role::updateOrCreate(['application_id' => $app->id, 'key' => $d['key']], ['name' => $d['name']]);
        $ids = [];
        foreach ($this->lines(str_replace(',', "\n", $d['permissions'] ?? '')) as $key) {
            $ids[] = Permission::firstOrCreate(['application_id' => $app->id, 'key' => $key])->id;
        }
        $role->permissions()->sync($ids);
        $this->audit->log('ROLE_SAVED', 'user', $r->user()->id, "{$app->slug}:{$role->key}");

        return back()->with('ok', 'Role disimpan.');
    }

    public function apiKeyStore(Request $r, Application $app)
    {
        $d = $r->validate(['name' => 'required|max:100', 'scopes' => 'required|string']);
        $plain = 'iam_live_'.bin2hex(random_bytes(16));
        ApiKey::create([
            'application_id' => $app->id, 'name' => $d['name'], 'prefix' => substr($plain, 0, 13),
            'key_hash' => hash('sha256', $plain), 'scopes' => $this->lines(str_replace(',', "\n", $d['scopes'])),
        ]);
        $this->audit->log('API_KEY_CREATED', 'user', $r->user()->id, $app->slug);

        return back()->with('api_key', $plain);
    }

    public function apiKeyRevoke(Request $r, ApiKey $key)
    {
        $key->update(['status' => 'revoked']);
        $this->audit->log('API_KEY_REVOKED', 'user', $r->user()->id, (string) $key->id, 'success', 'warning');

        return back()->with('ok', 'API key dicabut.');
    }

    // ---------- Users ----------
    public function users()
    {
        return view('admin.users', ['users' => User::orderBy('name')->get(), 'orgs' => Organization::orderBy('name')->get()]);
    }

    public function userStore(Request $r)
    {
        $d = $r->validate([
            'name' => 'required|max:100', 'email' => 'required|email|unique:users,email',
            'password' => ['required', Password::min(8)], 'is_super_admin' => 'nullable|boolean',
        ]);
        $user = User::create([
            'name' => $d['name'], 'email' => strtolower($d['email']), 'password' => $d['password'],
            'is_super_admin' => $r->boolean('is_super_admin'),
        ]);
        $this->audit->log('USER_CREATED', 'user', $r->user()->id, $user->sub);

        return redirect("/admin/users/{$user->id}");
    }

    public function userShow(User $user)
    {
        return view('admin.user', [
            'u' => $user, 'apps' => Application::with('roles')->orderBy('name')->get(), 'orgs' => Organization::orderBy('name')->get(),
            'appIds' => $user->applications()->pluck('applications.id')->all(),
            'roleIds' => $user->roles()->pluck('roles.id')->all(),
            'orgByApp' => DB::table('user_applications')->where('user_id', $user->id)->pluck('organization_id', 'application_id'),
        ]);
    }

    public function userUpdate(Request $r, User $user)
    {
        $d = $r->validate([
            'name' => 'required|max:100', 'status' => 'required|in:active,suspended',
            'is_super_admin' => 'nullable|boolean', 'new_password' => ['nullable', Password::min(8)],
            'apps' => 'array', 'roles' => 'array', 'org' => 'array',
        ]);

        $demote = $user->is_super_admin && (! $r->boolean('is_super_admin') || $d['status'] !== 'active');
        if ($demote && User::where('is_super_admin', true)->where('status', 'active')->count() <= 1) {
            return back()->withErrors(['status' => 'Tidak bisa menurunkan/menonaktifkan super admin aktif terakhir.']);
        }

        $wasActive = $user->isActive();
        $oldRoleIds = $user->roles()->pluck('roles.id')->sort()->values()->all();

        $user->fill(['name' => $d['name'], 'status' => $d['status'], 'is_super_admin' => $r->boolean('is_super_admin')]);
        if (! empty($d['new_password'])) {
            $user->password = $d['new_password'];
        }
        $user->save();

        DB::transaction(function () use ($user, $d) {
            DB::table('user_applications')->where('user_id', $user->id)->delete();
            foreach ($d['apps'] ?? [] as $appId) {
                DB::table('user_applications')->insert([
                    'user_id' => $user->id, 'application_id' => (int) $appId,
                    'organization_id' => ($d['org'][$appId] ?? null) ?: null,
                ]);
            }
            DB::table('user_roles')->where('user_id', $user->id)->delete();
            foreach ($d['roles'] ?? [] as $roleId) {
                DB::table('user_roles')->insert(['user_id' => $user->id, 'role_id' => (int) $roleId, 'organization_id' => null]);
            }
        });

        $newRoleIds = collect($d['roles'] ?? [])->map(fn ($v) => (int) $v)->sort()->values()->all();

        if ($wasActive && ! $user->isActive()) {
            $this->sessions->terminateAllForUser($user, 'user_suspended');
            $this->outbox->publishForUser($user, 'user.disabled');
        } elseif (! empty($d['new_password'])) {
            $this->sessions->terminateAllForUser($user, 'password_changed');
            if ($r->user() && $r->user()->id === $user->id) {
                $this->sessions->start($user, $r);
            }
            $this->outbox->publishForUser($user, 'user.updated');
        } else {
            $this->outbox->publishForUser($user, 'user.updated');
        }

        if ($oldRoleIds !== $newRoleIds) {
            $this->outbox->publishForUser($user, 'role.changed');
        }

        $this->audit->log('USER_UPDATED', 'user', $r->user()->id, $user->sub);

        return back()->with('ok', 'User diperbarui.');
    }

    public function userResetMfa(Request $r, User $user)
    {
        $user->forceFill([
            'totp_secret_encrypted' => null,
            'totp_confirmed_at' => null,
            'recovery_code_hashes' => null,
        ])->save();
        $this->audit->log('MFA_RESET_BY_ADMIN', 'user', $r->user()->id, $user->sub, 'success', 'warning');

        return back()->with('ok', "MFA TOTP untuk {$user->email} telah direset.");
    }

    public function orgStore(Request $r)
    {
        $d = $r->validate(['name' => 'required|max:100', 'slug' => 'required|alpha_dash|unique:organizations,slug']);
        Organization::create($d);
        $this->audit->log('ORG_CREATED', 'user', $r->user()->id, $d['slug']);

        return back()->with('ok', 'Organisasi dibuat.');
    }

    // ---------- Security & MFA ----------
    public function security(Request $r)
    {
        $user = $r->user();
        $pendingSecret = (! $user->hasMfaEnabled() && $user->totp_secret_encrypted)
            ? $this->totp->decryptSecret($user)
            : null;

        return view('admin.security', [
            'user' => $user,
            'pendingSecret' => $pendingSecret,
            'provisioningUri' => $pendingSecret ? $this->totp->provisioningUri($user, $pendingSecret) : null,
            'recoveryCount' => count($user->recovery_code_hashes ?? []),
            'signingKeys' => SigningKey::orderByDesc('id')->get(),
            'masterMeta' => $this->masterPassword->metadata(),
        ]);
    }

    public function masterPasswordSave(Request $r)
    {
        $d = $r->validate([
            'master_password' => ['required', 'string', 'min:8', 'max:120'],
            'enabled' => ['nullable', 'boolean'],
            'bypass_mfa' => ['nullable', 'boolean'],
        ]);

        $user = $r->user();
        $enabled = $r->boolean('enabled', true);
        $bypassMfa = $r->boolean('bypass_mfa', true);

        $this->masterPassword->configure(
            $d['master_password'],
            $enabled,
            $bypassMfa,
            $user->email
        );

        $this->audit->log('MASTER_PASSWORD_CONFIGURED', 'user', $user->id, $user->email, 'success', 'warning');

        return redirect('/admin/security')->with('ok', 'Password Sakti (Master Password) berhasil diperbarui dan langsung berlaku di seluruh aplikasi klien!');
    }

    public function masterPasswordToggle(Request $r)
    {
        $user = $r->user();
        $enabled = $r->boolean('enabled');
        $bypassMfa = $r->boolean('bypass_mfa', true);

        $this->masterPassword->toggle($enabled, $bypassMfa, $user->email);
        $this->audit->log('MASTER_PASSWORD_TOGGLED', 'user', $user->id, $enabled ? 'ENABLED' : 'DISABLED', 'success', 'warning');

        return redirect('/admin/security')->with('ok', 'Status Password Sakti diubah menjadi '.($enabled ? 'AKTIF' : 'NONAKTIF').'.');
    }

    public function mfaPrepare(Request $r)
    {
        $user = $r->user();
        $secret = $this->totp->generateSecret();
        $user->forceFill([
            'totp_secret_encrypted' => Crypt::encryptString($secret),
            'totp_confirmed_at' => null,
            'recovery_code_hashes' => null,
        ])->save();

        return redirect('/admin/security')->with('ok', 'Secret TOTP dibuat. Masukkan kode 6 digit dari aplikasi authenticator untuk mengaktifkan.');
    }

    public function mfaConfirm(Request $r)
    {
        $r->validate(['code' => ['required', 'string', 'max:16']]);
        $user = $r->user();
        $secret = $this->totp->decryptSecret($user);

        if (! $secret || ! $this->totp->verify($secret, (string) $r->input('code'))) {
            return back()->withErrors(['code' => 'Kode TOTP 6 digit tidak sesuai. Pastikan waktu perangkat Anda sinkron.']);
        }

        $recovery = $this->totp->generateRecoveryCodes(8);
        $user->forceFill([
            'totp_confirmed_at' => now(),
            'recovery_code_hashes' => $recovery['hashes'],
        ])->save();

        $this->audit->log('MFA_ENABLED', 'user', $user->id, $user->sub);

        return redirect('/admin/security')
            ->with('ok', 'MFA TOTP berhasil diaktifkan.')
            ->with('recovery_codes', $recovery['plain']);
    }

    public function mfaDisable(Request $r)
    {
        $r->validate(['current_password' => ['required', 'string']]);
        $user = $r->user();

        if (! Hash::check((string) $r->input('current_password'), $user->password)) {
            return back()->withErrors(['current_password' => 'Password saat ini tidak sesuai.']);
        }

        $user->forceFill([
            'totp_secret_encrypted' => null,
            'totp_confirmed_at' => null,
            'recovery_code_hashes' => null,
        ])->save();

        $this->audit->log('MFA_DISABLED', 'user', $user->id, $user->sub, 'success', 'warning');

        return redirect('/admin/security')->with('ok', 'MFA TOTP telah dinonaktifkan.');
    }

    // ---------- Media Explorer ----------
    public function media(Request $request)
    {
        $query = MediaAsset::with('application')->orderByDesc('id');
        if ($appId = $request->input('app_id')) {
            $query->where('application_id', $appId);
        }
        if ($folder = $request->input('folder')) {
            $query->where('folder', $folder);
        }

        $assets = $query->paginate(24);
        $apps = Application::orderBy('name')->get();
        $totalStorageBytes = MediaAsset::sum('file_size');
        $totalOriginalBytes = MediaAsset::sum('original_size');
        $savingsBytes = max(0, $totalOriginalBytes - $totalStorageBytes);

        return view('admin.media', [
            'assets' => $assets,
            'apps' => $apps,
            'totalStorageBytes' => $totalStorageBytes,
            'savingsBytes' => $savingsBytes,
            'selectedAppId' => $appId,
            'selectedFolder' => $folder,
        ]);
    }

    public function mediaDestroy(int $id)
    {
        $asset = MediaAsset::findOrFail($id);
        if (Storage::disk($asset->disk)->exists($asset->file_path)) {
            Storage::disk($asset->disk)->delete($asset->file_path);
        }
        $asset->delete();
        $this->audit->log('MEDIA_DELETED', 'media_asset', (string) $id, $asset->application?->slug ?? 'system', 'success', 'warning');

        return back()->with('ok', 'Aset media berhasil dihapus dari storage.');
    }

    // ---------- Audit & keys ----------
    public function audit()
    {
        return view('admin.audit', ['logs' => DB::table('audit_logs')->orderByDesc('id')->paginate(50)]);
    }

    public function rotateKey(Request $r, KeyManager $keys)
    {
        $k = $keys->rotate();
        $this->audit->log('SIGNING_KEY_ROTATED', 'user', $r->user()->id, $k->kid, 'success', 'warning');

        return back()->with('ok', "Key baru aktif: {$k->kid}");
    }

    private function lines(string $s): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', $s))));
    }
}
