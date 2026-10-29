{{--
    MODAL "Nueva producción" (ADMINISTRADOR y ENCARGADO_PLANTA). Se abre desde el historial de Producción;
    no es una página. Es una PLANIFICACIÓN: la persona agrega uno o varios productos con su cantidad y el
    SERVIDOR calcula, con la receta activa de cada uno, los insumos totales (convertidos a la unidad base y
    sumados entre productos) y si alcanza el stock (aquí no se calcula nada).
    Al iniciar se crea UN LOTE POR PRODUCTO en una sola transacción; cada lote descuenta su porción de
    insumos y se finaliza por separado. Previsualizar NUNCA descuenta nada.
    Cabecera y pie fijos; solo el cuerpo hace scroll.
    Variables: $leche (Insumo), $productos (id, nombre, etiqueta, unidad, imagen), $rutas (requerimientos, recetas, store),
    $puedeConfigurarRecetas, $abrirModal (bool).
--}}
@php
    $fmtLeche = rtrim(rtrim(number_format((float) $leche->stockActual(), 2), '0'), '.');
    // Al volver con error se reconstruyen las filas enviadas; si no, una fila vacía.
    $filasIniciales = array_values(array_filter((array) old('productos', []), 'is_array')) ?: [['producto_id' => '', 'cantidad' => '']];
    $campo = 'w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2.5 text-sm text-text shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary';
    $rotulo = 'text-xs font-semibold uppercase tracking-wide text-text-muted';
@endphp

