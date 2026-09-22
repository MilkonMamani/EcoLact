{{--
    MODAL "Nueva producción" (ADMINISTRADOR y ENCARGADO_PLANTA). Se abre desde el historial de Producción;
    no es una página. La persona elige QUÉ producto y CUÁNTO; el servidor calcula los insumos con la receta.
    Abrir, agregar, cambiar cantidades y previsualizar NUNCA descuentan nada: solo "Iniciar producción".
    Variables: $leche (Insumo), $productos (id, nombre, unidad, imagen), $rutas (requerimientos, recetas, store),
    $puedeConfigurarRecetas, $abrirModal (bool).
--}}
<x-ui.modal id="modal-nueva-produccion" title="Nueva producción" size="lg">
    <div
        class="space-y-5"
        data-produccion-nueva
        data-url-requerimientos="{{ $rutas['requerimientos'] }}"
        data-productos="{{ json_encode($productos) }}"
        data-lineas-iniciales="{{ json_encode(collect(old('productos', []))->values()) }}"
        data-abrir="{{ $abrirModal ? '1' : '0' }}"
    >
        <p class="-mt-3 text-sm text-text-muted">Selecciona los productos a fabricar. Los insumos se descuentan al iniciar la producción.</p>

        <p class="inline-flex items-center gap-2 rounded-lg bg-info/10 px-3 py-2 text-sm text-primary-dark">
            <x-ui.icon name="droplet" class="h-5 w-5" />
            <span>Leche disponible:</span>
            <strong class="font-semibold">{{ rtrim(rtrim(number_format($leche->stockActual(), 2), '0'), '.') }} {{ $leche->unidadBase() }}</strong>
        </p>

        @if (count($productos) === 0)
            <div class="rounded-xl border border-dashed border-text-muted/30 bg-primary-soft/30 p-6 text-center">
                <p class="text-sm font-semibold text-text">No hay productos disponibles para producir.</p>
                <p class="mt-1 text-sm text-text-muted">Primero configura una receta.</p>
                @if ($puedeConfigurarRecetas)
                    <x-ui.button :href="$rutas['recetas']" size="sm" as="a" class="mt-4">Ir a Recetas</x-ui.button>
                @endif
            </div>
            <div class="flex justify-end">
                <x-ui.button type="button" data-modal-close variant="ghost">Cerrar</x-ui.button>
            </div>
        @else
            <form method="POST" action="{{ $rutas['store'] }}" class="space-y-5" data-produccion-form>
                @csrf

                {{-- Íconos que usa el JavaScript (se dibujan aquí para reutilizar el catálogo de EcoLact). --}}
                <template data-icono="trash"><x-ui.icon name="trash" class="h-[1.125rem] w-[1.125rem]" /></template>
                <template data-icono="check"><x-ui.icon name="check-circle" class="h-4 w-4" /></template>
                <template data-icono="alerta"><x-ui.icon name="alert-circle" class="h-4 w-4" /></template>

                <section class="space-y-3">
                    <h3 class="text-sm font-semibold text-text">Agregar productos</h3>
                    <div class="grid gap-3 sm:grid-cols-12 sm:items-end">
                        <div class="sm:col-span-6">
                            <x-forms.select name="producto_picker" id="producto_picker" label="Producto" :options="collect($productos)->mapWithKeys(fn ($p) => [$p['id'] => $p['nombre']])->all()" placeholder="Elegir un producto" :sticky="false" data-producto-select />
                        </div>
                        <div class="sm:col-span-3">
                            <x-forms.input name="cantidad_picker" id="cantidad_picker" type="number" step="any" min="0" label="Cantidad" placeholder="Ej. 100" :sticky="false" data-cantidad-input />
                        </div>
                        <div class="sm:col-span-3">
                            <x-ui.button type="button" variant="secondary" class="w-full" data-agregar>
                                <x-ui.icon name="plus" class="h-4 w-4" /> Agregar
                            </x-ui.button>
                        </div>
                    </div>
                    <p class="text-xs text-danger" data-error-cantidad role="alert"></p>

                    <div data-tabla-lineas hidden>
                        <x-tables.table>
                            <thead>
                                <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                                    <th class="py-2 pr-3">Imagen</th>
                                    <th class="py-2 pr-3">Producto</th>
                                    <th class="py-2 pr-3">Cantidad</th>
                                    <th class="py-2 pl-3 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody data-lineas></tbody>
                        </x-tables.table>
                    </div>
                    <p class="rounded-lg bg-primary-soft/40 py-4 text-center text-sm text-text-muted" data-lineas-vacio>Todavía no agregaste ningún producto.</p>
                    <div data-inputs-ocultos></div>
                </section>

                <p class="flex items-center gap-2 text-sm text-text-muted" data-calculando hidden role="status">
                    <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" aria-hidden="true"></span>
                    Calculando insumos...
                </p>

                <div class="space-y-2" data-avisos aria-live="polite"></div>

                <section class="space-y-3" data-recursos hidden>
                    <h3 class="text-sm font-semibold text-text">Resumen de insumos requeridos</h3>
                    <x-tables.table>
                        <thead>
                            <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                                <th class="py-2 pr-3">Insumo</th>
                                <th class="py-2 pr-3">Necesario</th>
                                <th class="py-2 pr-3">Disponible</th>
                                <th class="py-2 pr-3">Estado</th>
                            </tr>
                        </thead>
                        <tbody data-recursos-cuerpo></tbody>
                    </x-tables.table>
                </section>

                <x-forms.textarea name="observacion" label="Observación (opcional)" :rows="2" placeholder="Producción turno tarde." />

                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-text-muted/10 pt-4">
                    <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                    <x-ui.button type="submit" disabled data-iniciar>Iniciar producción</x-ui.button>
                </div>
            </form>
        @endif
    </div>
</x-ui.modal>
