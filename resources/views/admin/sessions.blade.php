@extends('admin.layout')
@section('title', 'Manajemen Sesi Aktif Klien')
@section('admin')
<div class="page-head">
  <div>
    <h1>Manajemen Sesi Aktif Klien</h1>
    <p>Monitor dan cabut sesi login aktif per aplikasi dalam format TreeTable terstruktur hierarki.</p>
  </div>
  <div style="display:flex;align-items:center;gap:10px">
    <form method="post" action="/admin/app-sessions/revoke-all" style="margin:0" onsubmit="return confirm('⚠️ PERINGATAN KERAS:\n\nApakah Anda yakin ingin MENCABUT SELURUH ({{ $totalActiveSessions }}) sesi aplikasi klien di seluruh ekosistem?\n\nSemua pengguna di seluruh aplikasi klien akan otomatis terlogout.');">
      @csrf
      <button type="submit" class="btn btn-danger btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
        <span>Putus Semua Sesi Ekosistem</span>
      </button>
    </form>
  </div>
</div>

{{-- Metric Highlights --}}
<div class="grid-3" style="margin-bottom:20px">
  <div class="metric-card">
    <div class="metric-title">Total Sesi Aplikasi Aktif</div>
    <div class="metric-value">{{ $totalActiveSessions }}</div>
    <div class="metric-hint">Refresh token valid di ekosistem</div>
  </div>
  <div class="metric-card">
    <div class="metric-title">Aplikasi Terhubung</div>
    <div class="metric-value">{{ $totalConnectedApps }}</div>
    <div class="metric-hint">Aplikasi yang memiliki sesi aktif</div>
  </div>
  <div class="metric-card">
    <div class="metric-title">Pengguna Unik Online</div>
    <div class="metric-value">{{ $totalUniqueUsers }}</div>
    <div class="metric-hint">Subjek OIDC unik yang aktif</div>
  </div>
</div>

