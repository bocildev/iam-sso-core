<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Oidc\SessionService;
use App\Models\Application;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function __construct(
        private SessionService $sessions,
        private AuditLogger $audit,
    ) {}

    public function show()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $email = strtolower($data['email']);

        $user = DB::transaction(function () use ($data, $email) {
            $user = User::create([
                'name' => trim($data['name']),
                'email' => $email,
                'password' => $data['password'],
                'status' => 'active',
                'is_super_admin' => false,
                'email_verified_at' => now(),
            ]);

            $org = Organization::where('slug', 'vyrnforge')->first();
            if ($org) {
                $org->users()->syncWithoutDetaching([$user->id]);
            }

            // Otomatis berikan akses Free Starter ke aplikasi Mochi Finance
            $mochiApp = Application::where('slug', 'mochi-finance')->first();
            if ($mochiApp) {
                DB::table('user_applications')->updateOrInsert(
                    ['user_id' => $user->id, 'application_id' => $mochiApp->id],
                    ['organization_id' => $org?->id]
                );

                $freeRole = Role::where('application_id', $mochiApp->id)
                    ->where('key', 'free_user')
                    ->first();

                if ($freeRole) {
                    DB::table('user_roles')->insert([
                        'user_id' => $user->id,
                        'role_id' => $freeRole->id,
                        'organization_id' => null,
                    ]);
                }
            }

            return $user;
        });

        $intended = $request->session()->get('iam_intended');
        $request->session()->regenerate();
        Auth::login($user);
        $this->sessions->start($user, $request);
        $this->audit->log('USER_SELF_REGISTERED', 'user', $user->id, 'mochi-finance', 'success', 'info', [
            'default_role' => 'free_user',
        ]);

        if ($intended) {
            $request->session()->forget('iam_intended');

            return redirect()->to($intended);
        }

        return redirect('/login')->with('ok', 'Akun berhasil dibuat dengan level Free Starter! Silakan kembali ke aplikasi klien Anda.');
    }
}
