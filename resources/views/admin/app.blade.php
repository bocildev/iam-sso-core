@extends('admin.layout')
@section('title', $app->name)
@section('admin')
<div class="page-head">
  <div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <h1>{{ $app->name }}</h1>
      <x-badge :variant="$app->status">{{ $app->status }}</x-badge>
      <x-badge :variant="$app->categoryVariant()" :dot="false">{{ $app->categoryLabel() }}</x-badge>
      @if($app->isPublicClient())
        <x-badge variant="info" :dot="false">Frontend Web (PKCE S256)</x-badge>
      @else
        <x-badge variant="neutral" :dot="false">Backend Server (Confidential)</x-badge>
      @endif
    </div>
    <p>Client ID: <code>{{ $app->client_id }}</code> · Slug: <code>{{ $app->slug }}</code></p>
  </div>
  <a href="/admin/apps" class="btn btn-sm btn-ghost">&larr; Kembali ke Daftar Aplikasi</a>
</div>

<div class="admin-layout">
  <div class="admin-main">

    {{-- KOTAK PANDUAN CEPAT COPY-PASTE KE .ENV --}}
    <div class="card" style="border:1px solid var(--accent);background:linear-gradient(180deg, var(--bg-card) 0%, var(--bg-subtle) 100%)">
      <div class="card-header">
        <div>
          <h2 class="card-title" style="font-size:16px;display:flex;align-items:center;gap:8px">
            <span>🚀 Cara Menyambungkan ke Kodingan Aplikasi Anda</span>
          </h2>
          <p class="muted" style="margin:4px 0 0;font-size:13px">
            Cukup salin baris konfigurasi di bawah ini dan tempelkan ke file <code>.env</code> di dalam folder proyek aplikasi Anda.
          </p>
        </div>
        <button type="button" class="btn btn-sm" id="btn-copy-env" data-copy="IAM_BASE_URL={{ config('iam.issuer') }}&#10;IAM_CLIENT_ID={{ $app->client_id }}{{ !$app->isPublicClient() ? '&#10;IAM_CLIENT_SECRET='.(session('secret') ?? 'MASUKKAN_CLIENT_SECRET_ANDA') : '' }}">
          📋 Salin Baris .env
        </button>
      </div>

      <div style="position:relative;margin:10px 0">
        <pre style="background:var(--bg-main);border:1px solid var(--border);border-radius:8px;padding:14px;font-family:var(--font-mono);font-size:12.5px;color:var(--text-highlight);line-height:1.6;overflow-x:auto;margin:0"># File: .env pada proyek {{ $app->name }}
