@props(['label' => null, 'name', 'type' => 'text', 'required' => false, 'value' => null, 'id' => null, 'sticky' => true])

@php
    $campoId = $id ?? $name;
    $tieneError = $sticky && $errors->has($name);
@endphp

<div>
    @if ($label)
        <x-forms.label :for="$campoId" :required="$required">{{ $label }}</x-forms.label>
    @endif

    <input
        id="{{ $campoId }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($required) required @endif
        value="{{ $sticky ? old($name, $value) : $value }}"
        {{ $attributes->class([
            'w-full rounded-lg border bg-surface px-3 py-2 text-sm text-text shadow-sm transition-colors placeholder:text-text-muted/60 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary',
            'border-danger' => $tieneError,
            'border-text-muted/25' => ! $tieneError,
        ]) }}
    />

    @if ($sticky)
        <x-forms.error :name="$name" />
    @endif
</div>