<div class="admin-layout">
  <div class="admin-main">
    {{-- Filter & Search Toolbar --}}
    <div class="card" style="margin-bottom:16px">
      <form method="get" action="/admin/sessions" style="margin:0;display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between">
        <div style="display:flex;gap:10px;flex:1;min-width:280px;align-items:center">
          <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama pengguna, email, IP, atau client ID..." style="margin:0;padding:8px 12px;font-size:13px;flex:1">
          <select name="app" style="margin:0;width:auto;padding:8px 12px;font-size:13px">
            <option value="">Semua Aplikasi</option>
            @foreach($appsList as $al)
              <option value="{{ $al->slug }}" @selected($appFilter === $al->slug)>{{ $al->name }}</option>
            @endforeach
          </select>
          <button type="submit" class="btn btn-sm btn-ghost" style="border:1px solid var(--border)">Cari</button>
          @if($search || $appFilter)
            <a href="/admin/sessions" class="btn btn-sm btn-ghost" style="color:var(--danger)">Reset</a>
          @endif
        </div>
        <div style="display:flex;gap:8px">
          <button type="button" class="btn btn-sm btn-ghost" id="btn-expand-all" style="font-size:12px">
            + Buka Semua Pohon
          </button>
          <button type="button" class="btn btn-sm btn-ghost" id="btn-collapse-all" style="font-size:12px">
            − Tutup Semua Pohon
          </button>
        </div>
      </form>
    </div>

    {{-- TreeTable Card --}}
    <div class="card" style="padding:0;overflow:hidden">
      @forelse($grouped as $appId => $sessions)
        @php
          $first = $sessions->first();
          $appSessionsCount = $sessions->count();
        @endphp
        <div class="app-tree-node" id="node-app-{{ $appId }}" style="border-bottom:1px solid var(--border)">
          {{-- Header Baris Aplikasi (Parent Tree Node) --}}
          <div class="app-tree-header" style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:var(--bg-subtle);cursor:pointer;user-select:none" onclick="toggleTreeNode('{{ $appId }}')">
            <div style="display:flex;align-items:center;gap:12px">
              <span id="chevron-{{ $appId }}" style="display:inline-flex;align-items:center;transition:transform 0.2s ease;transform:rotate(90deg)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m9 18 6-6-6-6"/></svg>
              </span>
              <div>
                <div style="display:flex;align-items:center;gap:8px">
                  <span style="font-weight:700;font-size:14.5px;color:var(--text-main)">{{ $first->app_name }}</span>
                  <x-badge variant="neutral" :dot="false"><code style="font-size:11px">{{ $first->client_id }}</code></x-badge>
                  <x-badge variant="info">{{ $appSessionsCount }} Sesi Aktif</x-badge>
                </div>
                <div class="muted" style="font-size:11.5px;margin-top:2px">
                  Kategori: <strong>{{ $first->app_category ?? 'public' }}</strong> · Slug: <code>{{ $first->app_slug }}</code>
                </div>
              </div>
            </div>

            <div style="display:flex;align-items:center;gap:10px" onclick="event.stopPropagation()">
              <form method="post" action="/admin/app-sessions/revoke-app" style="margin:0" onsubmit="return confirm('Putus SEMUA ({{ $appSessionsCount }}) sesi aktif pada aplikasi &quot;{{ $first->app_name }}&quot;?');">
                @csrf
                <input type="hidden" name="application_id" value="{{ $first->app_id }}">
                <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--danger);border:1px solid rgba(239, 68, 68, 0.3)">
                  Putus Semua Sesi App Ini
                </button>
              </form>
            </div>
          </div>

          {{-- Daun Sesi Pengguna (Children Nodes) --}}
          <div id="children-{{ $appId }}" class="app-tree-children" style="display:block">
            <div class="table-wrap" style="margin:0">
              <table style="width:100%;margin:0">
                <thead>
                  <tr style="background:transparent">
                    <th style="padding-left:44px">Pengguna &amp; Identitas</th>
                    <th>ID Sesi OIDC</th>
                    <th>Waktu Login</th>
                    <th>IP &amp; Device</th>
                    <th style="text-align:right">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($sessions as $sess)
                    <tr>
                      <td style="padding-left:44px">
                        <div style="font-weight:600;font-size:13.5px">{{ $sess->user_name }}</div>
                        <div class="muted" style="font-size:11.5px">{{ $sess->user_email }}</div>
                        <div class="muted" style="font-size:10.5px;font-family:var(--font-mono)">sub: {{ $sess->user_sub }}</div>
                      </td>
                      <td>
                        <code style="font-size:11.5px">{{ \Illuminate\Support\Str::limit($sess->sso_session_id, 16) }}</code>
                      </td>
                      <td class="muted" style="font-size:12.5px">{{ $sess->issued_at }}</td>
                      <td>
                        <div style="font-size:12.5px"><code>{{ $sess->ip ?: '127.0.0.1' }}</code></div>
                        <div class="muted" style="font-size:11px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $sess->user_agent }}">
                          {{ $sess->user_agent ?: 'Unknown User-Agent' }}
                        </div>
                      </td>
                      <td style="text-align:right">
                        <form method="post" action="/admin/app-sessions/revoke" style="margin:0" onsubmit="return confirm('Putus sesi aplikasi untuk user &quot;{{ $sess->user_name }}&quot;?');">
                          @csrf
                          <input type="hidden" name="sso_session_id" value="{{ $sess->sso_session_id }}">
                          <input type="hidden" name="application_id" value="{{ $sess->application_id }}">
                          <button type="submit" class="btn btn-sm btn-danger">Putus Sesi</button>
                        </form>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        </div>
      @empty
        <div style="padding:48px 24px;text-align:center">
          <div style="font-size:32px;margin-bottom:12px">🍃</div>
          <h3 style="margin:0 0 6px;font-size:16px">Tidak Ada Sesi Aplikasi Aktif</h3>
          <p class="muted" style="margin:0;font-size:13px">
            @if($search || $appFilter)
              Tidak ditemukan sesi yang sesuai dengan filter pencarian Anda. <a href="/admin/sessions">Reset Filter</a>.
            @else
              Saat ini belum ada pengguna yang sedang login di aplikasi klien mana pun.
            @endif
          </p>
        </div>
      @endforelse
    </div>
  </div>

  <aside class="admin-aside">
    <x-help key="app_sessions" title="Apa itu TreeTable Sesi Aplikasi?">
      <ul>
        <li><strong>Format TreeTable</strong>: Mengelompokkan seluruh sesi aktif berdasarkan induk aplikasi kliennya (seperti folder dan file). Anda dapat melipat atau membuka setiap grup aplikasi.</li>
        <li><strong>App-Scoped Session</strong>: Sesi login yang berdiri sendiri di satu aplikasi. Jika dicabut, user hanya keluar dari aplikasi itu saja tanpa membatalkan login SSO utama IAM.</li>
        <li><strong>Putus Semua Sesi App</strong>: Berguna jika satu aplikasi sedang mengalami pembaruan sistem atau dicurigai mengalami anomali akses.</li>
        <li><strong>Putus Semua Sesi Ekosistem</strong>: Tombol darurat untuk membersihkan seluruh sesi di seluruh aplikasi klien sekaligus.</li>
      </ul>
    </x-help>

    <div class="card">
      <div class="card-header" style="margin-bottom:8px">
        <h3 class="card-title" style="font-size:14.5px">Aplikasi Terdaftar</h3>
      </div>
      <p class="muted" style="font-size:12px;margin:0 0 12px">
        Daftar aplikasi klien yang memiliki izin autentikasi OIDC terpusat:
      </p>
      <div style="display:flex;flex-direction:column;gap:6px">
        @foreach($appsList as $al)
          <div style="display:flex;align-items:center;justify-content:space-between;padding:6px 8px;border-radius:6px;background:var(--bg-subtle);font-size:12.5px">
            <div>
              <span style="font-weight:600">{{ $al->name }}</span>
              <div class="muted" style="font-size:11px"><code>{{ $al->client_id }}</code></div>
            </div>
            <a href="/admin/sessions?app={{ $al->slug }}" class="btn btn-sm btn-ghost" style="padding:2px 6px;font-size:11px">Filter</a>
          </div>
        @endforeach
      </div>
    </div>
  </aside>
</div>

<script>
function toggleTreeNode(appId) {
  const children = document.getElementById('children-' + appId);
  const chevron = document.getElementById('chevron-' + appId);
  if (!children) return;

  if (children.style.display === 'none') {
    children.style.display = 'block';
    if (chevron) chevron.style.transform = 'rotate(90deg)';
  } else {
    children.style.display = 'none';
    if (chevron) chevron.style.transform = 'rotate(0deg)';
  }
}

document.addEventListener('DOMContentLoaded', function() {
  const btnExpand = document.getElementById('btn-expand-all');
  const btnCollapse = document.getElementById('btn-collapse-all');

  if (btnExpand) {
    btnExpand.addEventListener('click', function() {
      document.querySelectorAll('.app-tree-children').forEach(el => el.style.display = 'block');
      document.querySelectorAll('[id^="chevron-"]').forEach(el => el.style.transform = 'rotate(90deg)');
    });
  }

  if (btnCollapse) {
    btnCollapse.addEventListener('click', function() {
      document.querySelectorAll('.app-tree-children').forEach(el => el.style.display = 'none');
      document.querySelectorAll('[id^="chevron-"]').forEach(el => el.style.transform = 'rotate(0deg)');
    });
  }
});
</script>
@endsection
