@props(['variant' => 'neutral', 'dot' => true])
@php
$map = [
    'success' => 'badge-success',
    'active' => 'badge-success',
    'delivered' => 'badge-success',
    'warning' => 'badge-warning',
    'pending' => 'badge-warning',
    'both' => 'badge-warning',
    'danger' => 'badge-danger',
    'suspended' => 'badge-danger',
    'revoked' => 'badge-danger',
    'failed' => 'badge-danger',
    'failure' => 'badge-danger',
    'high' => 'badge-danger',
    'info' => 'badge-info',
    'oidc' => 'badge-info',
    'neutral' => 'badge-neutral',
    'legacy' => 'badge-neutral',
];
$cls = $map[strtolower((string) $variant)] ?? 'badge-neutral';
@endphp
<span {{ $attributes->merge(['class' => "badge {$cls}"]) }}>
    @if($dot)<span class="badge-dot" aria-hidden="true"></span>@endif
    {{ $slot }}
</span>