IAM_BASE_URL={{ config('iam.issuer') }}
IAM_CLIENT_ID={{ $app->client_id }}
@unless($app->isPublicClient())
# Client Secret (Kata sandi rahasia server - JANGAN simpan di kodingan browser/frontend!):
IAM_CLIENT_SECRET={{ session('secret') ?? 'sec_xxxxxxxxxxxxxxxx (tersimpan aman di server)' }}
@endunless</pre>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:12px;margin-top:12px;padding-top:12px;border-top:1px solid var(--border);font-size:12.5px">
        <div>
          <strong style="color:var(--text-main)">1. Buka File .env</strong>
          <p class="muted" style="margin:4px 0 0">Buka file <code>.env</code> di folder proyek aplikasi Anda (misal di Nuxt atau Laravel).</p>
        </div>
        <div>
          <strong style="color:var(--text-main)">2. Tempelkan Baris di Atas</strong>
          <p class="muted" style="margin:4px 0 0">Paste baris <code>IAM_BASE_URL</code> dan <code>IAM_CLIENT_ID</code> tersebut.</p>
        </div>
        <div>
          <strong style="color:var(--text-main)">3. Selesai!</strong>
          <p class="muted" style="margin:4px 0 0">
            @if($app->isPublicClient())
              Aplikasi frontend Anda siap menggunakan helper <code>useIamSso.ts</code> tanpa rahasia.
            @else
              Aplikasi server Anda siap memverifikasi login lewat verifier backend.
            @endif
          </p>
        </div>
      </div>
    </div>

    {{-- FORMULIR PENGATURAN APLIKASI (DIBAGI DASAR VS LANJUTAN) --}}
    <div class="card">
      <div class="card-header">
        <div>
          <h2 class="card-title">Pengaturan Alamat &amp; Integrasi</h2>
          <p class="muted" style="margin:4px 0 0;font-size:13px">Atur alamat redirect dan opsi teknis aplikasi.</p>
        </div>
      </div>

      <form method="post" action="/admin/apps/{{ $app->id }}">
        @csrf

        {{-- BAGIAN 1: PENGATURAN UTAMA --}}
        <div style="margin-bottom:16px">
          <div style="font-weight:600;font-size:13.5px;color:var(--text-main);margin-bottom:8px">
            🟢 Pengaturan Utama (Wajib)
          </div>

          <div class="grid-2">
            <div>
              <label for="f-name">Nama Aplikasi</label>
              <input id="f-name" name="name" value="{{ $app->name }}" required placeholder="Nama aplikasi">
              <span class="muted" style="font-size:12px;display:block;margin-top:4px">Nama yang dilihat pengunjung di layar login.</span>
            </div>
            <div>
              <label for="f-category">Kategori Aplikasi</label>
              <select id="f-category" name="category">
                <option value="public_auth" @selected($app->category==='public_auth')>👥 Publik dengan Login (SaaS / App)</option>
                <option value="public_no_auth" @selected($app->category==='public_no_auth')>🌐 Publik Tanpa Login (Showcase / Portofolio)</option>
                <option value="internal_admin" @selected($app->category==='internal_admin')>🔒 Admin Backoffice (Khusus SSO)</option>
              </select>
              <span class="muted" style="font-size:12px;display:block;margin-top:4px">Menentukan apakah aplikasi butuh login pengguna atau tidak.</span>
            </div>
          </div>

          <div class="grid-2" style="margin-top:12px">
            <div>
              <label for="f-status">Status Operasional</label>
              <select id="f-status" name="status">
                <option value="active" @selected($app->status==='active')>Aktif (Bisa Diakses)</option>
                <option value="suspended" @selected($app->status==='suspended')>Ditangguhkan (Akses Ditolak)</option>
              </select>
            </div>
            <div style="display:flex;align-items:center;padding-top:20px">
              <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;margin:0">
                <input type="checkbox" name="allow_google_login" value="1" {{ $app->allow_google_login ? 'checked' : '' }} style="width:auto;margin:0">
                <span style="font-size:13px;font-weight:600">Izinkan Login dengan Akun Google (Social Login)</span>
              </label>
            </div>
          </div>

          <div style="margin-top:12px">
            <label for="f-redirects">Redirect URI (Pintu Masuk Kembali Setelah Login)</label>
            <textarea id="f-redirects" name="redirect_uris" rows="2" required placeholder="http://localhost:3000/auth/callback" style="font-family:var(--font-mono);font-size:12.5px">{{ implode("\n", $app->redirect_uris ?? []) }}</textarea>
            <span class="muted" style="font-size:12px;display:block;margin-top:4px">
              Alamat URL di aplikasi Anda yang menyambut pengunjung kembali setelah login di IAM berhasil. (Satu URL per baris).
            </span>
          </div>
        </div>

        {{-- BAGIAN 2: PENGATURAN LANJUTAN (OPSIONAL) --}}
        <div style="border-top:1px solid var(--border);padding-top:14px;margin-top:16px">
          <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer" id="toggle-advanced-settings">
            <div>
              <div style="font-weight:600;font-size:13.5px;color:var(--text-main)">
                ⚙️ Pengaturan Lanjutan (Opsional)
              </div>
              <p class="muted" style="margin:2px 0 0;font-size:12px">
                CORS, Post-Logout, Webhook, dan Back-channel. Biarkan kosong jika aplikasi Anda belum memerlukannya.
              </p>
            </div>
            <button type="button" class="btn btn-sm btn-ghost" id="btn-advanced-toggle" style="font-size:12px">
              + Buka Opsi Lanjutan
            </button>
          </div>

          <div id="advanced-settings-box" style="display:none;margin-top:14px;padding:14px;border-radius:8px;background:var(--bg-subtle);border:1px solid var(--border)">
            <label for="f-logout-uris">Post-Logout Redirect URI (Alamat Setelah Logout)</label>
            <textarea id="f-logout-uris" name="post_logout_redirect_uris" rows="2" placeholder="http://localhost:3000/" style="font-family:var(--font-mono);font-size:12px">{{ implode("\n", $app->post_logout_redirect_uris ?? []) }}</textarea>
            <span class="muted" style="font-size:11.5px;display:block;margin-top:2px;margin-bottom:12px">Alamat halaman tujuan ketika user mengklik tombol Keluar / Logout.</span>

            <label for="f-origins">Allowed CORS Origins (Khusus Aplikasi Web Browser)</label>
            <textarea id="f-origins" name="allowed_origins" rows="2" placeholder="http://localhost:3000" style="font-family:var(--font-mono);font-size:12px">{{ implode("\n", $app->allowed_origins ?? []) }}</textarea>
            <span class="muted" style="font-size:11.5px;display:block;margin-top:2px;margin-bottom:12px">Domain frontend yang diizinkan memanggil API IAM langsung lewat JavaScript browser.</span>

            <label for="f-bclogout">Back-Channel Logout URI</label>
            <input id="f-bclogout" name="backchannel_logout_uri" value="{{ $app->backchannel_logout_uri }}" placeholder="https://app.example.com/api/oidc/backchannel-logout">
            <span class="muted" style="font-size:11.5px;display:block;margin-top:2px;margin-bottom:12px">Pemberitahuan logout di belakang layar dari server IAM ke server aplikasi Anda.</span>

            <label for="f-webhook">Webhook URL (Notifikasi Event Otomatis)</label>
            <input id="f-webhook" name="webhook_url" value="{{ $app->webhook_url }}" placeholder="https://app.example.com/api/iam/webhook">
            <span class="muted" style="font-size:11.5px;display:block;margin-top:2px;margin-bottom:12px">IAM akan mengirimkan data otomatis saat ada user yang berubah, dinonaktifkan, atau role-nya berganti.</span>

            <label for="f-mode">Mode Autentikasi</label>
            <select id="f-mode" name="auth_mode" style="max-width:300px">
              <option value="oidc" @selected($app->auth_mode==='oidc')>OIDC (Standar Modern Terpusat)</option>
              <option value="legacy" @selected($app->auth_mode==='legacy')>Legacy (Sistem Lama)</option>
              <option value="both" @selected($app->auth_mode==='both')>Both (Masa Transisi Migrasi)</option>
            </select>
          </div>
        </div>

        <div style="margin-top:16px">
          <button type="submit">Simpan Perubahan Pengaturan</button>
        </div>
      </form>
    </div>

    {{-- KELOLA ROLE & PERMISSION --}}
    <div class="card">
      <div class="card-header">
        <div>
          <h3 class="card-title">Role &amp; Permission (Jabatan &amp; Hak Izin)</h3>
          <p class="muted" style="margin:4px 0 0;font-size:13px">Atur tingkatan jabatan khusus untuk aplikasi ini (misal: Admin, Editor, Viewer) beserta izin spesifiknya.</p>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Nama &amp; Kode Role</th><th>Daftar Hak Izin (Permissions)</th></tr></thead>
          <tbody>
            @forelse($app->roles as $r)
              <tr>
                <td><strong>{{ $r->name }}</strong> <code>{{ $r->key }}</code></td>
                <td>
                  @forelse($r->permissions as $p)
                    <code style="margin:2px">{{ $p->key }}</code>
                  @empty
                    <span class="muted">Belum ada izin spesifik (akses default).</span>
                  @endforelse
                </td>
              </tr>
            @empty
              <tr><td colspan="2" class="muted">Belum ada role khusus untuk aplikasi ini.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <form method="post" action="/admin/apps/{{ $app->id }}/roles" style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border)">
        @csrf
        <div class="grid-2">
          <div>
            <label for="r-key">Kode Role (Huruf kecil, tanpa spasi)</label>
            <input id="r-key" name="key" required placeholder="misal: editor atau finance_manager">
          </div>
          <div>
            <label for="r-name">Nama Jabatan yang Ramah</label>
            <input id="r-name" name="name" required placeholder="misal: Editor Konten atau Manajer Keuangan">
          </div>
        </div>
        <label for="r-perms">Daftar Izin (format <code>resource.aksi</code>, pisahkan dengan koma)</label>
        <input id="r-perms" name="permissions" placeholder="misal: project.read, project.write, invoice.create">
        <button type="submit" class="btn-sm">Tambah Role Baru</button>
      </form>
    </div>

    {{-- KELOLA API KEY MESIN --}}
    <div class="card">
      <div class="card-header">
        <div>
          <h3 class="card-title">API Key (Komunikasi Server-ke-Server)</h3>
          <p class="muted" style="margin:4px 0 0;font-size:13px">Kunci khusus untuk script cron-job atau server backend yang ingin mengambil data IAM tanpa melibatkan login pengguna.</p>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Nama Script / Mesin</th><th>Prefix Key</th><th>Izin (Scope)</th><th>Status</th><th></th></tr></thead>
          <tbody>
            @forelse($keys as $k)
              <tr>
                <td><strong>{{ $k->name }}</strong></td>
                <td><code>{{ $k->prefix }}…</code></td>
                <td>
                  @foreach($k->scopes as $sc)
                    <code style="margin:1px">{{ $sc }}</code>
                  @endforeach
                </td>
                <td><x-badge :variant="$k->status">{{ $k->status }}</x-badge></td>
                <td style="text-align:right">
                  @if($k->status === 'active')
                    <form method="post" action="/admin/api-keys/{{ $k->id }}/revoke" onsubmit="return confirm('Cabut API key ini? Server yang memakainya tidak akan bisa mengakses IAM lagi.')">
                      @csrf
                      <button class="btn-danger btn btn-sm" type="submit">Cabut Key</button>
                    </form>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="5" class="muted">Belum ada API key mesin.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <form method="post" action="/admin/apps/{{ $app->id }}/api-keys" style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border)">
        @csrf
        <div class="grid-2">
          <div>
            <label for="k-name">Nama Server / Script Pemakai</label>
            <input id="k-name" name="name" required placeholder="misal: Backend Worker Sinkronisasi">
          </div>
          <div>
            <label for="k-scopes">Izin Scope (pisahkan dengan koma)</label>
            <input id="k-scopes" name="scopes" placeholder="misal: iam:users.read, portfolio:read" required>
          </div>
        </div>
        <button type="submit" class="btn-sm">Buat API Key Mesin Baru</button>
      </form>
    </div>

    {{-- RIWAYAT OUTBOX WEBHOOK --}}
    <div class="card">
      <div class="card-header">
        <div>
          <h3 class="card-title">Riwayat Pengiriman Pesan Keluar (Outbox Webhook)</h3>
          <p class="muted" style="margin:4px 0 0;font-size:13px">Catatan pesan otomatis yang dikirimkan IAM ke server aplikasi Anda setiap ada perubahan akun.</p>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Waktu</th><th>Nama Kejadian</th><th>Status</th><th>Respon Server</th><th>Percobaan</th></tr></thead>
          <tbody>
            @forelse($outbox as $ev)
              <tr>
                <td style="font-family:var(--font-mono);font-size:12px">{{ $ev->created_at }}</td>
                <td><code>{{ $ev->event_type }}</code></td>
                <td><x-badge :variant="$ev->status">{{ $ev->status }}</x-badge></td>
                <td>{{ $ev->last_http_status ?? '-' }}</td>
                <td>{{ $ev->attempts }} kali</td>
              </tr>
            @empty
              <tr><td colspan="5" class="muted">Belum ada event pengiriman webhook untuk aplikasi ini.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- KOLOM SAMPING (ASIDE / PANDUAN) --}}
  <aside class="admin-aside">
    <x-help key="app" title="Panduan: Pengaturan Aplikasi">
      <p>Halaman ini adalah pusat identitas aplikasi Anda di IAM.</p>
      <ul>
        <li><strong>Client ID</strong>: Nama pengenal aplikasi Anda di IAM. Wajib dicatat di file <code>.env</code> aplikasi Anda.</li>
        <li><strong>Redirect URI</strong>: Alamat gerbang kembali setelah login. Jika Anda mengganti port lokal (misal dari 3000 ke 3002), perbarui juga alamatnya di sini!</li>
        <li><strong>Public vs Confidential</strong>:
          <ul>
            <li><em>Public</em>: Tidak punya password rahasia (aman untuk browser/Nuxt/Vue).</li>
            <li><em>Confidential</em>: Punya kata sandi rahasia (Client Secret) yang disimpan di file <code>.env</code> server backend.</li>
          </ul>
        </li>
      </ul>
      <p style="margin-top:10px">Mau tahu lebih banyak istilah? Buka <a href="/admin/glossary">Kamus Istilah</a>.</p>
    </x-help>

    <div class="card">
      <div class="card-header" style="margin-bottom:8px">
        <h3 class="card-title" style="font-size:14.5px">Ringkasan Klien</h3>
      </div>
      <div style="display:flex;flex-direction:column;gap:10px;font-size:13px">
        <div>
          <span class="muted" style="font-size:11.5px;display:block">Kategori Aplikasi</span>
          <x-badge :variant="$app->categoryVariant()" :dot="false">{{ $app->categoryLabel() }}</x-badge>
        </div>
        <div>
          <span class="muted" style="font-size:11.5px;display:block">Client ID</span>
          <code class="text-highlight">{{ $app->client_id }}</code>
        </div>
        <div>
          <span class="muted" style="font-size:11.5px;display:block">Tipe Klien</span>
          <span style="font-weight:600">
            {{ $app->isPublicClient() ? '🌐 Frontend (Public / PKCE)' : '⚙️ Backend (Confidential)' }}
          </span>
        </div>
        <div>
          <span class="muted" style="font-size:11.5px;display:block">Mode Autentikasi</span>
          <x-badge :variant="$app->auth_mode" :dot="false">{{ strtoupper($app->auth_mode) }}</x-badge>
        </div>
        <div>
          <span class="muted" style="font-size:11.5px;display:block">Status Operasional</span>
          <x-badge :variant="$app->status">{{ $app->status }}</x-badge>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header" style="margin-bottom:8px">
        <h3 class="card-title" style="font-size:14.5px">Kredensial Rahasia &amp; Rotasi</h3>
      </div>
      <p class="muted" style="font-size:12px;margin:0 0 12px;line-height:1.5">
        Jika Anda merasa kredensial aplikasi dicurigai bocor, Anda bisa menerbitkan kunci rahasia baru di bawah ini.
      </p>
      <div style="display:flex;flex-direction:column;gap:8px">
        @unless($app->isPublicClient())
          <form method="post" action="/admin/apps/{{ $app->id }}/rotate-secret" style="margin:0" onsubmit="return confirm('Rotasi Client Secret baru? Anda harus segera memperbarui nilai IAM_CLIENT_SECRET di file .env aplikasi server Anda.')">
            @csrf
            <button class="btn-danger btn btn-sm" type="submit" style="width:100%;justify-content:center">
              🔄 Rotasi Client Secret
            </button>
          </form>
        @endunless
        <form method="post" action="/admin/apps/{{ $app->id }}/rotate-webhook-secret" style="margin:0" onsubmit="return confirm('Rotasi Webhook Secret baru?')">
          @csrf
          <button class="btn-ghost btn btn-sm" type="submit" style="width:100%;justify-content:center">
            🔄 Rotasi Webhook Secret
          </button>
        </form>
      </div>
    </div>
  </aside>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const toggleBtn = document.getElementById('btn-advanced-toggle');
  const toggleHeader = document.getElementById('toggle-advanced-settings');
  const advancedBox = document.getElementById('advanced-settings-box');

  function toggleAdvanced() {
    if (advancedBox.style.display === 'none') {
      advancedBox.style.display = 'block';
      toggleBtn.textContent = '- Tutup Opsi Lanjutan';
    } else {
      advancedBox.style.display = 'none';
      toggleBtn.textContent = '+ Buka Opsi Lanjutan';
    }
  }

  if (toggleBtn) toggleBtn.addEventListener('click', toggleAdvanced);
  if (toggleHeader) {
    toggleHeader.addEventListener('click', function(e) {
      if (e.target !== toggleBtn) toggleAdvanced();
    });
  }
});
</script>
@endsection
