@props(['label' => null, 'name', 'required' => false, 'value' => null, 'rows' => 4, 'id' => null, 'sticky' => true])

@php
    $campoId = $id ?? $name;
    $tieneError = $sticky && $errors->has($name);
@endphp

<div>
    @if ($label)
        <x-forms.label :for="$campoId" :required="$required">{{ $label }}</x-forms.label>
    @endif

    <textarea
        id="{{ $campoId }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @if ($required) required @endif
        {{ $attributes->class([
            'w-full rounded-lg border bg-surface px-3 py-2 text-sm text-text shadow-sm transition-colors placeholder:text-text-muted/60 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary',
            'border-danger' => $tieneError,
            'border-text-muted/25' => ! $tieneError,
        ]) }}
    >{{ $sticky ? old($name, $value) : $value }}</textarea>

    @if ($sticky)
        <x-forms.error :name="$name" />
    @endif
</div>
