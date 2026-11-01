<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class DebuggerController extends Controller
{
    /**
     * Tampilkan halaman Debugger, System Diagnostic, dan Web Artisan Console.
     */
    public function index(): View
    {
        abort_unless(auth()->user()?->is_super_admin, 403, 'Akses ditolak: Hanya Super Admin yang diizinkan mengakses Debugger Server.');

        // 1. Diagnostik Database & Migrasi
        $dbStatus = 'online';
        $dbName = config('database.connections.mysql.database');
        $dbHost = config('database.connections.mysql.host');
        $tableCount = 0;
        $migrationStats = ['ran' => 0, 'pending' => 0];

        try {
            DB::connection()->getPdo();
            $tables = DB::select('SHOW TABLES');
            $tableCount = count($tables);

            $migrationFiles = count(File::glob(database_path('migrations/*.php')));
            $ranCount = DB::table('migrations')->count();
            $migrationStats = [
                'ran' => $ranCount,
                'pending' => max(0, $migrationFiles - $ranCount),
            ];
        } catch (\Throwable $e) {
            $dbStatus = 'offline (' . $e->getMessage() . ')';
        }

        // 2. Pemeriksaan Hak Akses Folder Writable (Penting untuk hosting)
        $folders = [
            'storage/framework/views' => storage_path('framework/views'),
            'storage/framework/sessions' => storage_path('framework/sessions'),
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/logs' => storage_path('logs'),
            'storage/app/public' => storage_path('app/public'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];

        $folderChecks = [];
        foreach ($folders as $label => $path) {
            if (! File::exists($path)) {
                @File::makeDirectory($path, 0775, true);
            }
            $folderChecks[$label] = [
                'path' => $path,
                'exists' => File::exists($path),
                'writable' => is_writable($path),
            ];
        }

        // 3. Pemeriksaan Ekstensi PHP Wajib
        $requiredExtensions = [
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'openssl' => extension_loaded('openssl'),
            'mbstring' => extension_loaded('mbstring'),
            'curl' => extension_loaded('curl'),
            'gd' => extension_loaded('gd'),
            'fileinfo' => extension_loaded('fileinfo'),
            'zip' => extension_loaded('zip'),
            'bcmath' => extension_loaded('bcmath'),
            'xml' => extension_loaded('xml'),
        ];

        // 4. Informasi Lingkungan Hosting
        $serverInfo = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? php_uname('s'),
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug') ? 'true' : 'false',
            'app_url' => config('app.url'),
            'session_driver' => config('session.driver'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time') . 's',
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'storage_symlink_exists' => File::exists(public_path('storage')),
        ];

        return view('admin.debugger', compact(
            'dbStatus',
            'dbName',
            'dbHost',
            'tableCount',
            'migrationStats',
            'folderChecks',
            'requiredExtensions',
            'serverInfo'
        ));
    }

    /**
     * Jalankan perintah Artisan yang aman dari Web Console.
     */
    public function run(Request $request)
    {
        abort_unless(auth()->user()?->is_super_admin, 403, 'Akses ditolak: Hanya Super Admin yang diizinkan menjalankan perintah.');

        $request->validate([
            'action' => 'required|string|max:255',
        ]);

        $action = trim($request->input('action'));
        $startTime = microtime(true);

        // Daftar aksi preset & whitelist perintah artisan yang aman
        $commandToRun = null;
        $params = [];

        switch ($action) {
            case 'migrate':
                $commandToRun = 'migrate';
                $params = ['--force' => true];
                break;
            case 'migrate:status':
                $commandToRun = 'migrate:status';
                break;
            case 'seed:hosting':
                $commandToRun = 'db:seed';
                $params = ['--class' => 'HostingProductionSeeder', '--force' => true];
                break;
            case 'seed:default':
                $commandToRun = 'db:seed';
                $params = ['--force' => true];
                break;
            case 'optimize:clear':
                $commandToRun = 'optimize:clear';
                break;
            case 'optimize':
                $commandToRun = 'optimize';
                break;
            case 'storage:link':
                $commandToRun = 'storage:link';
                $params = ['--force' => true];
                break;
            case 'iam:outbox-work':
                $commandToRun = 'iam:outbox-work';
                break;
            case 'custom':
                $customCmd = trim($request->input('custom_command', ''));
                if (empty($customCmd)) {
                    return back()->withErrors(['custom_command' => 'Perintah custom tidak boleh kosong.']);
                }

                // Whitelist ketat prefix artisan yang diizinkan
                $allowedPrefixes = [
                    'migrate',
                    'migrate:status',
                    'db:seed',
                    'cache:clear',
                    'config:cache',
                    'config:clear',
                    'route:cache',
                    'route:clear',
                    'view:cache',
                    'view:clear',
                    'optimize',
                    'optimize:clear',
                    'storage:link',
                    'iam:',
                ];

                $isAllowed = false;
                foreach ($allowedPrefixes as $prefix) {
                    if (str_starts_with($customCmd, $prefix)) {
                        $isAllowed = true;
                        break;
                    }
                }

                // Blokir perintah berbahaya
                $blockedPatterns = ['rm', 'drop', 'truncate', 'exec', 'system', 'eval', 'passport', 'tinker'];
                foreach ($blockedPatterns as $b) {
                    if (stripos($customCmd, $b) !== false && ! str_starts_with($customCmd, 'migrate:fresh')) {
                        $isAllowed = false;
                        break;
                    }
                }

                if (! $isAllowed) {
                    return back()->withErrors(['custom_command' => "Perintah '{$customCmd}' ditolak demi alasan keamanan server."]);
                }

                // Pecah perintah dan argumen sederhana
                $parts = explode(' ', $customCmd);
                $commandToRun = array_shift($parts);
                foreach ($parts as $p) {
                    if (str_starts_with($p, '--')) {
                        $params[$p] = true;
                    }
                }
                break;
            default:
                return back()->withErrors(['action' => "Aksi '{$action}' tidak dikenal."]);
        }

        try {
            $exitCode = Artisan::call($commandToRun, $params);
            $output = Artisan::output();
            if (empty(trim($output))) {
                $output = "Perintah '{$commandToRun}' selesai dieksekusi tanpa log output (Exit Code: {$exitCode}).";
            }
        } catch (\Throwable $e) {
            $exitCode = 1;
            $output = "Terjadi Kesalahan:\n" . $e->getMessage();
        }

        $durationMs = round((microtime(true) - $startTime) * 1000);

        // Catat ke Audit Log Keamanan
        try {
            app(AuditLogger::class)->log(
                action: 'DEBUGGER_COMMAND_EXECUTED',
                actorType: 'user',
                actorId: auth()->id(),
                target: $commandToRun,
                result: $exitCode === 0 ? 'success' : 'failed',
                severity: $exitCode === 0 ? 'info' : 'warning',
                meta: [
                    'command' => $commandToRun,
                    'params' => $params,
                    'exit_code' => $exitCode,
                    'duration_ms' => $durationMs,
                ]
            );
        } catch (\Throwable) {
            // Ignore audit fail
        }

        return back()->with([
            'debugger_ran' => true,
            'debugger_command' => 'php artisan ' . $commandToRun . (! empty($params) ? ' ' . json_encode($params) : ''),
            'debugger_output' => $output,
            'debugger_exit_code' => $exitCode,
            'debugger_duration' => $durationMs,
        ]);
    }
}
