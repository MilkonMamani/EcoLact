@props(['variant' => 'info', 'dismissible' => true])

@php
    $variants = [
        'success' => ['bg-success/10 text-success ring-success/20', 'M5 13l4 4L19 7'],
        'error' => ['bg-danger/10 text-danger ring-danger/20', 'M6 18L18 6M6 6l12 12'],
        'warning' => ['bg-warning/10 text-warning ring-warning/20', 'M12 9v3.75m0 3.75h.008M10.29 3.86 1.82 18a1.5 1.5 0 0 0 1.3 2.25h17.76a1.5 1.5 0 0 0 1.3-2.25L13.71 3.86a1.5 1.5 0 0 0-2.42 0Z'],
        'info' => ['bg-info/10 text-info ring-info/20', 'M11.25 11.25h1.5v4.5h-1.5v-4.5Zm0-3.75h1.5v1.5h-1.5v-1.5Z'],
    ];

    [$classes, $icon] = $variants[$variant] ?? $variants['info'];
@endphp

<div {{ $attributes->class(['flex items-start gap-3 rounded-lg px-4 py-3 text-sm ring-1 ring-inset', $classes]) }} role="alert">
    <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
    </svg>

    <div class="flex-1">{{ $slot }}</div>

    @if ($dismissible)
        <button type="button" onclick="this.closest('[role=alert]').remove()" class="shrink-0 text-current/70 hover:text-current" aria-label="Cerrar mensaje">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
    @endif
</div>
