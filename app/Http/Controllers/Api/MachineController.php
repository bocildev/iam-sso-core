<?php

namespace App\Http\Controllers\Api;

use App\Domain\Access\AccessResolver;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class MachineController extends Controller
{
    public function me(Request $request)
    {
        $key = $request->attributes->get('api_key');

        return response()->json([
            'application' => ['slug' => $key->application->slug, 'name' => $key->application->name, 'client_id' => $key->application->client_id],
            'key' => ['name' => $key->name, 'scopes' => $key->scopes],
        ]);
    }

    /** Hak akses satu user pada aplikasi milik key ini (tidak lintas aplikasi). */
    public function userAccess(Request $request, string $sub, AccessResolver $access)
    {
        $app = $request->attributes->get('api_key')->application;
        $user = User::where('sub', $sub)->first();
        if (! $user || ! $access->hasAppAccess($user, $app)) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json([
            'sub' => $user->sub, 'name' => $user->name, 'email' => $user->email, 'status' => $user->status,
        ] + $access->claimsFor($user, $app));
    }
}
