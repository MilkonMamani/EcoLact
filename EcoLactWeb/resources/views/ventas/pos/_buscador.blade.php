{{--
    Buscador de comprador / proveedor: se escribe, se elige de la lista y queda como ficha con
    "Cambiar". El id elegido va en el hidden $campo. Las opciones van en JSON (filtrado local).
    @param string $tipo  comprador|proveedor
--}}
<div class="vpos-buscador" data-vpos-buscador="{{ $tipo }}" data-vacio="{{ $vacio }}">
    <input type="hidden" name="{{ $campo }}" value="{{ $seleccion['id'] ?? '' }}" data-vpos-buscador-valor>

    <div class="vpos-ficha" data-vpos-buscador-ficha @if (! $seleccion) hidden @endif>
        <span class="vpos-ficha-avatar" aria-hidden="true"><x-ui.icon :name="$tipo === 'proveedor' ? 'milk-jar' : 'user'" class="h-4 w-4" /></span>
        <span class="min-w-0">
            <span class="vpos-ficha-nombre" data-vpos-buscador-nombre>{{ $seleccion['nombre'] ?? '' }}</span>
            <span class="vpos-ficha-doc" data-vpos-buscador-doc>{{ $seleccion['documento'] ?? '' }}</span>
        </span>
        <button type="button" class="vpos-link" data-vpos-buscador-cambiar>Cambiar</button>
    </div>

    <div class="vpos-buscador-caja" data-vpos-buscador-caja @if ($seleccion) hidden @endif>
        <label class="fichas-search vpos-search">
            <span class="sr-only">{{ $placeholder }}</span>
            <x-ui.icon name="magnifying-glass" class="h-[18px] w-[18px] shrink-0" />
            <input type="search" placeholder="{{ $placeholder }}" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="vpos-lista-{{ $tipo }}" data-vpos-buscador-texto>
        </label>
        <ul id="vpos-lista-{{ $tipo }}" class="vpos-lista" role="listbox" data-vpos-buscador-lista hidden></ul>
    </div>

    <script type="application/json" data-vpos-buscador-opciones>@json($opciones)</script>
</div>
