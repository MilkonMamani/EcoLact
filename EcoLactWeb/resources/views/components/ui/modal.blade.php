@props(['id', 'title' => null, 'size' => 'md', 'open' => false, 'nested' => false])

@php
    $widths = ['md' => 'max-w-md', 'lg' => 'max-w-2xl', 'xl' => 'max-w-3xl', '2xl' => 'max-w-4xl', 'form' => 'max-w-[46rem]'];
    $esForm = $size === 'form';
@endphp

<div id="{{ $id }}" data-modal class="@if ($open) is-open @endif fixed inset-0 {{ $nested ? 'z-[60]' : 'z-50' }} items-center justify-center {{ $esForm ? 'bg-text/50 p-2 sm:p-4' : 'bg-text/40 p-4' }}" role="dialog" aria-modal="true" @if ($title) aria-label="{{ $title }}" @endif>
    <div class="max-h-[92vh] w-full overflow-y-auto {{ $esForm ? 'rounded-2xl p-5 sm:p-7' : 'rounded-xl p-6' }} bg-surface shadow-xl {{ $widths[$size] ?? $widths['md'] }}">
        <div class="flex items-start justify-between gap-4">
            @if ($title)
                <h2 class="{{ $esForm ? 'text-xl font-bold' : 'text-base font-semibold' }} text-text">{{ $title }}</h2>
            @endif

            <button type="button" data-modal-close aria-label="Cerrar" class="ml-auto text-text-muted hover:text-text">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <div class="mt-4">
            {{ $slot }}
        </div>
    </div>
</div>
