@props(['href' => null, 'active' => false, 'disabled' => false, 'icon' => null])

@if ($disabled)
    <span {{ $attributes->class(['ecolact-sidebar-link is-disabled']) }} aria-disabled="true" title="Próximamente">
        @if ($icon)<x-layout.sidebar-icon :name="$icon" />@endif
        <span>{{ $slot }}</span>
    </span>
@else
    <a href="{{ $href }}" {{ $attributes->class(['ecolact-sidebar-link', 'is-active' => $active]) }} @if ($active) aria-current="page" @endif>
        @if ($icon)<x-layout.sidebar-icon :name="$icon" />@endif
        <span>{{ $slot }}</span>
    </a>
@endif
