{{--
    MODAL "Nueva producción" (ADMINISTRADOR y ENCARGADO_PLANTA). Se abre desde el historial de Producción;
    no es una página. La persona elige QUÉ producto y CUÁNTO; en cuanto ambos están completos se muestran
    los requerimientos que calcula el SERVIDOR con la receta activa (aquí no se calcula nada).
    Elegir, cambiar cantidades y previsualizar NUNCA descuentan nada: solo "Iniciar producción".
    Variables: $leche (Insumo), $productos (id, nombre, etiqueta, unidad, imagen), $rutas (requerimientos, recetas, store),
    $puedeConfigurarRecetas, $abrirModal (bool).
--}}
<x-ui.modal id="modal-nueva-produccion" title="Nueva producción" subtitle="Selecciona qué fabricar. EcoLact calculará automáticamente los insumos necesarios." size="lg">
    <div
        class="mt-5 space-y-5"
        data-produccion-nueva
        data-url-requerimientos="{{ $rutas['requerimientos'] }}"
        data-productos="{{ json_encode($productos) }}"
        data-lineas-iniciales="{{ json_encode(collect(old('productos', []))->values()) }}"
        data-abrir="{{ $abrirModal ? '1' : '0' }}"
    >
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
                <template data-icono="trash"><x-ui.icon name="trash" class="h-4 w-4" /></template>
                <template data-icono="check"><x-ui.icon name="check-circle" class="h-4 w-4" /></template>
                <template data-icono="alerta"><x-ui.icon name="alert-circle" class="h-4 w-4" /></template>
                <template data-icono="leche"><x-ui.icon name="droplet" class="h-4 w-4" /></template>

                {{-- 1 y 2: qué y cuánto. Las filas las arma el JavaScript con los productos de recetas activas. --}}
                <section class="space-y-3" aria-label="Qué vas a fabricar">
                    <div class="space-y-3" data-lineas></div>
                    <button type="button" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:text-primary-dark" data-agregar-linea>
                        <x-ui.icon name="plus" class="h-4 w-4" /> Agregar otro producto
                    </button>
                    <div data-inputs-ocultos></div>
                </section>

                <p class="rounded-lg bg-primary-soft/40 py-4 text-center text-sm text-text-muted" data-sin-calculo>
                    Elige un producto y la cantidad para ver los insumos necesarios.
                </p>

                <p class="flex items-center gap-2 text-sm text-text-muted" data-calculando hidden role="status">
                    <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" aria-hidden="true"></span>
                    Calculando insumos...
                </p>

                {{-- 3 y 4: qué insumos se necesitan y si alcanza el stock (calculado por el servidor). --}}
                <section class="space-y-3 rounded-xl border border-text-muted/15 p-4" data-recursos hidden aria-live="polite">
                    <div class="space-y-1" data-resumen-lineas></div>

                    <h3 class="pt-1 text-xs font-semibold uppercase tracking-wide text-text-muted">Requerimientos</h3>
                    <ul class="divide-y divide-text-muted/10" data-recursos-cuerpo></ul>
                </section>

                <div class="space-y-2" data-avisos aria-live="polite"></div>

                <x-forms.textarea name="observacion" label="Observación (opcional)" :rows="2" placeholder="Producción turno tarde." />

                <div class="flex flex-col-reverse gap-3 border-t border-text-muted/10 pt-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-text-muted">Al iniciar se descuentan estos insumos. El producto se suma al stock cuando finalices el lote.</p>
                    <div class="flex shrink-0 justify-end gap-2">
                        <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                        <x-ui.button type="submit" disabled data-iniciar>Iniciar producción</x-ui.button>
                    </div>
                </div>
            </form>
        @endif
    </div>
</x-ui.modal>
