@props(['title' => 'Apa ini? Penjelasan singkat', 'key' => null])
<details class="help" @if($key) data-help-key="{{ $key }}" @endif open>
    <summary>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <span>{{ $title }}</span>
    </summary>
    <div class="help-body">
        {{ $slot }}
        <p class="help-more">Bingung dengan istilahnya? Buka <a href="/admin/glossary">Kamus Istilah</a>.</p>
    </div>
</details>