<x-ui.modal id="modal-nueva-produccion" size="2xl" flush labelledby="modal-nueva-produccion-titulo">
    <div
        class="flex min-h-0 flex-1 flex-col"
        data-produccion-nueva
        data-url-requerimientos="{{ $rutas['requerimientos'] }}"
        data-abrir="{{ $abrirModal ? '1' : '0' }}"
    >
        <header class="flex shrink-0 items-start justify-between gap-4 border-b border-text-muted/10 px-5 py-4 sm:px-6">
            <div class="min-w-0">
                <h2 id="modal-nueva-produccion-titulo" class="text-lg font-bold text-text">Nueva producción</h2>
                <p class="mt-0.5 text-sm text-text-muted">Selecciona qué fabricar. EcoLact calculará automáticamente los insumos necesarios.</p>
            </div>
            <button type="button" data-modal-close aria-label="Cerrar" title="Cerrar" class="rounded-lg p-1 text-text-muted hover:bg-primary-soft/40 hover:text-text">
                <x-ui.icon name="x-mark" class="h-5 w-5" />
            </button>
        </header>

        @if (count($productos) === 0)
            <div class="flex-1 overflow-y-auto px-5 py-5 sm:px-6">
                <div class="rounded-xl border border-dashed border-text-muted/30 p-6 text-center">
                    <p class="text-sm font-semibold text-text">No hay productos disponibles para producir.</p>
                    <p class="mt-1 text-sm text-text-muted">Primero configura una receta.</p>
                    @if ($puedeConfigurarRecetas)
                        <x-ui.button :href="$rutas['recetas']" size="sm" as="a" class="mt-4">Ir a Recetas</x-ui.button>
                    @endif
                </div>
            </div>
            <footer class="flex shrink-0 justify-end border-t border-text-muted/10 px-5 py-3 sm:px-6">
                <x-ui.button type="button" data-modal-close variant="ghost">Cerrar</x-ui.button>
            </footer>
        @else
            <form method="POST" action="{{ $rutas['store'] }}" class="flex min-h-0 flex-1 flex-col" data-produccion-form>
                @csrf

                {{-- Íconos que usa el JavaScript (se dibujan aquí para reutilizar el catálogo de EcoLact). --}}
                <template data-icono="check"><x-ui.icon name="check-circle" class="h-4 w-4 shrink-0" /></template>
                <template data-icono="alerta"><x-ui.icon name="alert-circle" class="h-4 w-4 shrink-0" /></template>
                <template data-icono="leche"><x-ui.icon name="droplet" class="h-4 w-4 shrink-0" /></template>

                {{-- Fila nueva: el JavaScript reemplaza __i__ por un índice único. --}}
                <template data-fila-plantilla>
                    @include('produccion._nueva-fila', ['indice' => '__i__', 'fila' => ['producto_id' => '', 'cantidad' => '']])
                </template>

                <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-5 sm:px-6">
                    {{-- La leche disponible es el stock real del insumo LECHE: sube con Acopio y baja al iniciar producciones. --}}
                    <div class="flex items-center justify-between gap-3 rounded-xl border border-info/20 bg-info-soft px-4 py-3">
                        <div class="flex items-center gap-3">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-surface text-info">
                                <x-ui.icon name="droplet" class="h-5 w-5" />
                            </span>
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-text-muted">Leche disponible</p>
                                <p class="text-xs text-text-muted">Ingresa automáticamente desde Acopio</p>
                            </div>
                        </div>
                        <p class="text-xl font-bold text-text">{{ $fmtLeche }} {{ $leche->unidadBase() }}</p>
                    </div>

                    {{-- 1. Productos a fabricar: una fila por producto; cada uno será su propio lote. --}}
                    <section aria-labelledby="productos-titulo">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h3 id="productos-titulo" class="{{ $rotulo }}">Productos a fabricar</h3>
                            <p class="text-sm font-medium text-text" data-resumen-planificacion aria-live="polite"></p>
                        </div>

                        <div class="mt-3 hidden grid-cols-12 gap-3 px-1 text-xs font-medium text-text-muted sm:grid" aria-hidden="true">
                            <span class="col-span-6">Producto</span>
                            <span class="col-span-3">Cantidad a producir</span>
                            <span class="col-span-2">Unidad</span>
                        </div>

                        <ul class="mt-2 space-y-3 sm:space-y-2" data-filas>
                            @foreach ($filasIniciales as $indice => $fila)
                                @include('produccion._nueva-fila', ['indice' => $indice, 'fila' => $fila])
                            @endforeach
                        </ul>

                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-primary/40 px-3 py-2 text-sm font-medium text-primary hover:bg-primary-soft/40 disabled:cursor-not-allowed disabled:opacity-50" data-agregar-fila>
                                <x-ui.icon name="plus" class="h-4 w-4" /> Agregar otro producto
                            </button>
                            <p class="text-xs text-text-muted">Cada producto se fabrica en su propio lote. Para hacer más de un producto, aumenta su cantidad en lugar de repetirlo.</p>
                        </div>
                    </section>

                    {{-- 2. Requerimientos totales calculados por el servidor (todos los productos sumados). --}}
                    <section class="border-t border-text-muted/10 pt-5" aria-labelledby="requerimientos-titulo">
                        <h3 id="requerimientos-titulo" class="{{ $rotulo }}">Requerimientos de producción</h3>

                        <p class="mt-3 rounded-lg border border-dashed border-text-muted/25 px-4 py-6 text-center text-sm text-text-muted" data-sin-calculo>
                            Elige al menos un producto y su cantidad para ver los insumos necesarios.
                        </p>

                        <p class="mt-3 flex items-center gap-2 text-sm text-text-muted" data-calculando hidden role="status">
                            <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" aria-hidden="true"></span>
                            Calculando insumos...
                        </p>

                        <div class="mt-3 overflow-hidden rounded-xl border border-text-muted/15" data-recursos hidden aria-live="polite">
                            <div class="hidden grid-cols-12 gap-3 border-b border-text-muted/10 bg-primary-soft/30 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-text-muted sm:grid">
                                <span class="col-span-4">Insumo</span>
                                <span class="col-span-2 text-right">Necesario</span>
                                <span class="col-span-3 text-right">Disponible</span>
                                <span class="col-span-3 text-right">Estado</span>
                            </div>
                            <ul class="divide-y divide-text-muted/10" data-recursos-cuerpo></ul>
                        </div>
                    </section>

                    {{-- Veredicto: lo redacta el servidor (misma regla que bloquea el inicio real). --}}
                    <div data-veredicto hidden aria-live="polite"></div>

                    <x-forms.textarea name="observacion" label="Observación (opcional)" :rows="2" placeholder="Producción turno tarde." />
                </div>

                <footer class="flex shrink-0 flex-col-reverse gap-3 border-t border-text-muted/10 bg-surface px-5 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <p class="text-xs text-text-muted">Al iniciar se descuentan los insumos. Cada producto se suma al stock cuando finalices su lote.</p>
                    <div class="flex shrink-0 justify-end gap-2">
                        <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                        <x-ui.button type="submit" disabled data-iniciar>Iniciar producción</x-ui.button>
                    </div>
                </footer>
            </form>
        @endif
    </div>
</x-ui.modal>
