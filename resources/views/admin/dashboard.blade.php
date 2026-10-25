@extends('admin.layout')
@section('title', 'Dashboard Control Plane')
@section('admin')
<div class="page-head">
  <div>
    <h1>Ringkasan Ekosistem IAM</h1>
    <p>Telemetri identitas terpusat, sesi OIDC aktif, dan status tanda tangan kriptografi.</p>
  </div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <a href="/admin/apps" class="btn btn-sm">+ Daftarkan Aplikasi</a>
    <a href="/admin/security" class="btn btn-sm btn-ghost">Pengaturan MFA &amp; Key</a>
  </div>
</div>

<div class="kpi-grid">
  <div class="kpi-card">
    <div class="kpi-label">
      <span>Aplikasi Klien</span>
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
    </div>
    <div class="kpi-value">{{ $apps }}</div>
    <span class="muted" style="font-size:12px">Terdaftar di registry</span>
  </div>

  <div class="kpi-card">
    <div class="kpi-label">
      <span>Identitas User</span>
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
    </div>
    <div class="kpi-value">{{ $users }}</div>
    <span class="muted" style="font-size:12px">{{ $orgs ?? 0 }} organisasi aktif</span>
  </div>

  <div class="kpi-card">
    <div class="kpi-label">
      <span>Sesi SSO Aktif</span>
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    </div>
    <div class="kpi-value" style="color:var(--ok)">{{ $sessions }}</div>
    <span class="muted" style="font-size:12px">Belum dicabut / kedaluwarsa</span>
  </div>

  <div class="kpi-card">
    <div class="kpi-label">
      <span>Login Gagal (24j)</span>
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
    </div>
    <div class="kpi-value" style="color:{{ $failed > 0 ? 'var(--warn)' : 'var(--text)' }}">{{ $failed }}</div>
    <span class="muted" style="font-size:12px">Deteksi brute-force aktif</span>
  </div>

  <div class="kpi-card">
    <div class="kpi-label">
      <span>Outbox Pending</span>
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
    </div>
    <div class="kpi-value">{{ $outboxPending }}</div>
    <span class="muted" style="font-size:12px">Antrean webhook HMAC</span>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Endpoint OpenID Connect 1.0</h2>
      <x-badge variant="info">Discovery Ready</x-badge>
    </div>
    <p class="muted" style="margin-top:0;font-size:13px">Gunakan endpoint berikut pada SDK klien (<code>useIamSso.ts</code> / <code>TokenVerifier</code>):</p>
    <div style="display:flex;flex-direction:column;gap:10px">
      <div>
        <span class="muted" style="font-size:12px;display:block;margin-bottom:3px">Discovery Document</span>
        <div style="display:flex;gap:8px;align-items:center">
          <code style="flex:1">{{ config('iam.issuer') }}/.well-known/openid-configuration</code>
          <button type="button" class="btn btn-sm btn-ghost" data-copy="{{ config('iam.issuer') }}/.well-known/openid-configuration">Salin</button>
        </div>
      </div>
      <div>
        <span class="muted" style="font-size:12px;display:block;margin-bottom:3px">JSON Web Key Set (JWKS)</span>
        <div style="display:flex;gap:8px;align-items:center">
          <code style="flex:1">{{ config('iam.issuer') }}/oauth/jwks</code>
          <button type="button" class="btn btn-sm btn-ghost" data-copy="{{ config('iam.issuer') }}/oauth/jwks">Salin</button>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Signing Key RS256</h2>
      @if($activeKey ?? null)
        <x-badge variant="success">KID: {{ $activeKey->kid }}</x-badge>
      @else
        <x-badge variant="warning">Auto-Generate</x-badge>
      @endif
    </div>
    <p class="muted" style="margin-top:0;font-size:13px">
      Rotasi membuat pasangan kunci RSA-2048 baru untuk penandatanganan JWT. Kunci lama tetap disajikan di endpoint JWKS untuk masa tenggang verifikasi token yang masih beredar.
    </p>
    <form method="post" action="/admin/keys/rotate">
      @csrf
      <button class="btn-danger btn" type="submit">Rotasi Signing Key RS256</button>
    </form>
  </div>
</div>

@if(!empty($recentLogs) && count($recentLogs))
<div class="card">
  <div class="card-header">
    <h2 class="card-title">Aktivitas Audit Terbaru</h2>
    <a href="/admin/audit" style="font-size:13px">Lihat semua log &rarr;</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Waktu</th><th>Aksi</th><th>Aktor</th><th>Target</th><th>Hasil</th><th>IP</th></tr>
      </thead>
      <tbody>
        @foreach($recentLogs as $l)
          <tr>
            <td class="mono" style="background:none;border:0;padding:10px 12px">{{ $l->created_at }}</td>
            <td><code>{{ $l->action }}</code></td>
            <td>{{ $l->actor_type }} {{ $l->actor_id }}</td>
            <td>{{ $l->target ?: '-' }}</td>
            <td><x-badge :variant="$l->result">{{ $l->result }}</x-badge></td>
            <td class="muted">{{ $l->ip }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endif
@endsection
