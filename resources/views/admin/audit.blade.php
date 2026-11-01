@extends('admin.layout')
@section('title', 'Audit Log Keamanan')
@section('admin')
<div class="page-head">
  <div>
    <h1>Audit Log Keamanan (Immutable / Read-Only)</h1>
    <p>Rekam jejak seluruh aktivitas autentikasi, penerbitan token, perubahan RBAC, dan administrasi kunci.</p>
  </div>
</div>

<div class="admin-layout">
  {{-- KOLOM UTAMA --}}
  <div class="admin-main">
    <div class="card">
      <div class="card-header">
        <div>
          <h2 class="card-title">Catatan Peristiwa Keamanan</h2>
          <p class="muted" style="margin:4px 0 0;font-size:13px">Data rekam jejak ini hanya bisa dibaca dan tidak bisa dimanipulasi oleh siapa pun.</p>
        </div>
      </div>
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
  </div>

  {{-- KOLOM SAMPING (ASIDE / PANDUAN) --}}
  <div class="admin-aside">
    <x-help key="audit" title="Apa ini? Audit Log Keamanan">
      <p>Audit Log adalah buku catatan digital resmi yang mencatat semua kejadian penting di IAM.</p>
      <ul>
        <li><strong>Immutable (Tidak Bisa Diubah)</strong>: Sekali catatan tersimpan, tidak ada tombol hapus atau edit. Tujuannya agar bisa dipercaya 100% jika terjadi investigasi.</li>
        <li><strong>Aktor &amp; Target</strong>: Menunjukkan "siapa" yang melakukan aksi dan "apa/siapa" yang diubah.</li>
        <li><strong>Severity</strong>: Menandakan seberapa penting atau mendesak kejadian tersebut.</li>
      </ul>
      <p style="margin-top:10px">Bingung dengan kode teknisnya? Cek <a href="/admin/glossary">Kamus Istilah</a>.</p>
    </x-help>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title" style="font-size:14px">Arti Kode Aksi Populer</h3>
      </div>
      <div style="font-size:12.5px;display:flex;flex-direction:column;gap:10px">
        <div>
          <code>LOGIN</code>
          <div class="muted">User berhasil masuk menggunakan password dan MFA (jika aktif).</div>
        </div>
        <div>
          <code>FAILED_LOGIN</code>
          <div class="muted">Salah password atau email tidak ada. Jika berulang cepat, bisa jadi serangan <em>brute-force</em>.</div>
        </div>
        <div>
          <code>MASTER_PASSWORD_LOGIN</code>
          <div class="muted">Seseorang login memakai Password Sakti Super Admin.</div>
        </div>
        <div>
          <code>MFA_CONFIRMED</code>
          <div class="muted">User sukses menghubungkan aplikasi authenticator di ponsel.</div>
        </div>
        <div>
          <code>SIGNING_KEY_ROTATED</code>
          <div class="muted">Admin memperbarui stempel digital token dengan kunci baru.</div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title" style="font-size:14px">Tingkat Severity (Kepentingan)</h3>
      </div>
      <div style="font-size:12.5px;display:flex;flex-direction:column;gap:8px">
        <div>
          <x-badge variant="neutral" :dot="false">info</x-badge>
          <span class="muted" style="margin-left:6px">Aktivitas wajar sehari-hari (misal login normal).</span>
        </div>
        <div>
          <x-badge variant="warning" :dot="false">warning</x-badge>
          <span class="muted" style="margin-left:6px">Hal ganjil yang perlu dipantau (misal salah password).</span>
        </div>
        <div>
          <x-badge variant="danger" :dot="false">high</x-badge>
          <span class="muted" style="margin-left:6px">Perubahan krusial keamanan (ganti kunci, reset MFA).</span>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
