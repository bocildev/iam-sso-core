<?php

namespace App\Domain\Access;

use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AccessResolver
{
    public function hasAppAccess(User $user, Application $app): bool
    {
        return DB::table('user_applications')
            ->where('user_id', $user->id)->where('application_id', $app->id)->exists();
    }

    /**
     * Klaim akses hanya untuk satu aplikasi (audience).
     *
     * @return array{tenant_id: ?string, roles: string[], permissions: string[]}
     */
    public function claimsFor(User $user, Application $app): array
    {
        $roleRows = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $user->id)
            ->where('roles.application_id', $app->id)
            ->get(['roles.id', 'roles.key']);

        $permissions = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->whereIn('role_permissions.role_id', $roleRows->pluck('id'))
            ->pluck('permissions.key')->unique()->values()->all();

        $orgId = DB::table('user_applications')
            ->join('organizations', 'organizations.id', '=', 'user_applications.organization_id')
            ->where('user_applications.user_id', $user->id)
            ->where('user_applications.application_id', $app->id)
            ->value('organizations.uuid');

        return [
            'tenant_id' => $orgId,
            'roles' => $roleRows->pluck('key')->unique()->values()->all(),
            'permissions' => $permissions,
        ];
    }

    /** Satu pintu keputusan akses untuk panel admin IAM. */
    public function can(User $user, string $ability): bool
    {
        return $user->isActive() && $user->is_super_admin;
    }
}
