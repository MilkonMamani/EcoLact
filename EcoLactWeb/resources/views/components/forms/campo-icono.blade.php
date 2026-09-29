@props([
    'name',
    'label',
    'icono',
    'type' => 'text',
    'required' => false,
    'options' => null,
    'placeholder' => null,
    'id' => null,
    'value' => null,
])

{{--
    Campo con ícono dentro (variante de x-forms.input / x-forms.select, que siguen intactos).
    Con :options se pinta un <select>; con type="password" agrega el botón para mostrar la clave.
    Conserva el valor anterior (old) y muestra el error de validación del campo.
--}}
@php
    $campoId = $id ?? $name;
    $tieneError = $errors->has($name);
    $valor = old($name, $value);
@endphp

<div {{ $attributes->only(['class', 'data-campo'])->class(['campo-icono']) }} @if ($attributes->get('hidden')) hidden @endif>
    <x-forms.label :for="$campoId" :required="$required">{{ $label }}</x-forms.label>

    <div @class(['campo-icono-caja', 'is-error' => $tieneError])>
        <x-ui.icon :name="$icono" class="campo-icono-simbolo h-[18px] w-[18px]" />

        @if ($options !== null)
            <select id="{{ $campoId }}" name="{{ $name }}" @if ($required) required @endif {{ $attributes->except(['class', 'data-campo', 'hidden']) }}>
                <option value="" @selected($valor === null || $valor === '')>{{ $placeholder ?? 'Selecciona una opción' }}</option>
                @foreach ($options as $valorOpcion => $etiqueta)
                    <option value="{{ $valorOpcion }}" @selected((string) $valor === (string) $valorOpcion)>{{ $etiqueta }}</option>
                @endforeach
            </select>
            <x-ui.icon name="chevron-down" class="campo-icono-flecha h-4 w-4" />
        @else
            <input id="{{ $campoId }}" name="{{ $name }}" type="{{ $type }}" @if ($type !== 'password') value="{{ $valor }}" @endif
                @if ($placeholder) placeholder="{{ $placeholder }}" @endif @if ($required) required @endif
                {{ $attributes->except(['class', 'data-campo', 'hidden']) }}>

            @if ($type === 'password')
                <button type="button" class="campo-icono-ver" data-ver-clave aria-label="Mostrar contraseña" aria-pressed="false">
                    <x-ui.icon name="eye" class="h-[18px] w-[18px]" data-ver-clave-mostrar />
                    <x-ui.icon name="eye-off" class="hidden h-[18px] w-[18px]" data-ver-clave-ocultar />
                </button>
            @endif
        @endif
    </div>

    <x-forms.error :name="$name" />
</div>
