@extends('admin.layout')
@section('title', 'Registry Aplikasi')
@section('admin')
<div class="page-head">
  <div>
    <h1>Registry Aplikasi Klien</h1>
    <p>Kelola klien OIDC (PKCE S256 / Confidential), izin CORS, API Key mesin, dan pengiriman webhook HMAC.</p>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2 class="card-title">Daftar Aplikasi Terdaftar ({{ $apps->count() }})</h2>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Nama &amp; Slug</th>
          <th>Client ID</th>
          <th>Tipe Klien</th>
          <th>Mode Auth</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($apps as $a)
          <tr>
            <td>
              <a href="/admin/apps/{{ $a->id }}" style="font-weight:600">{{ $a->name }}</a>
              <div class="muted" style="font-size:12px">{{ $a->slug }}</div>
            </td>
            <td><code>{{ $a->client_id }}</code></td>
            <td>
              @if($a->isPublicClient())
                <x-badge variant="info" :dot="false">Public (PKCE)</x-badge>
              @else
                <x-badge variant="neutral" :dot="false">Confidential</x-badge>
              @endif
            </td>
            <td><x-badge :variant="$a->auth_mode" :dot="false">{{ strtoupper($a->auth_mode) }}</x-badge></td>
            <td><x-badge :variant="$a->status">{{ $a->status }}</x-badge></td>
            <td style="text-align:right">
              <a href="/admin/apps/{{ $a->id }}" class="btn btn-sm btn-ghost">Konfigurasi</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="muted">Belum ada aplikasi terdaftar.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">Daftarkan Aplikasi Baru</h3>
  </div>
  <form method="post" action="/admin/apps">
    @csrf
    <div class="grid-2">
      <div>
        <label for="app-name">Nama Aplikasi</label>
        <input id="app-name" name="name" required placeholder="Mis. Motion Portfolio">
      </div>
      <div>
        <label for="app-slug">Slug Unik</label>
        <input id="app-slug" name="slug" required placeholder="Mis. motion-portfolio">
      </div>
    </div>

    <label for="app-redirects">Redirect URI (satu URI per baris, pencocokan ketat)</label>
    <textarea id="app-redirects" name="redirect_uris" rows="3" required placeholder="http://localhost:3000/auth/callback"></textarea>

    <label class="checkbox-row">
      <input type="checkbox" name="confidential" value="1">
      <span><strong>Confidential Client</strong> (menerbitkan Client Secret). Kosongkan untuk SPA / Nuxt / Mobile dengan <strong>PKCE S256</strong>.</span>
    </label>

    <button type="submit">Daftarkan Aplikasi</button>
  </form>
</div>
@endsection
