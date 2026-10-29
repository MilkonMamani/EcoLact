{{--
    Una fila de "Productos a fabricar" del modal Nueva producción.
    Variables: $indice (int | '__i__' en la plantilla), $fila (producto_id, cantidad), $productos, $campo.
--}}
<li class="grid grid-cols-12 items-center gap-x-3 gap-y-2 rounded-xl border border-text-muted/10 p-3 sm:rounded-none sm:border-0 sm:p-0" data-fila>
    <div class="col-span-12 flex min-w-0 items-center gap-2 sm:col-span-6">
        <img src="" alt="" class="h-9 w-9 shrink-0 rounded-lg border border-text-muted/10 object-cover" data-fila-imagen hidden />
        <label for="produccion-producto-{{ $indice }}" class="sr-only">Producto</label>
        <select
            id="produccion-producto-{{ $indice }}"
            name="productos[{{ $indice }}][producto_id]"
            data-producto
            class="{{ $campo }}"
        >
            <option value="">Elegir un producto</option>
            @foreach ($productos as $producto)
                <option value="{{ $producto['id'] }}" data-unidad="{{ $producto['unidad'] }}" data-imagen="{{ $producto['imagen'] }}" @selected((string) ($fila['producto_id'] ?? '') === (string) $producto['id'])>{{ $producto['etiqueta'] }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-span-6 sm:col-span-3">
        <label for="produccion-cantidad-{{ $indice }}" class="mb-1 block text-xs text-text-muted sm:sr-only">Cantidad a producir</label>
        <input
            id="produccion-cantidad-{{ $indice }}"
            name="productos[{{ $indice }}][cantidad]"
            type="number"
            step="any"
            min="0"
            inputmode="decimal"
            placeholder="Ej. 10"
            value="{{ $fila['cantidad'] ?? '' }}"
            data-cantidad
            class="{{ $campo }}"
        />
    </div>
    <p class="col-span-4 self-end pb-2.5 text-sm text-text-muted sm:col-span-2 sm:self-center sm:pb-0" data-fila-unidad>—</p>
    <div class="col-span-2 flex justify-end self-end sm:col-span-1 sm:self-center">
        <button type="button" class="rounded-lg p-2 text-text-muted hover:bg-danger-soft hover:text-danger disabled:invisible" aria-label="Quitar producto" title="Quitar producto" data-quitar-fila>
            <x-ui.icon name="trash" class="h-4 w-4" />
        </button>
    </div>
</li>
