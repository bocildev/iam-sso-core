@extends('admin.layout')
@section('title', $app->name)
@section('admin')
<div class="page-head">
  <div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <h1>{{ $app->name }}</h1>
      <x-badge :variant="$app->status">{{ $app->status }}</x-badge>
      <x-badge variant="info" :dot="false">{{ $app->isPublicClient() ? 'Public Client (PKCE S256)' : 'Confidential Client' }}</x-badge>
    </div>
    <p>Client ID: <code>{{ $app->client_id }}</code> · Slug: <code>{{ $app->slug }}</code></p>
  </div>
  <a href="/admin/apps" class="btn btn-sm btn-ghost">&larr; Semua Aplikasi</a>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Konfigurasi OIDC, CORS &amp; Webhook</h2>
    </div>
    <form method="post" action="/admin/apps/{{ $app->id }}">
      @csrf
      <label for="f-name">Nama Aplikasi</label>
      <input id="f-name" name="name" value="{{ $app->name }}" required>

      <label for="f-redirects">Redirect URI (satu per baris)</label>
      <textarea id="f-redirects" name="redirect_uris" rows="3" required>{{ implode("\n", $app->redirect_uris ?? []) }}</textarea>

      <label for="f-logout-uris">Post-Logout Redirect URI</label>
      <textarea id="f-logout-uris" name="post_logout_redirect_uris" rows="2">{{ implode("\n", $app->post_logout_redirect_uris ?? []) }}</textarea>

      <label for="f-origins">Allowed CORS Origins (satu per baris, mis. http://localhost:3000)</label>
      <textarea id="f-origins" name="allowed_origins" rows="2">{{ implode("\n", $app->allowed_origins ?? []) }}</textarea>

      <label for="f-bclogout">Back-Channel Logout URI</label>
      <input id="f-bclogout" name="backchannel_logout_uri" value="{{ $app->backchannel_logout_uri }}" placeholder="https://app.example.com/api/oidc/backchannel-logout">

      <label for="f-webhook">Webhook URL (Event HMAC <code>user.updated</code>, <code>user.disabled</code>, <code>role.changed</code>)</label>
      <input id="f-webhook" name="webhook_url" value="{{ $app->webhook_url }}" placeholder="https://app.example.com/api/iam/webhook">

      <div class="grid-2">
        <div>
          <label for="f-mode">Mode Autentikasi</label>
          <select id="f-mode" name="auth_mode">
            @foreach(['legacy','oidc','both'] as $m)
              <option value="{{ $m }}" @selected($app->auth_mode===$m)>{{ $m }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label for="f-status">Status Aplikasi</label>
          <select id="f-status" name="status">
            @foreach(['active','suspended'] as $m)
              <option value="{{ $m }}" @selected($app->status===$m)>{{ $m }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <button type="submit">Simpan Perubahan</button>
    </form>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
      @unless($app->isPublicClient())
        <form method="post" action="/admin/apps/{{ $app->id }}/rotate-secret">
          @csrf
          <button class="btn-danger btn btn-sm" type="submit">Rotasi Client Secret</button>
        </form>
      @endunless
      <form method="post" action="/admin/apps/{{ $app->id }}/rotate-webhook-secret">
        @csrf
        <button class="btn-ghost btn btn-sm" type="submit">Rotasi Webhook Secret</button>
      </form>
    </div>
  </div>

  <div>
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Role &amp; Permission (Isolated Claims)</h3>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Role</th><th>Permission Terikat</th></tr></thead>
          <tbody>
            @forelse($app->roles as $r)
              <tr>
                <td><strong>{{ $r->name }}</strong> <code>{{ $r->key }}</code></td>
                <td>
                  @forelse($r->permissions as $p)
                    <code style="margin:2px">{{ $p->key }}</code>
                  @empty
                    <span class="muted">-</span>
                  @endforelse
                </td>
              </tr>
            @empty
              <tr><td colspan="2" class="muted">Belum ada role untuk aplikasi ini.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <form method="post" action="/admin/apps/{{ $app->id }}/roles" style="margin-top:14px">
        @csrf
        <div class="grid-2">
          <div>
            <label for="r-key">Key Role</label>
            <input id="r-key" name="key" required placeholder="editor">
          </div>
          <div>
            <label for="r-name">Nama Role</label>
            <input id="r-name" name="name" required placeholder="Content Editor">
          </div>
        </div>
        <label for="r-perms">Daftar Permission (format <code>resource.action</code>, pisah koma)</label>
        <input id="r-perms" name="permissions" placeholder="project.read, project.write">
        <button type="submit" class="btn-sm">Simpan Role</button>
      </form>
    </div>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title">API Key (Machine-to-Machine Gateway)</h3>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Nama</th><th>Prefix</th><th>Scope</th><th>Status</th><th></th></tr></thead>
          <tbody>
            @forelse($keys as $k)
              <tr>
                <td>{{ $k->name }}</td>
                <td><code>{{ $k->prefix }}…</code></td>
                <td>
                  @foreach($k->scopes as $sc)
                    <code style="margin:1px">{{ $sc }}</code>
                  @endforeach
                </td>
                <td><x-badge :variant="$k->status">{{ $k->status }}</x-badge></td>
                <td style="text-align:right">
                  @if($k->status === 'active')
                    <form method="post" action="/admin/api-keys/{{ $k->id }}/revoke">
                      @csrf
                      <button class="btn-danger btn btn-sm" type="submit">Cabut</button>
                    </form>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="5" class="muted">Belum ada API key untuk aplikasi ini.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <form method="post" action="/admin/apps/{{ $app->id }}/api-keys" style="margin-top:14px">
        @csrf
        <div class="grid-2">
          <div>
            <label for="k-name">Nama Key</label>
            <input id="k-name" name="name" required placeholder="Server Backend Worker">
          </div>
          <div>
            <label for="k-scopes">Scope (pisah koma)</label>
            <input id="k-scopes" name="scopes" placeholder="iam:users.read, portfolio:read" required>
          </div>
        </div>
        <button type="submit" class="btn-sm">Buat API Key Baru</button>
      </form>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">Riwayat Outbox Webhook Terakhir</h3>
    <span class="muted" style="font-size:12.5px">Header: <code>X-IAM-Signature: sha256=&lt;hmac&gt;</code></span>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Waktu</th><th>Event</th><th>Status</th><th>HTTP Status</th><th>Percobaan</th></tr></thead>
      <tbody>
        @forelse($outbox as $ev)
          <tr>
            <td>{{ $ev->created_at }}</td>
            <td><code>{{ $ev->event_type }}</code></td>
            <td><x-badge :variant="$ev->status">{{ $ev->status }}</x-badge></td>
            <td>{{ $ev->last_http_status ?? '-' }}</td>
            <td>{{ $ev->attempts }}</td>
          </tr>
        @empty
          <tr><td colspan="5" class="muted">Belum ada pengiriman webhook.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
