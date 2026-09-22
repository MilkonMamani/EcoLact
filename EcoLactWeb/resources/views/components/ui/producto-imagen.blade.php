@props(['url' => null, 'alt' => '', 'size' => 'sm'])

@php
    // sm: ~52 px para tablas; lg: ficha de detalle.
    $caja = $size === 'lg' ? 'h-40 w-40 rounded-2xl' : 'h-[52px] w-[52px] rounded-lg';
@endphp

@if ($url)
    <img src="{{ $url }}" alt="{{ $alt }}" loading="lazy" {{ $attributes->class([$caja, 'shrink-0 border border-text-muted/10 bg-primary-soft/40 object-cover']) }}>
@else
    <span role="img" aria-label="{{ $alt !== '' ? $alt.' (sin imagen)' : 'Sin imagen' }}" {{ $attributes->class([$caja, 'flex shrink-0 items-center justify-center border border-dashed border-text-muted/25 bg-primary-soft/40 text-text-muted']) }}>
        <svg class="{{ $size === 'lg' ? 'h-12 w-12' : 'h-6 w-6' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.16-5.16a2.25 2.25 0 0 1 3.18 0l5.16 5.16m-1.5-1.5 1.41-1.41a2.25 2.25 0 0 1 3.18 0l2.25 2.25M3.75 19.5h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Z" /></svg>
    </span>
@endif
