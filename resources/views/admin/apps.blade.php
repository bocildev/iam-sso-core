@extends('admin.layout')
@section('title', 'Registry Aplikasi')
@section('admin')
<div class="page-head">
  <div>
    <h1>Registry Aplikasi Klien</h1>
    <p>Kelola ekosistem aplikasi: Showcase Portofolio Publik, Aplikasi Pengguna dengan Login Google, dan Backoffice Admin.</p>
  </div>
</div>

<div class="admin-layout">
  <div class="admin-main">
    {{-- Tabel Daftar Aplikasi --}}
    <div class="card">
      <div class="card-header">
        <div>
          <h2 class="card-title">Daftar Aplikasi Terdaftar ({{ $apps->count() }})</h2>
          <p class="muted" style="margin:4px 0 0;font-size:13px">Aplikasi terbagi dalam 3 kategori: Publik Tanpa Login, Publik dengan Login Google, dan Admin Backoffice.</p>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Nama Aplikasi</th>
              <th>Kategori Aplikasi</th>
              <th>Client ID</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @forelse($apps as $a)
              <tr>
                <td>
                  <a href="/admin/apps/{{ $a->id }}" style="font-weight:600;font-size:14px">{{ $a->name }}</a>
                  <div class="muted" style="font-size:12px">slug: <code>{{ $a->slug }}</code></div>
                </td>
                <td>
                  <x-badge :variant="$a->categoryVariant()" :dot="false">{{ $a->categoryLabel() }}</x-badge>
                </td>
                <td><code class="text-highlight">{{ $a->client_id }}</code></td>
                <td><x-badge :variant="$a->status">{{ $a->status }}</x-badge></td>
                <td style="text-align:right">
                  <div style="display:inline-flex;align-items:center;gap:6px">
                    <a href="/admin/apps/{{ $a->id }}" class="btn btn-sm btn-ghost" style="display:inline-flex;align-items:center;gap:4px">
                      <span>Konfigurasi &amp; .env</span>
                      <span aria-hidden="true">&rarr;</span>
                    </a>
                    <form method="post" action="/admin/apps/{{ $a->id }}" style="margin:0;display:inline" onsubmit="return confirm('⚠️ PERINGATAN:\n\nApakah Anda yakin ingin MENGHAPUS aplikasi &quot;{{ $a->name }}&quot;?\n\nSeluruh token login, sesi, role, dan API key aplikasi ini akan ikut terhapus permanen.');">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-sm btn-danger" title="Hapus Aplikasi" style="padding:4px 8px;display:inline-flex;align-items:center">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <tr><td colspan="5" class="muted">Belum ada aplikasi terdaftar. Silakan daftarkan aplikasi baru di bawah ini.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- Form Daftarkan Aplikasi Baru --}}
    <div class="card" id="form-daftar-app">
      <div class="card-header">
        <div>
          <h3 class="card-title" style="font-size:16px">➕ Daftarkan Aplikasi Baru (Cepat &amp; Otomatis)</h3>
          <p class="muted" style="margin:4px 0 0;font-size:13px">Pilih kategori aplikasi Anda. Sistem akan otomatis mengatur kebutuhan login dan izin keamanannya.</p>
        </div>
      </div>

      {{-- Pemilih Kategori Aplikasi --}}
      <div style="margin-bottom:18px">
        <label style="font-weight:600;margin-bottom:8px;display:block">Langkah 1: Pilih Kategori &amp; Kebutuhan Login</label>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:12px">
          
          {{-- Option 1: Public with Auth (Default) --}}
          <label id="card-preset-public-auth" style="display:flex;flex-direction:column;gap:6px;padding:12px 14px;border-radius:8px;border:2px solid var(--accent);background:var(--bg-subtle);cursor:pointer;position:relative">
            <div style="display:flex;align-items:center;gap:8px">
              <input type="radio" name="app_preset" value="public_auth" checked style="margin:0;width:auto">
              <span style="font-weight:600;font-size:13px">👥 Publik dengan Login</span>
            </div>
            <p class="muted" style="font-size:11.5px;margin:0;line-height:1.4">
              <strong>Mochi Finance, ToonHub</strong>. Aplikasi interaktif tempat user menyimpan data sendiri. Mendukung <strong>Login Google (1-Klik)</strong> &amp; daftar akun mandiri.
            </p>
          </label>

          {{-- Option 2: Public No Auth (Showcase) --}}
          <label id="card-preset-public-no-auth" style="display:flex;flex-direction:column;gap:6px;padding:12px 14px;border-radius:8px;border:1px solid var(--border);background:var(--bg-subtle);cursor:pointer;position:relative">
            <div style="display:flex;align-items:center;gap:8px">
              <input type="radio" name="app_preset" value="public_no_auth" style="margin:0;width:auto">
              <span style="font-weight:600;font-size:13px">🌐 Publik Tanpa Login</span>
            </div>
            <p class="muted" style="font-size:11.5px;margin:0;line-height:1.4">
              <strong>Motion Portfolio, Landing Page</strong>. Bebas diakses publik tanpa login. Hanya butuh izin CORS &amp; API Key mesin untuk mengambil data karya.
            </p>
          </label>

          {{-- Option 3: Internal Admin / Backoffice --}}
          <label id="card-preset-internal-admin" style="display:flex;flex-direction:column;gap:6px;padding:12px 14px;border-radius:8px;border:1px solid var(--border);background:var(--bg-subtle);cursor:pointer;position:relative">
            <div style="display:flex;align-items:center;gap:8px">
              <input type="radio" name="app_preset" value="internal_admin" style="margin:0;width:auto">
              <span style="font-weight:600;font-size:13px">🔒 Admin / Backoffice</span>
            </div>
            <p class="muted" style="font-size:11.5px;margin:0;line-height:1.4">
              <strong>Mochi Admin, Portfolio Admin</strong>. Khusus Super Admin / Owner. Hanya login via SSO resmi / Password Sakti (Google publik dilarang).
            </p>
          </label>

        </div>
      </div>

      {{-- Formulir Data Aplikasi --}}
      <form method="post" action="/admin/apps" id="form-register">
        @csrf
        <input type="hidden" name="category" id="input-category" value="public_auth">
        <input type="hidden" name="confidential" id="input-confidential" value="0">
        <input type="hidden" name="allow_google_login" id="input-allow-google" value="1">

        <label style="font-weight:600;margin-bottom:8px;display:block">Langkah 2: Isi Nama &amp; Alamat Aplikasi</label>

        <div class="grid-2">
          <div>
            <label for="app-name">Nama Aplikasi</label>
            <input id="app-name" name="name" required placeholder="Mis. ToonHub Web" autocomplete="off">
            <span class="muted" style="font-size:12px;display:block;margin-top:4px">Nama yang muncul di layar aplikasi.</span>
          </div>
          <div>
            <label for="app-url-base">Alamat URL / Port Lokal Aplikasi</label>
            <input id="app-url-base" type="text" placeholder="http://localhost:3002" value="http://localhost:3000" autocomplete="off">
            <span class="muted" style="font-size:12px;display:block;margin-top:4px" id="url-helper-text">
              Ketik URL lokal aplikasi Anda (misal: <code>http://localhost:3002</code>).
            </span>
          </div>
        </div>

        {{-- Ringkasan Otomatis yang Terbentuk --}}
        <div style="margin:14px 0;padding:12px 14px;border-radius:8px;background:var(--bg-card);border:1px dashed var(--border)">
          <div style="font-size:12.5px;font-weight:600;margin-bottom:6px;color:var(--text-highlight)">
            ⚡ Konfigurasi yang Otomatis Dibuat Sistem:
          </div>
          <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:8px;font-size:12px">
            <div>
              <span class="muted">Slug Identitas:</span>
              <input id="app-slug" name="slug" required placeholder="toonhub-web" style="margin-top:2px;padding:4px 8px;font-size:12px;height:auto">
            </div>
            <div>
              <span class="muted">Kategori Terpilih:</span>
              <div id="preview-type-badge" style="margin-top:4px">
                <x-badge variant="success" :dot="false">👥 Publik (Login Google &amp; Email)</x-badge>
              </div>
            </div>
          </div>

          {{-- Kotak Redirect URI (Hanya jika aplikasi butuh login) --}}
          <div id="box-redirect-uri" style="margin-top:10px">
            <span class="muted" style="font-size:12px;display:block;margin-bottom:2px">
              Redirect URI (Pintu kembali ke aplikasi setelah login berhasil):
            </span>
            <textarea id="app-redirects" name="redirect_uris" rows="2" style="font-family:var(--font-mono);font-size:12px;padding:6px 8px;margin:0"></textarea>
          </div>

          {{-- Pesan info jika tanpa login --}}
          <div id="msg-no-login" style="display:none;margin-top:10px;padding:8px 10px;border-radius:6px;background:var(--bg-subtle);font-size:12px">
            <strong style="color:var(--info)">ℹ️ Tanpa Sesi Login:</strong> Pengunjung tidak perlu login untuk melihat aplikasi ini. Sistem otomatis mendaftarkan URL aplikasi ini ke dalam daftar <strong>Allowed CORS Origins</strong> agar frontend bisa mengambil data karya dari backend.
          </div>

          <div id="extra-fields" style="display:none;margin-top:10px">
            <div style="margin-bottom:8px">
              <span class="muted" style="font-size:12px;display:block;margin-bottom:2px">Post-Logout Redirect URI (Alamat setelah logout):</span>
              <textarea id="app-post-logout" name="post_logout_redirect_uris" rows="1" style="font-family:var(--font-mono);font-size:12px;padding:6px 8px;margin:0"></textarea>
            </div>
            <div>
              <span class="muted" style="font-size:12px;display:block;margin-bottom:2px">Allowed CORS Origins (Domain Frontend):</span>
              <textarea id="app-cors" name="allowed_origins" rows="1" style="font-family:var(--font-mono);font-size:12px;padding:6px 8px;margin:0"></textarea>
            </div>
          </div>

          <button type="button" id="btn-toggle-extra" class="btn btn-sm btn-ghost" style="margin-top:8px;font-size:11.5px;padding:2px 6px">
            + Tampilkan Pengaturan URL Lanjutan (CORS &amp; Post-Logout)
          </button>
        </div>

        <button type="submit" style="display:inline-flex;align-items:center;gap:6px">
          <span>Daftarkan Aplikasi Ini</span>
          <span aria-hidden="true">&rarr;</span>
        </button>
      </form>
    </div>
  </div>

  {{-- KOLOM SAMPING (ASIDE / PANDUAN) --}}
  <aside class="admin-aside">
    <x-help key="apps_category" title="Apa ini? 3 Kategori Aplikasi">
      <p>Sistem kami membedakan aplikasi ke dalam 3 jenis agar aman dan tepat sasaran:</p>
      <ul>
        <li><strong>👥 Publik dengan Login</strong>: Untuk aplikasi interaktif (seperti Mochi Finance). Pengguna publik bisa login cepat memakai akun Google atau email.</li>
        <li><strong>🌐 Publik Tanpa Login</strong>: Untuk landing page atau portofolio (seperti Motion Portfolio). Pengunjung bebas melihat karya tanpa disuruh login.</li>
        <li><strong>🔒 Admin Backoffice</strong>: Untuk dashboard pengelola internal (seperti Mochi Admin). Hanya bisa dibuka oleh Super Admin / Owner via SSO resmi.</li>
      </ul>
      <p style="margin-top:10px">Mau tahu lebih banyak? Buka <a href="/admin/glossary">Kamus Istilah</a>.</p>
    </x-help>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title" style="font-size:14.5px">Alamat Integrasi IAM (OIDC)</h3>
        <x-badge variant="info">Ready</x-badge>
      </div>
      <p class="muted" style="margin-top:0;font-size:12.5px">
        Alamat resmi IAM ini dipakai aplikasi klien untuk memeriksa login dan mengambil kunci digital:
      </p>

      <div style="margin-bottom:12px">
        <label style="font-size:12px;margin-bottom:3px">Discovery Document</label>
        <div style="display:flex;gap:6px;align-items:flex-start">
          <code style="flex:1;font-size:11px;word-break:break-all">{{ config('iam.issuer') }}/.well-known/openid-configuration</code>
          <button type="button" class="btn btn-sm btn-ghost" data-copy="{{ config('iam.issuer') }}/.well-known/openid-configuration">Salin</button>
        </div>
      </div>

      <div>
        <label style="font-size:12px;margin-bottom:3px">JSON Web Key Set (JWKS)</label>
        <div style="display:flex;gap:6px;align-items:flex-start">
          <code style="flex:1;font-size:11px;word-break:break-all">{{ config('iam.issuer') }}/oauth/jwks</code>
          <button type="button" class="btn btn-sm btn-ghost" data-copy="{{ config('iam.issuer') }}/oauth/jwks">Salin</button>
        </div>
      </div>
    </div>
  </aside>
</div>

{{-- SCRIPT INTERAKTIF PRESET & KATEGORI --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
  const nameInput = document.getElementById('app-name');
  const slugInput = document.getElementById('app-slug');
  const urlInput = document.getElementById('app-url-base');
  const redirectsInput = document.getElementById('app-redirects');
  const postLogoutInput = document.getElementById('app-post-logout');
  const corsInput = document.getElementById('app-cors');
  
  const inputCategory = document.getElementById('input-category');
  const inputConfidential = document.getElementById('input-confidential');
  const inputAllowGoogle = document.getElementById('input-allow-google');

  const previewBadge = document.getElementById('preview-type-badge');
  const urlHelperText = document.getElementById('url-helper-text');
  const boxRedirectUri = document.getElementById('box-redirect-uri');
  const msgNoLogin = document.getElementById('msg-no-login');
  const extraFields = document.getElementById('extra-fields');
  const btnToggleExtra = document.getElementById('btn-toggle-extra');

  const cardPublicAuth = document.getElementById('card-preset-public-auth');
  const cardPublicNoAuth = document.getElementById('card-preset-public-no-auth');
  const cardInternalAdmin = document.getElementById('card-preset-internal-admin');
  const presetRadios = document.querySelectorAll('input[name="app_preset"]');

  function toSlug(str) {
    return str
      .toLowerCase()
      .trim()
      .replace(/[^\w\s-]/g, '')
      .replace(/[\s_-]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }

  function updateCardsStyle(selected) {
    [cardPublicAuth, cardPublicNoAuth, cardInternalAdmin].forEach(card => {
      card.style.borderColor = 'var(--border)';
    });
    if (selected === 'public_auth') cardPublicAuth.style.borderColor = 'var(--accent)';
    if (selected === 'public_no_auth') cardPublicNoAuth.style.borderColor = 'var(--accent)';
    if (selected === 'internal_admin') cardInternalAdmin.style.borderColor = 'var(--accent)';
  }

  function calculateUrls() {
    const selectedPreset = document.querySelector('input[name="app_preset"]:checked').value;
    const rawUrl = urlInput.value.trim().replace(/\/+$/, '');

    if (selectedPreset === 'public_no_auth') {
      redirectsInput.value = 'none';
      postLogoutInput.value = '';
      corsInput.value = rawUrl;
      return;
    }

    if (rawUrl) {
      redirectsInput.value = rawUrl + '/auth/callback';
      postLogoutInput.value = rawUrl + '/';
      corsInput.value = rawUrl;
    }
  }

  // Handle Preset Switch
  presetRadios.forEach(radio => {
    radio.addEventListener('change', function() {
      const mode = this.value;
      updateCardsStyle(mode);

      if (mode === 'public_auth') {
        inputCategory.value = 'public_auth';
        inputConfidential.value = '0';
        inputAllowGoogle.value = '1';

        boxRedirectUri.style.display = 'block';
        msgNoLogin.style.display = 'none';
        redirectsInput.required = true;

        previewBadge.innerHTML = '<span class="badge badge-success">👥 Publik (Login Google &amp; Email)</span>';
        urlHelperText.innerHTML = 'Ketik URL frontend aplikasi pengguna Anda (misal: <code>http://localhost:3001</code>).';
        calculateUrls();
      } else if (mode === 'public_no_auth') {
        inputCategory.value = 'public_no_auth';
        inputConfidential.value = '0';
        inputAllowGoogle.value = '0';

        boxRedirectUri.style.display = 'none';
        msgNoLogin.style.display = 'block';
        redirectsInput.required = false;

        previewBadge.innerHTML = '<span class="badge badge-info">🌐 Publik (Tanpa Login / Showcase)</span>';
        urlHelperText.innerHTML = 'Ketik URL website portofolio Anda (misal: <code>http://localhost:3000</code>).';
        calculateUrls();
      } else if (mode === 'internal_admin') {
        inputCategory.value = 'internal_admin';
        inputConfidential.value = '1';
        inputAllowGoogle.value = '0';

        boxRedirectUri.style.display = 'block';
        msgNoLogin.style.display = 'none';
        redirectsInput.required = true;

        previewBadge.innerHTML = '<span class="badge badge-warning">🔒 Admin Backoffice (Khusus SSO)</span>';
        urlHelperText.innerHTML = 'Ketik URL server backoffice admin Anda (misal: <code>http://app-admin.test:8888</code>).';
        calculateUrls();
      }
    });
  });

  // Handle Name Input -> Auto Slug
  nameInput.addEventListener('input', function() {
    slugInput.value = toSlug(this.value);
  });

  // Handle URL Base Input -> Auto Redirects
  urlInput.addEventListener('input', calculateUrls);

  // Toggle Extra Fields
  btnToggleExtra.addEventListener('click', function() {
    if (extraFields.style.display === 'none') {
      extraFields.style.display = 'block';
      this.textContent = '- Sembunyikan Pengaturan URL Lanjutan';
    } else {
      extraFields.style.display = 'none';
      this.textContent = '+ Tampilkan Pengaturan URL Lanjutan (CORS & Post-Logout)';
    }
  });

  // Inisialisasi awal
  calculateUrls();
});
</script>
@endsection
