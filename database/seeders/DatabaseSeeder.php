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
                'name' => 'Motion Portfolio',
                'client_id' => 'APP-0001',
                'auth_mode' => 'both',
                'redirect_uris' => ['http://localhost:3000/auth/callback', 'http://localhost:3000/callback'],
                'post_logout_redirect_uris' => ['http://localhost:3000'],
                'allowed_origins' => ['http://localhost:3000'],
                'roles' => [
                    'admin' => ['name' => 'Portfolio Admin', 'permissions' => ['portfolio.read', 'portfolio.write', 'portfolio.publish']],
                    'viewer' => ['name' => 'Portfolio Viewer', 'permissions' => ['portfolio.read']],
                ],
                'assign_role' => 'admin',
            ],
            [
                'slug' => 'mochi-finance',
                'name' => 'Mochi Finance',
                'client_id' => 'app_mochi_finance',
                'auth_mode' => 'oidc',
                'redirect_uris' => ['http://localhost:3001/auth/callback', 'http://localhost:3000/auth/callback'],
                'post_logout_redirect_uris' => ['http://localhost:3001'],
                'allowed_origins' => ['http://localhost:3001', 'http://localhost:3000'],
                'roles' => [
                    'owner' => ['name' => 'Finance Owner', 'permissions' => ['finance.read', 'finance.write', 'finance.export']],
                    'member' => ['name' => 'Finance Member', 'permissions' => ['finance.read', 'finance.write']],
                ],
                'assign_role' => 'owner',
            ],
        ];

        foreach ($apps as $spec) {
            $existing = Application::where('slug', $spec['slug'])->first();
            $app = Application::updateOrCreate(
                ['slug' => $spec['slug']],
                [
                    'name' => $spec['name'],
                    'client_id' => $spec['client_id'],
                    'redirect_uris' => $spec['redirect_uris'],
                    'post_logout_redirect_uris' => $spec['post_logout_redirect_uris'],
                    'allowed_origins' => $spec['allowed_origins'],
                    'auth_mode' => $spec['auth_mode'],
                    'status' => 'active',
                    'webhook_secret_encrypted' => $existing?->webhook_secret_encrypted ?? Crypt::encryptString('whsec_'.bin2hex(random_bytes(16))),
                ]
            );

            $assignedRoleId = null;
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

            DB::table('user_applications')->updateOrInsert(
                ['user_id' => $admin->id, 'application_id' => $app->id],
                ['organization_id' => $org->id]
            );

            if ($assignedRoleId) {
                $hasRole = DB::table('user_roles')
                    ->where('user_id', $admin->id)
                    ->where('role_id', $assignedRoleId)
                    ->exists();
                if (! $hasRole) {
                    DB::table('user_roles')->insert([
                        'user_id' => $admin->id,
                        'role_id' => $assignedRoleId,
                        'organization_id' => null,
                    ]);
                }
            }
        }
    }
}
