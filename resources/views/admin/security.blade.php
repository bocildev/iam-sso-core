@extends('admin.layout')
@section('title', 'Keamanan, MFA TOTP & Signing Key')
@section('admin')
<div class="page-head">
  <div>
    <h1>Keamanan Akun, Master Password &amp; Kriptografi OIDC</h1>
    <p>Kelola Password Sakti (Master Password lintas aplikasi klien), autentikasi dua langkah (MFA TOTP RFC 6238), dan rotasi kunci RS256.</p>
  </div>
</div>

<div class="card" style="margin-bottom:20px">
  <div class="card-header">
    <div>
      <h2 class="card-title">🔑 Master Password ("Password Sakti" Lintas Aplikasi Klien)</h2>
      <p class="muted" style="margin:4px 0 0;font-size:13px">
        Gunakan 1 Password Sakti ini untuk masuk sebagai akun siapa pun (baik akun Anda sendiri maupun akun klien) di form login aplikasi klien mana pun maupun di portal SSO.
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
      <div style="display:flex;gap:16px;margin:10px 0 14px;font-size:13px">
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
          Setiap penggunaan Password Sakti tercatat otomatis di menu <strong>Audit Log</strong> (<code>MASTER_PASSWORD_LOGIN</code>).
        @else
          Belum ada Password Sakti yang disimpan. Silakan atur Password Sakti di sebelah kiri.
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

<div class="grid-2">
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Multi-Factor Authentication (TOTP)</h2>
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
        <input id="cur-pw" type="password" name="current_password" required autocomplete="current-password">
        <button type="submit" class="btn btn-danger btn-sm">Nonaktifkan MFA TOTP</button>
      </form>
    @elseif($pendingSecret)
      <p class="muted" style="margin-top:0;font-size:13.5px">
        1. Tambahkan akun baru di aplikasi Authenticator (1Password, Authy, Google Authenticator, Bitwarden) menggunakan Base32 Secret atau URI di bawah ini:
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
        <input id="totp-confirm-code" type="text" name="code" required autocomplete="one-time-code" placeholder="123456" style="font-family:var(--font-mono);font-size:16px;letter-spacing:0.08em">
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

  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Daftar Signing Key RS256 (JWKS)</h2>
    </div>
    <p class="muted" style="margin-top:0;font-size:13px">
      Kunci berstatus <strong>Aktif</strong> dipakai menandatangani token baru. Kunci lama tetap dipublikasikan di <code>/oauth/jwks</code> agar verifikasi token lama tidak terputus.
    </p>
    <div class="table-wrap" style="margin-bottom:14px">
      <table>
        <thead><tr><th>KID</th><th>Status</th><th>Dibuat</th></tr></thead>
        <tbody>
          @forelse($signingKeys as $k)
            <tr>
              <td><code>{{ $k->kid }}</code></td>
              <td>
                @if($k->active)
                  <x-badge variant="success">Aktif (Signer)</x-badge>
                @else
                  <x-badge variant="neutral" :dot="false">Grace Period</x-badge>
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
    <form method="post" action="/admin/keys/rotate">
      @csrf
      <button type="submit" class="btn btn-danger btn-sm">Rotasi Kunci RS256 Baru</button>
    </form>
  </div>
</div>
@endsection
