@props(['title' => 'No hay datos', 'description' => null, 'icon' => true])

<div {{ $attributes->class(['flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-text-muted/25 bg-background/60 px-6 py-12 text-center']) }}>
    @if ($icon)
        <svg class="h-10 w-10 text-text-muted/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 7.5h16.5M4.5 7.5v11.25A1.5 1.5 0 0 0 6 20.25h12a1.5 1.5 0 0 0 1.5-1.5V7.5M8.25 7.5V5.25A1.5 1.5 0 0 1 9.75 3.75h4.5a1.5 1.5 0 0 1 1.5 1.5V7.5" />
        </svg>
    @endif

    <p class="text-sm font-medium text-text">{{ $title }}</p>

    @if ($description)
        <p class="max-w-sm text-sm text-text-muted">{{ $description }}</p>
    @endif

    @isset($action)
        <div class="mt-2">{{ $action }}</div>
    @endisset
</div>
