@props(['label', 'value' => null, 'hint' => null, 'icon' => null, 'accent' => 'primary'])

@php
    $accents = [
        'primary' => 'bg-primary-soft text-primary-dark',
        'accent' => 'bg-accent-soft text-accent',
        'success' => 'bg-success-soft text-success',
        'warning' => 'bg-warning-soft text-warning',
        'danger' => 'bg-danger-soft text-danger',
        'info' => 'bg-info-soft text-info',
    ];

    $iconClasses = $accents[$accent] ?? $accents['primary'];
    $hasValue = $value !== null && $value !== '';
@endphp

<div {{ $attributes->class(['flex items-start gap-4 rounded-xl border border-text-muted/10 bg-surface p-5 shadow-sm']) }}>
    @if ($icon)
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg {{ $iconClasses }}">
            {{ $icon }}
        </span>
    @endif

    <div class="min-w-0">
        <p class="text-sm text-text-muted">{{ $label }}</p>

        @if ($hasValue)
            <p class="mt-1 text-2xl font-semibold text-text">{{ $value }}</p>
        @else
            <p class="mt-1 text-2xl font-semibold text-text-muted">Sin datos</p>
        @endif

        @if ($hint)
            <p class="mt-1 text-xs text-text-muted">{{ $hint }}</p>
        @endif
    </div>
</div>
