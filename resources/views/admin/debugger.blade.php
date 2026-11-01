@extends('admin.layout')
@section('title', 'Debugger & Server Console')
@section('admin')
<div class="page-head">
  <div>
    <h1>🛠️ Debugger Server &amp; Web Artisan Console</h1>
    <p>Jalankan migrasi database, seeder produksi, pembersihan cache, dan diagnostik server langsung dari browser tanpa perlu akses terminal SSH di hosting.</p>
  </div>
</div>

<div class="admin-layout">
  {{-- KOLOM UTAMA --}}
  <div class="admin-main">

    {{-- KELUARAN TERMINAL (JIKA ADA PERINTAH YANG BARU DIJALANKAN) --}}
    @if(session('debugger_ran'))
      <div class="card" style="border-color:var(--primary);background:var(--bg-card);margin-bottom:20px">
        <div class="card-header" style="padding-bottom:12px;border-bottom:1px solid var(--border)">
          <div style="display:flex;align-items:center;gap:10px">
            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:{{ session('debugger_exit_code') === 0 ? '#10b981' : '#ef4444' }}"></span>
            <div>
              <h2 class="card-title" style="font-size:15px;font-family:var(--font-mono, monospace)">{{ session('debugger_command') }}</h2>
              <div class="muted" style="font-size:12px;margin-top:2px">
                Waktu Eksekusi: <strong>{{ session('debugger_duration') }} ms</strong> · Exit Code: <strong>{{ session('debugger_exit_code') }}</strong>
              </div>
            </div>
          </div>
          <div>
            @if(session('debugger_exit_code') === 0)
              <x-badge variant="success">Berhasil Dieksekusi</x-badge>
            @else
              <x-badge variant="danger">Gagal / Error</x-badge>
            @endif
          </div>
        </div>

        <div style="position:relative;margin-top:14px">
          <pre id="terminal-output" style="background:#07090e;color:#f1f5f9;padding:16px;border-radius:8px;font-family:var(--font-mono, monospace);font-size:12.5px;line-height:1.5;overflow-x:auto;max-height:380px;white-space:pre-wrap;border:1px solid rgba(255,255,255,0.08)">{{ session('debugger_output') }}</pre>
          <button
            type="button"
            class="btn btn-ghost btn-sm"
            onclick="navigator.clipboard.writeText(document.getElementById('terminal-output').innerText); alert('Output terminal berhasil disalin!')"
            style="position:absolute;top:10px;right:10px;background:rgba(255,255,255,0.1);font-size:11px"
          >
            📋 Salin Log
          </button>
        </div>
      </div>
    @endif

    {{-- KARTU TOMBOL CEPAT ARTISAN (1-KLIK) --}}
    <div class="card" style="margin-bottom:20px">
      <div class="card-header">
        <div>
          <h2 class="card-title">⚡ Aksi Cepat Server &amp; Database (1-Klik)</h2>
          <p class="muted" style="margin:4px 0 0;font-size:13px">
            Klik tombol di bawah untuk menjalankan tugas pemeliharaan kritis secara instan tanpa perlu mengetik perintah secara manual.
          </p>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(260px, 1fr));gap:14px;margin-top:16px">
        {{-- Migrasi --}}
        <div style="background:var(--bg-elevated);border:1px solid var(--border);border-radius:8px;padding:14px">
          <div style="font-weight:600;font-size:14px;display:flex;align-items:center;gap:6px">
            <span>🚀</span> Migrasi Skema Database
          </div>
          <p class="muted" style="font-size:12px;margin:6px 0 12px;min-height:36px">
            Menjalankan seluruh file migrasi untuk membuat atau memperbarui tabel database.
          </p>
          <div style="display:flex;gap:8px">
            <form method="post" action="/admin/debugger/run" style="flex:1">
              @csrf
              <input type="hidden" name="action" value="migrate">
              <button class="btn btn-primary btn-sm" type="submit" style="width:100%" onclick="return confirm('Jalankan php artisan migrate --force di database?')">
                Jalankan Migrasi
              </button>
            </form>
            <form method="post" action="/admin/debugger/run">
              @csrf
              <input type="hidden" name="action" value="migrate:status">
              <button class="btn btn-ghost btn-sm" type="submit" title="Cek status migrasi">
                Status
              </button>
            </form>
          </div>
        </div>

        {{-- Seeder Hosting --}}
        <div style="background:var(--bg-elevated);border:1px solid var(--border);border-radius:8px;padding:14px">
          <div style="font-weight:600;font-size:14px;display:flex;align-items:center;gap:6px">
            <span>🌱</span> Seeder Hosting (1 Super Admin)
          </div>
          <p class="muted" style="font-size:12px;margin:6px 0 12px;min-height:36px">
            Men-seed 1 Super Admin resmi (athallahrich@gmail.com) &amp; 4 app ekosistem tanpa user dummy.
          </p>
          <form method="post" action="/admin/debugger/run">
            @csrf
            <input type="hidden" name="action" value="seed:hosting">
            <button class="btn btn-secondary btn-sm" type="submit" style="width:100%" onclick="return confirm('Jalankan HostingProductionSeeder? Ini hanya akan membuat 1 akun Super Admin resmi.')">
              Seed Hosting (Produksi)
            </button>
          </form>
        </div>

        {{-- Bersihkan Seluruh Cache --}}
        <div style="background:var(--bg-elevated);border:1px solid var(--border);border-radius:8px;padding:14px">
          <div style="font-weight:600;font-size:14px;display:flex;align-items:center;gap:6px">
            <span>🧹</span> Bersihkan Seluruh Cache
          </div>
          <p class="muted" style="font-size:12px;margin:6px 0 12px;min-height:36px">
            Menghapus cache config, route, view, dan compiled class agar perubahan file langsung aktif.
          </p>
          <form method="post" action="/admin/debugger/run">
            @csrf
            <input type="hidden" name="action" value="optimize:clear">
            <button class="btn btn-ghost btn-sm" type="submit" style="width:100%">
              Bersihkan Cache (Clear)
            </button>
          </form>
        </div>

        {{-- Optimasi Produksi --}}
        <div style="background:var(--bg-elevated);border:1px solid var(--border);border-radius:8px;padding:14px">
          <div style="font-weight:600;font-size:14px;display:flex;align-items:center;gap:6px">
            <span>⚡</span> Optimasi Cache Produksi
          </div>
          <p class="muted" style="font-size:12px;margin:6px 0 12px;min-height:36px">
            Meng-compile config &amp; routes ke memori cache untuk kecepatan loading maksimal di server.
          </p>
          <form method="post" action="/admin/debugger/run">
            @csrf
            <input type="hidden" name="action" value="optimize">
            <button class="btn btn-ghost btn-sm" type="submit" style="width:100%">
              Optimasi (Cache All)
            </button>
          </form>
        </div>

        {{-- Storage Link --}}
        <div style="background:var(--bg-elevated);border:1px solid var(--border);border-radius:8px;padding:14px">
          <div style="font-weight:600;font-size:14px;display:flex;align-items:center;gap:6px">
            <span>🔗</span> Storage Symlink
          </div>
          <p class="muted" style="font-size:12px;margin:6px 0 12px;min-height:36px">
            Menghubungkan folder penyimpanan file internal ke folder publik agar gambar bisa dibuka.
          </p>
          <form method="post" action="/admin/debugger/run">
            @csrf
            <input type="hidden" name="action" value="storage:link">
            <button class="btn btn-ghost btn-sm" type="submit" style="width:100%">
              Buat / Perbarui Symlink
            </button>
          </form>
        </div>

        {{-- Kirim Outbox Webhook --}}
        <div style="background:var(--bg-elevated);border:1px solid var(--border);border-radius:8px;padding:14px">
          <div style="font-weight:600;font-size:14px;display:flex;align-items:center;gap:6px">
            <span>📨</span> Kirim Webhook Outbox
          </div>
          <p class="muted" style="font-size:12px;margin:6px 0 12px;min-height:36px">
            Mengeksekusi antrean event webhook yang belum terkirim ke aplikasi klien secara manual.
          </p>
          <form method="post" action="/admin/debugger/run">
            @csrf
            <input type="hidden" name="action" value="iam:outbox-work">
            <button class="btn btn-ghost btn-sm" type="submit" style="width:100%">
              Kirim Antrean Outbox
            </button>
          </form>
        </div>
      </div>
    </div>

    {{-- KARTU PERINTAH ARTISAN KUSTOM --}}
    <div class="card" style="margin-bottom:20px">
      <div class="card-header">
        <div>
          <h2 class="card-title">💻 Terminal Artisan Kustom (Whitelist Terproteksi)</h2>
          <p class="muted" style="margin:4px 0 0;font-size:13px">
            Masukkan perintah artisan yang diizinkan (misal: <code>migrate:status</code>, <code>route:list</code>, <code>config:cache</code>).
          </p>
        </div>
      </div>

      <form method="post" action="/admin/debugger/run" style="margin-top:14px">
        @csrf
        <input type="hidden" name="action" value="custom">
        <div style="display:flex;gap:10px;align-items:center">
          <span style="font-family:var(--font-mono, monospace);font-weight:600;color:var(--primary);padding:0 4px">php artisan</span>
          <input
            type="text"
            name="custom_command"
            placeholder="migrate:status, optimize:clear, db:seed --class=HostingProductionSeeder"
            style="flex:1;font-family:var(--font-mono, monospace);margin:0"
            required
          >
          <button type="submit" class="btn btn-primary">
            Jalankan Perintah
          </button>
        </div>
        <div class="muted" style="font-size:11px;margin-top:6px">
          * Hanya namespace yang aman diizinkan: <code>migrate</code>, <code>db:seed</code>, <code>cache:</code>, <code>config:</code>, <code>route:</code>, <code>view:</code>, <code>optimize</code>, <code>storage:link</code>, <code>iam:</code>.
        </div>
      </form>
    </div>

    {{-- KARTU DIAGNOSTIK SERVER & HAK AKSES FOLDER --}}
    <div class="card">
      <div class="card-header">
        <div>
          <h2 class="card-title">📊 Status Diagnostik Server &amp; Database</h2>
          <p class="muted" style="margin:4px 0 0;font-size:13px">
            Pastikan seluruh folder storage dapat ditulis (*writable*) dan ekstensi PHP penting dalam status aktif.
          </p>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:16px">
        {{-- Kolom Database & Server Info --}}
        <div>
          <h3 style="font-size:13.5px;font-weight:600;margin-bottom:10px;color:var(--text)">Koneksi &amp; Lingkungan Server</h3>
          <div class="data-table-wrap">
            <table class="data-table">
              <tbody>
                <tr>
                  <td class="muted">Status Database</td>
                  <td>
                    @if(str_contains($dbStatus, 'online'))
                      <x-badge variant="success">Online (Terhubung)</x-badge>
                    @else
                      <x-badge variant="danger">{{ $dbStatus }}</x-badge>
                    @endif
                  </td>
                </tr>
                <tr>
                  <td class="muted">Database / Host</td>
                  <td style="font-family:var(--font-mono, monospace);font-size:12px">{{ $dbName }} ({{ $dbHost }})</td>
                </tr>
                <tr>
                  <td class="muted">Total Tabel DB</td>
                  <td><strong>{{ $tableCount }}</strong> Tabel</td>
                </tr>
                <tr>
                  <td class="muted">Status Migrasi</td>
                  <td>
                    <span style="color:#10b981;font-weight:600">{{ $migrationStats['ran'] }} selesai</span>
                    @if($migrationStats['pending'] > 0)
                      · <span style="color:#f59e0b;font-weight:600">({{ $migrationStats['pending'] }} pending)</span>
                    @else
                      · <span class="muted">(Semua sinkron)</span>
                    @endif
                  </td>
                </tr>
                <tr>
                  <td class="muted">Versi PHP &amp; Laravel</td>
                  <td style="font-family:var(--font-mono, monospace);font-size:12px">PHP {{ $serverInfo['php_version'] }} · Laravel {{ $serverInfo['laravel_version'] }}</td>
                </tr>
                <tr>
                  <td class="muted">Environment (APP_ENV)</td>
                  <td>
                    <x-badge variant="{{ $serverInfo['app_env'] === 'production' ? 'success' : 'info' }}">
                      {{ $serverInfo['app_env'] }}
                    </x-badge>
                    <span class="muted" style="font-size:11px">(Debug: {{ $serverInfo['app_debug'] }})</span>
                  </td>
                </tr>
                <tr>
                  <td class="muted">Batas Memori / Waktu</td>
                  <td style="font-size:12px">{{ $serverInfo['memory_limit'] }} · {{ $serverInfo['max_execution_time'] }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        {{-- Kolom Hak Akses Folder & Ekstensi --}}
        <div>
          <h3 style="font-size:13.5px;font-weight:600;margin-bottom:10px;color:var(--text)">Hak Akses Folder Writable</h3>
          <div class="data-table-wrap" style="margin-bottom:16px">
            <table class="data-table">
              <tbody>
                @foreach($folderChecks as $folderLabel => $check)
                  <tr>
                    <td style="font-family:var(--font-mono, monospace);font-size:11.5px">{{ $folderLabel }}</td>
                    <td style="text-align:right">
                      @if($check['writable'])
                        <x-badge variant="success">Writable (OK)</x-badge>
                      @else
                        <x-badge variant="danger">Read Only (Kunci!)</x-badge>
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          <h3 style="font-size:13.5px;font-weight:600;margin-bottom:10px;color:var(--text)">Ekstensi PHP Wajib</h3>
          <div style="display:flex;flex-wrap:wrap;gap:6px">
            @foreach($requiredExtensions as $extName => $isInstalled)
              <span style="font-family:var(--font-mono, monospace);font-size:11px;padding:3px 8px;border-radius:4px;border:1px solid var(--border);background:var(--bg-elevated);color:{{ $isInstalled ? '#10b981' : '#ef4444' }}">
                {{ $isInstalled ? '✓' : '✗' }} {{ $extName }}
              </span>
            @endforeach
          </div>
        </div>
      </div>
    </div>

  </div>

  {{-- KOLOM SAMPING (ASIDE PANDUAN AWAM) --}}
  <aside class="admin-aside">
    <x-help title="💡 Apa Itu Web Debugger &amp; Console?">
      <p>
        Di lingkungan hosting (terutama <em>Shared Hosting</em> atau <em>cPanel</em>), kita sering kali <strong>tidak diberikan akses terminal SSH</strong> untuk mengetik perintah seperti <code>php artisan migrate</code>.
      </p>
      <p>
        Menu <strong>Web Debugger</strong> ini hadir sebagai jembatan darurat yang aman agar Anda dapat menjalankan tugas pemeliharaan server hanya dengan sekali klik dari browser!
      </p>
    </x-help>

    <x-help title="🔍 Perumpamaan Istilah Teknis">
      <ul style="padding-left:18px;margin:0;font-size:12.5px;line-height:1.6">
        <li><strong>Migrate</strong>: Ibarat <em>tukang bangunan</em> yang menata denah ruangan baru di lemari database Anda. Wajib dijalankan saat deploy pertama kali.</li>
        <li><strong>Seeder Hosting</strong>: Ibarat <em>mengisi perabotan pertama</em> dan mencetak kartu akses utama (1 Super Admin resmi) tanpa membawa data-data uji coba.</li>
        <li><strong>Optimize Clear</strong>: Ibarat <em>menyeka papan tulis</em> agar tulisan lama terhapus bersih dan konfigurasi baru langsung terlihat.</li>
        <li><strong>Storage Link</strong>: Ibarat <em>membangun jembatan penyeberangan</em> dari gudang rahasia ke lobi publik agar file/foto yang diupload bisa dibuka pengunjung.</li>
      </ul>
    </x-help>

    <x-help title="🛡️ Prinsip Keamanan Super Admin">
      <p>
        Fitur ini <strong>terkunci rapat</strong> dan hanya dapat diakses oleh akun bertanda <code>is_super_admin = true</code>.
      </p>
      <p>
        Setiap aksi yang Anda jalankan di halaman ini otomatis dicatat ke dalam <strong>Audit Log Keamanan</strong> lengkap dengan IP Address dan waktu eksekusi demi transparansi penuh.
      </p>
    </x-help>
  </aside>
</div>
@endsection
