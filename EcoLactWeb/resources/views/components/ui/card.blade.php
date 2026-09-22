@props(['title' => null, 'subtitle' => null, 'padded' => true])

<section {{ $attributes->class(['bg-surface rounded-xl border border-text-muted/10 shadow-sm']) }}>
    @if ($title || $subtitle || isset($actions))
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-text-muted/10 px-5 py-4">
            <div>
                @if ($title)
                    <h2 class="text-base font-semibold text-text">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 text-sm text-text-muted">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    @endif

    <div class="{{ $padded ? 'p-5' : '' }}">
        {{ $slot }}
    </div>
</section>
