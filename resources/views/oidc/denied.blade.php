@extends('layouts.app')
@section('title', 'Akses ditolak')
@section('content')
<div class="card" style="max-width:520px;margin:10vh auto">
  <h2 style="margin-top:0" class="err">Akses ditolak</h2>
  <p>Akun Anda tidak memiliki akses ke <strong>{{ $app->name }}</strong>. Hubungi administrator.</p>
  <form method="post" action="/logout">@csrf<button class="btn-ghost btn" type="submit">Keluar</button></form>
</div>
@endsection
