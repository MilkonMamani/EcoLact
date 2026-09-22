@props(['as' => 'button', 'href' => null, 'variant' => 'primary', 'size' => 'md', 'type' => 'submit'])

@php
    $variants = [
        'primary' => 'bg-primary text-white hover:bg-primary-dark focus-visible:outline-primary',
        'accent' => 'bg-accent text-white hover:bg-accent/90 focus-visible:outline-accent',
        'secondary' => 'bg-primary-soft text-primary-dark hover:bg-primary-soft/70',
        'ghost' => 'bg-transparent text-text hover:bg-text-muted/10',
        'danger' => 'bg-danger text-white hover:bg-danger/90',
    ];

    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-5 py-2.5 text-base',
    ];

    $classes = 'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50 '
        . ($variants[$variant] ?? $variants['primary']) . ' ' . ($sizes[$size] ?? $sizes['md']);

    $tag = $href ? 'a' : $as;
@endphp

<{{ $tag }}
    {{ $attributes->class($classes) }}
    @if ($href) href="{{ $href }}" @else type="{{ $type }}" @endif
>{{ $slot }}</{{ $tag }}>
