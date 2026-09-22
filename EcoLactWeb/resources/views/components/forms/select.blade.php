@props(['label' => null, 'name', 'required' => false, 'value' => null, 'options' => [], 'placeholder' => 'Selecciona una opción', 'id' => null, 'sticky' => true])

@php
    $campoId = $id ?? $name;
    $tieneError = $sticky && $errors->has($name);
    $valorActual = $sticky ? old($name, $value) : $value;
@endphp

<div>
    @if ($label)
        <x-forms.label :for="$campoId" :required="$required">{{ $label }}</x-forms.label>
    @endif

    <select
        id="{{ $campoId }}"
        name="{{ $name }}"
        @if ($required) required @endif
        {{ $attributes->class([
            'w-full rounded-lg border bg-surface px-3 py-2 text-sm text-text shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary',
            'border-danger' => $tieneError,
            'border-text-muted/25' => ! $tieneError,
        ]) }}
    >
        @if ($placeholder)
            <option value="" @if ($valorActual === null) selected @endif>{{ $placeholder }}</option>
        @endif

        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $valorActual === (string) $optionValue)>
                {{ $optionLabel }}
            </option>
        @endforeach

        {{ $slot }}
    </select>

    @if ($sticky)
        <x-forms.error :name="$name" />
    @endif
</div>
