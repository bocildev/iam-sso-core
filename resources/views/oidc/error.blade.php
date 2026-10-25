@extends('layouts.app')
@section('title', 'Permintaan tidak valid')
@section('content')
<div class="card" style="max-width:520px;margin:10vh auto">
  <h2 style="margin-top:0" class="err">Permintaan tidak valid</h2>
  <p><code>{{ $e->error }}</code></p>
  <p class="muted">{{ $e->description }}</p>
</div>
@endsection
