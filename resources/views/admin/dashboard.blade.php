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

<div class="dashboard-layout">
  <div class="dashboard-main">
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

    <div class="card">
      <div class="card-header">
        <div>
          <h2 class="card-title">Sesi Aktif per Aplikasi Klien (App-Scoped Sessions)</h2>
          <p class="muted" style="margin:4px 0 0;font-size:12.5px">
            Daftar sesi login yang sedang aktif di masing-masing aplikasi klien. Mencabut sesi di bawah ini hanya mengeluarkan user dari aplikasi terkait tanpa mematikan sesi induk IAM.
          </p>
        </div>
        <x-badge variant="info">{{ count($clientAppSessions ?? []) }} Sesi Aplikasi</x-badge>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Aplikasi Klien</th>
              <th>Pengguna</th>
              <th>Session ID</th>
              <th>Waktu Login</th>
              <th>IP</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            @forelse($clientAppSessions ?? [] as $cs)
              <tr>
                <td>
                  <strong>{{ $cs->app_name }}</strong>
                  <div class="muted" style="font-size:11.5px"><code>{{ $cs->client_id }}</code></div>
                </td>
                <td>
                  <strong>{{ $cs->user_name }}</strong>
                  <div class="muted" style="font-size:11.5px">{{ $cs->user_email }}</div>
                </td>
                <td><code>{{ \Illuminate\Support\Str::limit($cs->sso_session_id, 14) }}</code></td>
                <td class="muted">{{ $cs->issued_at }}</td>
                <td class="muted">{{ $cs->ip ?: '-' }}</td>
                <td>
                  <form method="post" action="/admin/app-sessions/revoke" style="margin:0">
                    @csrf
                    <input type="hidden" name="sso_session_id" value="{{ $cs->sso_session_id }}">
                    <input type="hidden" name="application_id" value="{{ $cs->application_id }}">
                    <button type="submit" class="btn btn-danger btn-sm">Putus Sesi App</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="muted">Belum ada sesi aplikasi klien yang aktif saat ini.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
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
  </div>

  <aside class="dashboard-aside">
    <x-help key="dashboard" title="Apa ini? Cara membaca dashboard">
      <p>Dashboard adalah ringkasan kondisi sistem login pusatmu, seperti panel instrumen di mobil.</p>
      <ul>
        <li><strong>Aplikasi Klien</strong>: jumlah aplikasimu yang sudah memakai IAM untuk login.</li>
        <li><strong>Identitas User</strong>: jumlah akun pengguna yang terdaftar.</li>
        <li><strong>Sesi SSO Aktif</strong>: orang yang sedang login saat ini.</li>
        <li><strong>Login Gagal (24j)</strong>: salah password dalam 24 jam terakhir. Angka tinggi tiba-tiba bisa berarti ada yang mencoba menebak password.</li>
        <li><strong>Outbox Pending</strong>: pesan otomatis ke aplikasi yang belum terkirim. Biasanya 0; kalau menumpuk, ada aplikasi yang tidak bisa dihubungi.</li>
      </ul>
    </x-help>

    <div class="card">
      <div class="card-header" style="margin-bottom:10px">
        <h3 class="card-title" style="font-size:14.5px">Kunci Penandatangan (RS256)</h3>
        @if($activeKey ?? null)
          <x-badge variant="success">Aktif</x-badge>
        @else
          <x-badge variant="warning">Auto</x-badge>
        @endif
      </div>
      <p class="muted" style="font-size:12.5px;margin:0 0 10px">
        Kunci kriptografi rahasia untuk menandatangani token login (JWT).
      </p>
      @if($activeKey ?? null)
        <div style="margin-bottom:8px">
          <span class="muted" style="font-size:11.5px;display:block">Key ID (KID)</span>
          <code style="font-size:12px">{{ $activeKey->kid }}</code>
        </div>
        <div class="muted" style="font-size:12px;margin-bottom:12px">
          Dibuat {{ \Illuminate\Support\Carbon::parse($activeKey->created_at)->diffForHumans() }}
        </div>
      @endif
      <a href="/admin/security" class="btn btn-sm btn-ghost" style="width:100%;justify-content:center">
        Kelola di Keamanan &amp; MFA &rarr;
      </a>
    </div>

    <div class="card">
      <div class="card-header" style="margin-bottom:8px">
        <h3 class="card-title" style="font-size:14.5px">Pintasan Cepat</h3>
      </div>
      <div style="display:flex;flex-direction:column;gap:6px">
        <a href="/admin/glossary" class="nav-link" style="padding:8px 10px">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
          <span>Kamus Istilah</span>
        </a>
        <a href="/admin/apps" class="nav-link" style="padding:8px 10px">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
          <span>Registry Aplikasi &amp; Endpoint</span>
        </a>
        <a href="/admin/audit" class="nav-link" style="padding:8px 10px">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          <span>Audit Log Keamanan</span>
        </a>
      </div>
    </div>
  </aside>
</div>
@endsection
