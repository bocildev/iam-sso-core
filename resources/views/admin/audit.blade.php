@extends('admin.layout')
@section('title', 'Audit Log Keamanan')
@section('admin')
<div class="page-head">
  <div>
    <h1>Audit Log Keamanan (Immutable / Read-Only)</h1>
    <p>Rekam jejak seluruh aktivitas autentikasi, penerbitan token, perubahan RBAC, dan administrasi kunci.</p>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Waktu</th>
          <th>Aksi</th>
          <th>Severity</th>
          <th>Aktor</th>
          <th>Target</th>
          <th>Hasil</th>
          <th>Alamat IP</th>
        </tr>
      </thead>
      <tbody>
        @forelse($logs as $l)
          <tr>
            <td style="font-family:var(--font-mono);font-size:12px;white-space:nowrap">{{ $l->created_at }}</td>
            <td><code>{{ $l->action }}</code></td>
            <td><x-badge :variant="$l->severity ?? 'neutral'" :dot="false">{{ $l->severity ?? 'info' }}</x-badge></td>
            <td>{{ $l->actor_type }} {{ $l->actor_id }}</td>
            <td>{{ $l->target ?: '-' }}</td>
            <td><x-badge :variant="$l->result">{{ $l->result }}</x-badge></td>
            <td class="muted" style="font-family:var(--font-mono);font-size:12px">{{ $l->ip }}</td>
          </tr>
        @empty
          <tr><td colspan="7" class="muted">Belum ada catatan audit.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div style="margin-top:16px">
    {{ $logs->links() }}
  </div>
</div>
@endsection
