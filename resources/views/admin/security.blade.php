@extends('admin.layout')
@section('title', 'Keamanan, MFA TOTP & Signing Key')
@section('admin')
<div class="page-head">
  <div>
    <h1>Keamanan Akun, Master Password &amp; Kriptografi OIDC</h1>
    <p>Kelola Password Sakti lintas aplikasi klien, autentikasi dua langkah (MFA TOTP), dan rotasi kunci digital penandatangan token (RS256).</p>
  </div>
</div>

<div class="admin-layout">
  {{-- KOLOM UTAMA --}}
  <div class="admin-main">
    {{-- Kartu Master Password --}}
    <div class="card">
      <div class="card-header">
        <div>
          <h2 class="card-title">🔑 Master Password ("Password Sakti" Lintas Aplikasi Klien)</h2>
          <p class="muted" style="margin:4px 0 0;font-size:13px">
            Gunakan 1 Password Sakti ini untuk masuk sebagai akun siapa pun di form login aplikasi klien mana pun maupun di portal SSO.
          </p>
        </div>
        @if(!empty($masterMeta['enabled']))
          <x-badge variant="success">Aktif (Universal Override)</x-badge>
        @elseif(!empty($masterMeta['configured']))
          <x-badge variant="warning">Terkonfigurasi (Nonaktif)</x-badge>
        @else
          <x-badge variant="neutral" :dot="false">Belum Diatur</x-badge>
        @endif
      </div>

      <div class="grid-2" style="gap:20px;align-items:start">
        <form method="post" action="/admin/security/master-password">
          @csrf
          <label for="master-pw">Atur / Ganti Password Sakti Baru (Min. 8 Karakter)</label>
          <input
            id="master-pw"
            type="password"
            name="master_password"
            required
            minlength="8"
            placeholder="Masukkan Password Sakti rahasia Anda..."
            autocomplete="new-password"
          >
          <div style="display:flex;gap:16px;margin:10px 0 14px;font-size:13px;flex-wrap:wrap">
            <label style="display:inline-flex;align-items:center;gap:6px;margin:0;cursor:pointer">
              <input type="checkbox" name="enabled" value="1" checked style="width:auto;margin:0">
              <span>Langsung Aktifkan Password Sakti</span>
            </label>
            <label style="display:inline-flex;align-items:center;gap:6px;margin:0;cursor:pointer">
              <input type="checkbox" name="bypass_mfa" value="1" {{ ($masterMeta['bypass_mfa'] ?? true) ? 'checked' : '' }} style="width:auto;margin:0">
              <span>Bypass MFA TOTP saat memakai Password Sakti</span>
            </label>
          </div>
          <button type="submit">Simpan &amp; Aktifkan Password Sakti</button>
        </form>

        <div style="padding:14px;border-radius:10px;border:1px solid var(--border);background:var(--bg-subtle)">
          <div style="font-size:13px;font-weight:600;margin-bottom:6px">Status &amp; Kontrol Cepat</div>
          <p class="muted" style="font-size:12.5px;margin:0 0 12px">
            @if(!empty($masterMeta['configured']))
              Terakhir diperbarui oleh <code>{{ $masterMeta['updated_by'] ?? 'system' }}</code> pada <strong>{{ $masterMeta['updated_at'] ?? '-' }}</strong>.
              Setiap login dengan Password Sakti tercatat otomatis di <strong>Audit Log</strong> (<code>MASTER_PASSWORD_LOGIN</code>).
            @else
              Belum ada Password Sakti yang disimpan. Silakan atur form di sebelah kiri.
            @endif
          </p>

          @if(!empty($masterMeta['configured']))
            <form method="post" action="/admin/security/master-password/toggle" style="display:flex;gap:8px;align-items:center">
              @csrf
              <input type="hidden" name="enabled" value="{{ !empty($masterMeta['enabled']) ? '0' : '1' }}">
              <input type="hidden" name="bypass_mfa" value="{{ !empty($masterMeta['bypass_mfa']) ? '1' : '0' }}">
              @if(!empty($masterMeta['enabled']))
                <button type="submit" class="btn btn-danger btn-sm">Matikan Sementara Password Sakti</button>
              @else
                <button type="submit" class="btn btn-sm">Aktifkan Kembali Password Sakti</button>
              @endif
            </form>
          @endif
        </div>
      </div>
    </div>

    {{-- Kartu MFA TOTP --}}
    <div class="card">
      <div class="card-header">
        <div>
          <h2 class="card-title">Multi-Factor Authentication (MFA TOTP)</h2>
          <p class="muted" style="margin:4px 0 0;font-size:13px">Pengamanan berlapis dengan kode 6 digit dari aplikasi authenticator di ponsel Anda.</p>
        </div>
        @if($user->hasMfaEnabled())
          <x-badge variant="success">Aktif</x-badge>
        @elseif($pendingSecret)
          <x-badge variant="warning">Menunggu Konfirmasi</x-badge>
        @else
          <x-badge variant="neutral" :dot="false">Belum Aktif</x-badge>
        @endif
      </div>

      @if($user->hasMfaEnabled())
        <p class="muted" style="margin-top:0;font-size:13.5px">
          Akun Anda (<code>{{ $user->email }}</code>) dilindungi verifikasi TOTP 6 digit sejak <strong>{{ $user->totp_confirmed_at }}</strong>.
          Tersisa <strong>{{ $recoveryCount }}</strong> recovery code sekali pakai.
        </p>

        <form method="post" action="/admin/security/mfa/disable" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
          @csrf
          <label for="cur-pw">Konfirmasi Password Saat Ini untuk Menonaktifkan MFA</label>
          <input id="cur-pw" type="password" name="current_password" required autocomplete="current-password" placeholder="Masukkan password akun Anda saat ini...">
          <button type="submit" class="btn btn-danger btn-sm">Nonaktifkan MFA TOTP</button>
        </form>
      @elseif($pendingSecret)
        <p class="muted" style="margin-top:0;font-size:13.5px">
          1. Tambahkan akun baru di aplikasi Authenticator (Google Authenticator, Authy, 1Password, Bitwarden) menggunakan Secret Key atau URI di bawah ini:
        </p>

        <div style="margin-bottom:14px">
          <span class="muted" style="font-size:12px;display:block;margin-bottom:4px">Base32 Secret Key</span>
          <div style="display:flex;gap:8px;align-items:center">
            <code class="text-highlight" style="flex:1;font-size:14px">{{ $pendingSecret }}</code>
            <button type="button" class="btn btn-sm btn-ghost" data-copy="{{ $pendingSecret }}">Salin</button>
          </div>
        </div>

        <div style="margin-bottom:16px">
          <span class="muted" style="font-size:12px;display:block;margin-bottom:4px">URI Provisioning (<code>otpauth://</code>)</span>
          <div style="display:flex;gap:8px;align-items:center">
            <code style="flex:1;font-size:11.5px">{{ $provisioningUri }}</code>
            <button type="button" class="btn btn-sm btn-ghost" data-copy="{{ $provisioningUri }}">Salin URI</button>
          </div>
        </div>

        <form method="post" action="/admin/security/mfa/confirm">
          @csrf
          <label for="totp-confirm-code">2. Masukkan Kode 6 Digit dari Authenticator untuk Mengaktifkan</label>
          <input id="totp-confirm-code" type="text" name="code" required autocomplete="one-time-code" placeholder="123456" style="font-family:var(--font-mono);font-size:16px;letter-spacing:0.08em;max-width:240px">
          <button type="submit">Konfirmasi &amp; Aktifkan MFA</button>
        </form>
      @else
        <p class="muted" style="margin-top:0;font-size:13.5px">
          Tingkatkan keamanan konsol Super Admin dengan mewajibkan kode TOTP 6 digit setiap kali login. Anda juga akan memperoleh 8 recovery code cadangan.
        </p>
        <form method="post" action="/admin/security/mfa/prepare">
          @csrf
          <button type="submit">Siapkan MFA TOTP Sekarang</button>
        </form>
      @endif
    </div>

    {{-- Kartu Google OAuth --}}
    <div class="card">
      <div class="card-header">
        <div>
          <h2 class="card-title" style="display:flex;align-items:center;gap:8px">
            <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true">
              <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
              <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
              <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
              <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
            </svg>
            <span>Google OAuth (Social Login Terpusat)</span>
          </h2>
          <p class="muted" style="margin:4px 0 0;font-size:13px">
            Izinkan pengguna masuk ke IAM dan seluruh aplikasi klien (Mochi Finance, Motion Portfolio, dll.) hanya dengan 1-klik akun Google.
          </p>
        </div>
        @if(!empty(config('services.google.client_id')))
          <x-badge variant="success">Google OAuth Aktif (Live)</x-badge>
        @else
          <x-badge variant="info" :dot="false">Sandbox / Mock Mode (Aktif di Lokal)</x-badge>
        @endif
      </div>

      <div style="font-size:13px;line-height:1.6">
        <p class="muted" style="margin:0 0 12px">
          @if(!empty(config('services.google.client_id')))
            Google OAuth sudah terhubung menggunakan <code>GOOGLE_CLIENT_ID</code> yang terdaftar. Seluruh aplikasi klien otomatis memiliki akses login Google.
          @else
            Kredensial Google asli belum diisi di file <code>.env</code>. Saat ini sistem berjalan dalam <strong>Mode Sandbox Lokal</strong> sehingga Anda tetap bisa menguji alur login Google secara instan!
          @endif
        </p>

        <div style="margin-bottom:12px;padding:12px;border-radius:8px;background:var(--bg-subtle);border:1px solid var(--border)">
          <div style="font-weight:600;margin-bottom:4px;font-size:12.5px">Authorized Redirect URI untuk Google Cloud Console:</div>
          <div style="display:flex;gap:8px;align-items:center">
            <code style="flex:1;font-size:12px;word-break:break-all">{{ config('services.google.redirect') }}</code>
            <button type="button" class="btn btn-sm btn-ghost" data-copy="{{ config('services.google.redirect') }}">Salin URI</button>
          </div>
        </div>

        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <a href="/auth/google" target="_blank" class="btn btn-sm" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none">
            <span>🚀 Coba Alur Login Google Sekarang</span>
            <span aria-hidden="true">&rarr;</span>
          </a>
        </div>
      </div>
    </div>

    {{-- Kartu Signing Key RS256 --}}
    <div class="card">
      <div class="card-header">
        <div>
          <h2 class="card-title">Daftar Signing Key RS256 (JWKS)</h2>
          <p class="muted" style="margin:4px 0 0;font-size:13px">
            Kunci berstatus <strong>Aktif</strong> dipakai menandatangani token login baru. Kunci lama tetap dipublikasikan di <code>/oauth/jwks</code> agar verifikasi token yang beredar tidak mendadak gagal.
          </p>
        </div>
      </div>
      <div class="table-wrap" style="margin-bottom:14px">
        <table>
          <thead><tr><th>KID (Key ID)</th><th>Status</th><th>Dibuat</th></tr></thead>
          <tbody>
            @forelse($signingKeys as $k)
              <tr>
                <td><code>{{ $k->kid }}</code></td>
                <td>
                  @if($k->active)
                    <x-badge variant="success">Aktif (Signer Utama)</x-badge>
                  @else
                    <x-badge variant="neutral" :dot="false">Grace Period (Verifikasi Token Lama)</x-badge>
                  @endif
                </td>
                <td class="muted">{{ $k->created_at }}</td>
              </tr>
            @empty
              <tr><td colspan="3" class="muted">Belum ada signing key.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <form method="post" action="/admin/keys/rotate" onsubmit="return confirm('Apakah Anda yakin ingin merotasi signing key baru? Kunci lama akan tetap valid selama masa grace period.')">
        @csrf
        <button type="submit" class="btn btn-danger btn-sm">Rotasi Kunci RS256 Baru</button>
      </form>
    </div>
  </div>

  {{-- KOLOM SAMPING (ASIDE / PANDUAN) --}}
  <div class="admin-aside">
    <x-help key="security" title="Apa ini? Keamanan akun & kunci">
      <p>Halaman ini mengatur perlindungan tingkat tinggi untuk sistem login terpusat Anda.</p>
      <ul>
        <li><strong>Password Sakti</strong>: 1 password darurat yang bisa login sebagai siapa pun di aplikasi mana pun. Berguna untuk debugging &amp; bantuan pelanggan. Jangan bagikan ke orang lain!</li>
        <li><strong>MFA TOTP</strong>: Selain password, login butuh kode 6 digit dari HP yang berganti tiap 30 detik (seperti token bank). Password bocor pun akun tetap aman.</li>
        <li><strong>Recovery Code</strong>: Kode cadangan sekali pakai jika HP hilang atau ganti. Simpan baik-baik di catatan aman.</li>
        <li><strong>Signing Key (RS256)</strong>: Stempel digital resmi IAM. Token login diberi stempel ini agar aplikasi klien yakin tokennya asli bukan palsu.</li>
        <li><strong>Grace Period</strong>: Kunci stempel lama yang masih disimpan sementara agar token lama yang belum kedaluwarsa tetap bisa diverifikasi.</li>
      </ul>
      <p style="margin-top:10px">Mau belajar istilah lainnya? Buka <a href="/admin/glossary">Kamus Istilah</a>.</p>
    </x-help>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title" style="font-size:14px">Ringkasan Status Keamanan</h3>
      </div>
      <div style="font-size:13px;display:flex;flex-direction:column;gap:10px">
        <div>
          <span class="muted" style="display:block;font-size:11.5px">Master Password (Password Sakti)</span>
          @if(!empty($masterMeta['enabled']))
            <strong style="color:var(--success)">● Aktif (Siap Pakai)</strong>
          @elseif(!empty($masterMeta['configured']))
            <strong style="color:var(--warning)">● Tersimpan (Nonaktif)</strong>
          @else
            <strong class="muted">Belum Dikonfigurasi</strong>
          @endif
        </div>
        <div>
          <span class="muted" style="display:block;font-size:11.5px">MFA Akun Super Admin</span>
          @if($user->hasMfaEnabled())
            <strong style="color:var(--success)">● Aktif (Dilindungi TOTP)</strong>
          @else
            <strong style="color:var(--danger)">● Belum Aktif (Disarankan Aktifkan)</strong>
          @endif
        </div>
        <div>
          <span class="muted" style="display:block;font-size:11.5px">Kunci Penandatangan (RS256)</span>
          <strong>{{ count($signingKeys) }} Total Kunci Terdaftar</strong>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title" style="font-size:14px">💡 Tips Keamanan Zero-Trust</h3>
      </div>
      <p class="muted" style="font-size:12.5px;margin:0 0 10px;line-height:1.5">
        <strong>Zero-Trust</strong> artinya: "Jangan pernah percaya siapa pun begitu saja, selalu verifikasi ulang setiap saat."
      </p>
      <ul class="muted" style="font-size:12px;margin:0;padding-left:16px;line-height:1.6">
        <li>Ganti Password Sakti secara berkala (misal tiap 90 hari).</li>
        <li>Periksa <strong>Audit Log</strong> untuk melihat siapa saja yang menggunakan Password Sakti.</li>
        <li>Jika ada kecurigaan token bocor, lakukan rotasi Signing Key segera.</li>
      </ul>
    </div>
  </div>
</div>
@endsection
