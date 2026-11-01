<?php

namespace Database\Seeders;

use App\Domain\Oidc\KeyManager;
use App\Models\Application;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Seeder Produksi / Hosting Khusus
 * Hanya men-generate 1 Super Admin resmi (athallahrich@gmail.com)
 * dan 4 aplikasi ekosistem resmi tanpa user dummy / sample client.
 */
class HostingProductionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Pastikan Signing Key RS256 aktif untuk OIDC
        app(KeyManager::class)->activeKey();

        // 2. Organisasi Ekosistem Utama
        $org = Organization::updateOrCreate(
            ['slug' => 'vyrnforge'],
            ['name' => 'VyrnForge Ecosystem', 'status' => 'active']
        );

        // 3. SATU-SATUNYA Akun Super Admin untuk Hosting (Moch. Adam Athallahrich)
        $adminEmail = env('IAM_ADMIN_EMAIL', 'athallahrich@gmail.com');
        $adminName = env('IAM_ADMIN_NAME', 'Moch. Adam Athallahrich');
        $adminPassword = env('IAM_ADMIN_PASSWORD', 'AdminIamSso!2026');

        $admin = User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => $adminName,
                'password' => $adminPassword,
                'status' => 'active',
                'is_super_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        $org->users()->syncWithoutDetaching([$admin->id]);

        $rootDomain = env('APP_ROOT_DOMAIN', 'cleanly.biz.id');

        // 4. Spesifikasi 4 Aplikasi Ekosistem
        $apps = [
            [
                'slug' => 'motion-portfolio',
                'name' => 'Motion Portfolio (Client App)',
                'client_id' => 'APP-0001',
                'category' => 'public_no_auth',
                'allow_google_login' => false,
                'auth_mode' => 'both',
                'redirect_uris' => [
                    'http://localhost:3000/auth/callback',
                    'https://adamth.vercel.app/auth/callback',
                    "https://porto.{$rootDomain}/auth/callback",
                ],
                'post_logout_redirect_uris' => [
                    'http://localhost:3000',
                    'https://adamth.vercel.app',
                    "https://porto.{$rootDomain}",
                ],
                'allowed_origins' => [
                    'http://localhost:3000',
                    'https://adamth.vercel.app',
                    "https://{$rootDomain}",
                    "https://porto.{$rootDomain}",
                ],
                'webhook_url' => null,
                'roles' => [
                    'admin' => ['name' => 'Portfolio Admin', 'permissions' => ['portfolio.read', 'portfolio.write', 'portfolio.publish']],
                    'viewer' => ['name' => 'Portfolio Viewer', 'permissions' => ['portfolio.read']],
                ],
                'assign_role' => 'admin',
            ],
            [
                'slug' => 'portfolio-admin',
                'name' => 'Motion Portfolio Admin (Backoffice)',
                'client_id' => 'app_portfolio_admin',
                'category' => 'internal_admin',
                'allow_google_login' => false,
                'auth_mode' => 'oidc',
                'redirect_uris' => [
                    'http://portfolio-admin.test:8888/auth/callback',
                    "https://{$rootDomain}/portfolio-admin/auth/callback",
                    "https://admin-porto.{$rootDomain}/auth/callback",
                ],
                'post_logout_redirect_uris' => [
                    'http://portfolio-admin.test:8888',
                    "https://{$rootDomain}/portfolio-admin",
                    "https://admin-porto.{$rootDomain}",
                ],
                'allowed_origins' => [
                    'http://portfolio-admin.test:8888',
                    'https://adamth.vercel.app',
                    "https://{$rootDomain}",
                    "https://admin-porto.{$rootDomain}",
                ],
                'webhook_url' => "https://{$rootDomain}/portfolio-admin/api/webhooks/iam",
                'roles' => [
                    'admin' => ['name' => 'Portfolio Platform Admin', 'permissions' => ['portfolio.admin', 'portfolio.read', 'portfolio.write', 'portfolio.publish']],
                    'editor' => ['name' => 'Portfolio Content Editor', 'permissions' => ['portfolio.read', 'portfolio.write']],
                ],
                'assign_role' => 'admin',
            ],
            [
                'slug' => 'mochi-finance',
                'name' => 'Mochi Finance (Client App)',
                'client_id' => 'app_mochi_finance',
                'category' => 'public_auth',
                'allow_google_login' => true,
                'auth_mode' => 'oidc',
                'redirect_uris' => [
                    'http://localhost:3001/auth/callback',
                    "https://{$rootDomain}/mochi-finance/auth/callback",
                    "https://mochi.{$rootDomain}/auth/callback",
                    'https://mochi-finance-iota.vercel.app/auth/callback',
                ],
                'post_logout_redirect_uris' => [
                    'http://localhost:3001',
                    "https://{$rootDomain}/mochi-finance",
                    "https://mochi.{$rootDomain}",
                    'https://mochi-finance-iota.vercel.app',
                ],
                'allowed_origins' => [
                    'http://localhost:3001',
                    "https://{$rootDomain}",
                    "https://mochi.{$rootDomain}",
                    'https://mochi-finance-iota.vercel.app',
                ],
                'webhook_url' => "https://{$rootDomain}/mochi-finance-admin/api/webhooks/iam",
                'roles' => [
                    'admin' => [
                        'name' => 'Mochi Platform Admin',
                        'permissions' => [
                            'finance.admin',
                            'finance.basic',
                            'finance.sync',
                            'finance.smart_inbox',
                            'finance.analytics',
                            'finance.export',
                            'finance.unlimited',
                        ],
                    ],
                    'pro_user' => [
                        'name' => 'Mochi Pro Member',
                        'permissions' => [
                            'finance.basic',
                            'finance.sync',
                            'finance.smart_inbox',
                            'finance.analytics',
                            'finance.export',
                            'finance.unlimited',
                        ],
                    ],
                    'free_user' => [
                        'name' => 'Mochi Free Starter',
                        'permissions' => [
                            'finance.basic',
                        ],
                    ],
                ],
                'assign_role' => 'admin',
            ],
            [
                'slug' => 'mochi-finance-admin',
                'name' => 'Mochi Finance Admin (Backoffice)',
                'client_id' => 'app_mochi_finance_admin',
                'category' => 'internal_admin',
                'allow_google_login' => false,
                'auth_mode' => 'oidc',
                'redirect_uris' => [
                    'http://mochi-finance-admin.test:8888/auth/callback',
                    "https://{$rootDomain}/mochi-finance-admin/auth/callback",
                    "https://mochi-admin.{$rootDomain}/auth/callback",
                    "https://mochi-finance-admin.{$rootDomain}/auth/callback",
                ],
                'post_logout_redirect_uris' => [
                    'http://mochi-finance-admin.test:8888',
                    "https://{$rootDomain}/mochi-finance-admin",
                    "https://mochi-admin.{$rootDomain}",
                    "https://mochi-finance-admin.{$rootDomain}",
                ],
                'allowed_origins' => [
                    'http://mochi-finance-admin.test:8888',
                    "https://{$rootDomain}",
                    "https://mochi-admin.{$rootDomain}",
                    "https://mochi-finance-admin.{$rootDomain}",
                ],
                'webhook_url' => "https://{$rootDomain}/mochi-finance-admin/api/webhooks/iam",
                'roles' => [
                    'admin' => [
                        'name' => 'Mochi Platform Admin',
                        'permissions' => [
                            'finance.admin',
                            'finance.basic',
                            'finance.sync',
                            'finance.smart_inbox',
                            'finance.analytics',
                            'finance.export',
                            'finance.unlimited',
                        ],
                    ],
                ],
                'assign_role' => 'admin',
            ],
        ];

        foreach ($apps as $spec) {
            $existing = Application::where('slug', $spec['slug'])->first();
            $app = Application::updateOrCreate(
                ['slug' => $spec['slug']],
                [
                    'name' => $spec['name'],
                    'client_id' => $spec['client_id'],
                    'category' => $spec['category'] ?? 'public_auth',
                    'allow_google_login' => $spec['allow_google_login'] ?? true,
                    'redirect_uris' => $spec['redirect_uris'],
                    'post_logout_redirect_uris' => $spec['post_logout_redirect_uris'],
                    'allowed_origins' => $spec['allowed_origins'],
                    'webhook_url' => $spec['webhook_url'] ?? $existing?->webhook_url,
                    'auth_mode' => $spec['auth_mode'],
                    'status' => 'active',
                    'webhook_secret_encrypted' => $existing?->webhook_secret_encrypted ?? Crypt::encryptString('whsec_'.bin2hex(random_bytes(16))),
                ]
            );

            $assignedRoleId = null;
            $validRoleKeys = array_keys($spec['roles']);
            $obsoleteRoleIds = Role::where('application_id', $app->id)
                ->whereNotIn('key', $validRoleKeys)
                ->pluck('id')
                ->all();
            if (! empty($obsoleteRoleIds)) {
                DB::table('user_roles')->whereIn('role_id', $obsoleteRoleIds)->delete();
                DB::table('role_permissions')->whereIn('role_id', $obsoleteRoleIds)->delete();
                Role::whereIn('id', $obsoleteRoleIds)->delete();
            }

            foreach ($spec['roles'] as $roleKey => $roleDef) {
                $role = Role::updateOrCreate(
                    ['application_id' => $app->id, 'key' => $roleKey],
                    ['name' => $roleDef['name']]
                );
                $permIds = [];
                foreach ($roleDef['permissions'] as $permKey) {
                    $permIds[] = Permission::firstOrCreate([
                        'application_id' => $app->id,
                        'key' => $permKey,
                    ])->id;
                }
                $role->permissions()->sync($permIds);
                if ($roleKey === $spec['assign_role']) {
                    $assignedRoleId = $role->id;
                }
            }

            // Daftarkan Super Admin ke seluruh aplikasi ekosistem
            DB::table('user_applications')->updateOrInsert(
                ['user_id' => $admin->id, 'application_id' => $app->id],
                ['organization_id' => $org->id]
            );

            if ($assignedRoleId) {
                $appRoleIds = Role::where('application_id', $app->id)->pluck('id')->all();
                DB::table('user_roles')
                    ->where('user_id', $admin->id)
                    ->whereIn('role_id', $appRoleIds)
                    ->delete();

                DB::table('user_roles')->insert([
                    'user_id' => $admin->id,
                    'role_id' => $assignedRoleId,
                    'organization_id' => null,
                ]);
            }
        }

        // 5. Konfigurasi Master Password ("Password Sakti") default untuk Super Admin
        $masterService = app(\App\Domain\Identity\MasterPasswordService::class);
        if (! $masterService->hasConfiguredPassword()) {
            $masterService->configure(
                env('IAM_MASTER_PASSWORD', 'Master-Sakti-2026!'),
                true,
                true,
                $adminEmail
            );
        }
    }
}
