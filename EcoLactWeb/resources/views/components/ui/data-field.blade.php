@props(['label', 'icon' => null, 'muted' => false])

{{-- Par etiqueta/valor de las fichas de detalle: la etiqueta es pequeña, gris y en mayúsculas; el valor, oscuro y más grande. --}}
<div {{ $attributes->class(['min-w-0']) }}>
    <dt class="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wide text-text-muted">
        @if ($icon)
            <x-ui.icon :name="$icon" class="h-3.5 w-3.5 shrink-0" />
        @endif
        {{ $label }}
    </dt>
    <dd @class(['mt-1 break-words', 'text-base font-semibold text-text' => ! $muted, 'text-sm text-text-muted' => $muted])>{{ $slot }}</dd>
</div>
