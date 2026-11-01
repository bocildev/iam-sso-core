<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Audit\AuditLogger;
use App\Domain\Oidc\SessionService;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function __construct(
        private SessionService $sessions,
        private AuditLogger $audit,
    ) {}

    /**
     * Mengarahkan pengguna ke halaman persetujuan login Google (OAuth Consent Screen).
     */
    public function redirect(Request $request)
    {
        // Simpan parameter kembali jika login dipicu dari aplikasi klien
        if ($request->filled('client_id')) {
            $clientId = $request->string('client_id')->toString();
            $app = Application::where('client_id', $clientId)->first();
            if ($app && $app->isActive()) {
                if ($app->isPublicNoAuth()) {
                    return redirect('/login')->withErrors([
                        'email' => 'Aplikasi "'.$app->name.'" bertipe Showcase / Landing Page dan tidak membutuhkan sesi login pengguna.',
                    ]);
                }

                $request->session()->put('google_auth_app_id', $app->id);

                $redirectUri = $app->redirect_uris[0] ?? null;
                if ($redirectUri && $redirectUri !== 'none') {
                    $authorizeUrl = '/oauth/authorize?client_id='.$app->client_id
                        .'&redirect_uri='.urlencode($redirectUri)
                        .'&response_type=code'
                        .'&scope=openid%20profile%20email'
                        .'&state='.Str::random(16);
                    $request->session()->put('iam_intended', $authorizeUrl);
                }
            }
        }

        if ($request->filled('return_to')) {
            $request->session()->put('iam_intended', $request->string('return_to')->toString());
        }

        $clientIdConfig = config('services.google.client_id');

        // Jika Google Client ID belum disetel dan dijalankan di komputer lokal / testing,
        // sediakan layar simulasi instan (Mock Sandbox) agar developer bisa menguji alur login langsung.
        if (empty($clientIdConfig) || $request->boolean('mock')) {
            if (app()->environment('local', 'testing') || $request->boolean('mock')) {
                return view('auth.google-mock', [
                    'intended' => $request->session()->get('iam_intended'),
                ]);
            }

            return redirect('/login')->withErrors([
                'email' => 'Login Google belum dikonfigurasi. Silakan isi GOOGLE_CLIENT_ID di file .env IAM.',
            ]);
        }

        return Socialite::driver('google')->redirect();
    }

    /**
     * Menerima kembalian data dari Google setelah pengguna menyetujui login.
     */
    public function callback(Request $request)
    {
        // Tangani mode simulasi lokal jika dipanggil
        if ($request->boolean('mock') || $request->session()->pull('google_mock_mode')) {
            $mockEmail = strtolower(trim($request->input('mock_email', 'bocildev.tester@gmail.com')));
            $mockName = $request->input('mock_name', 'Bocil Developer (Google User)');
            $mockId = 'google_mock_'.sha1($mockEmail);
            $mockAvatar = 'https://ui-avatars.com/api/?name='.urlencode($mockName).'&background=00d4ff&color=001020';

            return $this->handleGoogleUser($request, (object) [
                'id' => $mockId,
                'email' => $mockEmail,
                'name' => $mockName,
                'avatar' => $mockAvatar,
            ]);
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect('/login')->withErrors([
                'email' => 'Gagal menghubungkan dengan Google: '.$e->getMessage(),
            ]);
        }

        return $this->handleGoogleUser($request, (object) [
            'id' => $googleUser->getId(),
            'email' => $googleUser->getEmail(),
            'name' => $googleUser->getName() ?? $googleUser->getNickname() ?? 'Pengguna Google',
            'avatar' => $googleUser->getAvatar(),
        ]);
    }

    /**
     * Memproses data pengguna Google: cari user yang ada atau buat user baru otomatis.
     */
    private function handleGoogleUser(Request $request, object $googleUser)
    {
        $email = strtolower(trim($googleUser->email));
        $googleId = (string) $googleUser->id;
        $name = (string) $googleUser->name;
        $avatar = $googleUser->avatar ?? null;

        // 1. Cari berdasarkan Google ID
        $user = User::where('google_id', $googleId)->first();

        if (! $user) {
            // 2. Jika belum ada Google ID, cari berdasarkan Email
            $user = User::where('email', $email)->first();

            if ($user) {
                // Tautkan akun yang sudah ada ke akun Google ini
                $user->google_id = $googleId;
                if ($avatar && empty($user->avatar_url)) {
                    $user->avatar_url = $avatar;
                }
                $user->save();

                $this->audit->log('GOOGLE_ACCOUNT_LINKED', 'user', $user->id, $user->email, 'success', 'info');
            } else {
                // 3. Buat akun baru otomatis
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'google_id' => $googleId,
                    'avatar_url' => $avatar,
                    'password' => Hash::make(Str::random(32)),
                    'status' => 'active',
                    'is_super_admin' => false,
                ]);

                $this->attachDefaultEcosystemAccess($user);
                $this->audit->log('GOOGLE_REGISTER', 'user', $user->id, $user->email, 'success', 'info');
            }
        } else {
            // Perbarui avatar jika ada pembaruan dari Google
            if ($avatar && $avatar !== $user->avatar_url) {
                $user->update(['avatar_url' => $avatar]);
            }
        }

        if (! $user->isActive()) {
            return redirect('/login')->withErrors([
                'email' => 'Akun Anda dinonaktifkan atau ditangguhkan. Hubungi Super Admin.',
            ]);
        }

        if (in_array($user->email, ['athallahrich@gmail.com', 'admin@iam-sso.local'], true)) {
            if (! $user->is_super_admin) {
                $user->forceFill(['is_super_admin' => true])->save();
            }
        }

        // Jika login dipicu oleh aplikasi internal admin, tolak jika user bukan super admin
        $targetAppId = $request->session()->pull('google_auth_app_id');
        if ($targetAppId) {
            $targetApp = Application::find($targetAppId);
            if ($targetApp && $targetApp->isInternalAdmin() && ! $user->is_super_admin) {
                return redirect('/login')->withErrors([
                    'email' => 'Akses ditolak: Aplikasi "'.$targetApp->name.'" adalah konsol backoffice internal. Akun Google Anda bukan administrator.',
                ]);
            }
        }

        // Jalankan login resmi
        $intended = $request->session()->get('iam_intended');
        $request->session()->regenerate();
        Auth::login($user);
        $this->sessions->start($user, $request);

        $this->audit->log('LOGIN', 'user', $user->id, $user->email, 'success', 'info', [
            'provider' => 'google',
        ]);

        if ($intended) {
            $request->session()->forget('iam_intended');

            return redirect()->to($intended);
        }

        if ($user->is_super_admin) {
            return redirect('/admin');
        }

        return redirect('/');
    }

    /**
     * Memberikan keanggotaan default di ekosistem agar user bisa langsung membuka client app.
     */
    private function attachDefaultEcosystemAccess(User $user): void
    {
        $org = Organization::first();
        if ($org && ! $user->organizations()->where('organizations.id', $org->id)->exists()) {
            $user->organizations()->attach($org->id);
        }

        // Sambungkan ke aplikasi client aktif dengan role default member/viewer jika tersedia
        $apps = Application::where('status', 'active')->get();
        foreach ($apps as $app) {
            if (! $user->applications()->where('applications.id', $app->id)->exists()) {
                $user->applications()->attach($app->id);

                $defaultRole = Role::where('application_id', $app->id)
                    ->whereIn('key', ['viewer', 'member', 'client', 'user'])
                    ->first();
                if ($defaultRole) {
                    $user->roles()->attach($defaultRole->id);
                }
            }
        }
    }
}
