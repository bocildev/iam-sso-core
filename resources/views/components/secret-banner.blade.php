@props(['label', 'value', 'hint' => 'Salin dan simpan di tempat aman. Nilai ini hanya ditampilkan satu kali.'])
<div class="secret-banner" role="region" aria-label="{{ $label }}">
    <div class="secret-banner-head">
        <div style="display:flex;align-items:center;gap:8px">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
            <strong>{{ $label }}</strong>
        </div>
        <button type="button" class="btn btn-sm btn-ghost" data-copy="{{ $value }}" aria-label="Salin {{ $label }}">
            Salin
        </button>
    </div>
    <p class="muted" style="margin:4px 0 8px;font-size:12.5px">{{ $hint }}</p>
    <code class="secret-code">{{ $value }}</code>
</div>
