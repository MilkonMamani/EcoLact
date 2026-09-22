@props(['value' => 0, 'max' => 100, 'label' => null])

@php
    $pct = $max > 0 ? min(100, max(0, (int) round($value / $max * 100))) : 0;
@endphp

<div {{ $attributes->class(['w-full']) }}>
    @if ($label)
        <div class="mb-1 flex items-center justify-between text-xs text-text-muted">
            <span>{{ $label }}</span>
            <span class="font-medium text-text">{{ $value }}/{{ $max }}</span>
        </div>
    @endif
    <div class="h-2 w-full overflow-hidden rounded-full bg-text-muted/10">
        <div class="h-full rounded-full bg-primary transition-all" style="width: {{ $pct }}%"></div>
    </div>
</div>
