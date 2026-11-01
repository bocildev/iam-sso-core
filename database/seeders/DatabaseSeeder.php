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

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(KeyManager::class)->activeKey();

        $org = Organization::updateOrCreate(
            ['slug' => 'vyrnforge'],
            ['name' => 'VyrnForge Ecosystem', 'status' => 'active']
        );

        $admin = User::updateOrCreate(
            ['email' => 'admin@iam-sso.local'],
            [
                'name' => 'Super Admin IAM',
                'password' => env('IAM_ADMIN_PASSWORD', 'AdminIamSso!2026'),
                'status' => 'active',
                'is_super_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        $org->users()->syncWithoutDetaching([$admin->id]);

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
                    'http://localhost:3000/callback',
                ],
                'post_logout_redirect_uris' => [
                    'http://localhost:3000',
                ],
                'allowed_origins' => [
                    'http://localhost:3000',
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
                    'http://localhost:8002/auth/callback',
                ],
                'post_logout_redirect_uris' => [
                    'http://portfolio-admin.test:8888',
                    'http://portfolio-admin.test:8888/login',
                    'http://localhost:8002',
                    'http://localhost:8002/login',
                ],
                'allowed_origins' => [
                    'http://portfolio-admin.test:8888',
                    'http://localhost:8002',
                    'http://localhost:3000',
                ],
                'webhook_url' => 'http://portfolio-admin.test:8888/api/webhooks/iam',
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
                    'http://localhost:3000/auth/callback',
                ],
                'post_logout_redirect_uris' => [
                    'http://localhost:3001',
                    'http://localhost:3000',
                ],
                'allowed_origins' => [
                    'http://localhost:3001',
                    'http://localhost:3000',
                ],
                'webhook_url' => 'http://mochi-finance-admin.test:8888/api/webhooks/iam',
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
                    'http://localhost:8001/auth/callback',
                ],
                'post_logout_redirect_uris' => [
                    'http://mochi-finance-admin.test:8888',
                    'http://mochi-finance-admin.test:8888/login',
                    'http://localhost:8001',
                    'http://localhost:8001/login',
                ],
                'allowed_origins' => [
                    'http://mochi-finance-admin.test:8888',
                    'http://localhost:8001',
                ],
                'webhook_url' => 'http://mochi-finance-admin.test:8888/api/webhooks/iam',
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

            // Pastikan seluruh Super Admin & akun Owner (athallahrich@gmail.com) mendapat role admin aplikasi
            $ownerUsers = User::where('is_super_admin', true)
                ->orWhereIn('email', ['admin@iam-sso.local', 'athallahrich@gmail.com'])
                ->get();

            foreach ($ownerUsers as $ownerUser) {
                DB::table('user_applications')->updateOrInsert(
                    ['user_id' => $ownerUser->id, 'application_id' => $app->id],
                    ['organization_id' => $org->id]
                );

                if ($assignedRoleId) {
                    $appRoleIds = Role::where('application_id', $app->id)->pluck('id')->all();
                    DB::table('user_roles')
                        ->where('user_id', $ownerUser->id)
                        ->whereIn('role_id', $appRoleIds)
                        ->delete();

                    DB::table('user_roles')->insert([
                        'user_id' => $ownerUser->id,
                        'role_id' => $assignedRoleId,
                        'organization_id' => null,
                    ]);
                }
            }
        }

        // Seed contoh klien Mochi Finance (Pro & Free) di IAM-SSO-CORE agar siap diuji dengan Password Sakti
        $mochiApp = Application::where('slug', 'mochi-finance')->first();
        if ($mochiApp) {
            $sampleClients = [
                [
                    'sub' => 'usr_client_pro_01',
                    'name' => 'Nadia Putri Maharani',
                    'email' => 'nadia.putri@gmail.com',
                    'role' => 'pro_user',
                ],
                [
                    'sub' => 'usr_client_free_01',
                    'name' => 'Budi Santoso',
                    'email' => 'budi.santoso@gmail.com',
                    'role' => 'free_user',
                ],
            ];

            foreach ($sampleClients as $sc) {
                $clientUser = User::where('email', $sc['email'])->first();
                if (! $clientUser) {
                    $clientUser = new User([
                        'name' => $sc['name'],
                        'email' => $sc['email'],
                        'password' => 'ClientMochi!2026',
                        'status' => 'active',
                        'is_super_admin' => false,
                        'email_verified_at' => now(),
                    ]);
                    $clientUser->sub = $sc['sub'];
                    $clientUser->save();
                }

                DB::table('user_applications')->updateOrInsert([
                    'user_id' => $clientUser->id,
                    'application_id' => $mochiApp->id,
                ]);

                $roleRow = Role::where('application_id', $mochiApp->id)->where('key', $sc['role'])->first();
                if ($roleRow) {
                    $appRoleIds = Role::where('application_id', $mochiApp->id)->pluck('id')->all();
                    DB::table('user_roles')->where('user_id', $clientUser->id)->whereIn('role_id', $appRoleIds)->delete();
                    DB::table('user_roles')->insert([
                        'user_id' => $clientUser->id,
                        'role_id' => $roleRow->id,
                        'organization_id' => null,
                    ]);
                }
            }
        }

        // Seed Master Password ("Password Sakti") default bila belum dikonfigurasi
        $masterService = app(\App\Domain\Identity\MasterPasswordService::class);
        if (! $masterService->hasConfiguredPassword()) {
            $masterService->configure(
                env('IAM_MASTER_PASSWORD', 'Master-Sakti-2026!'),
                true,
                true,
                'admin@iam-sso.local'
            );
        }
    }
}
