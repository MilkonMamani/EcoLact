{{--
    MODAL "Nueva producción" (ADMINISTRADOR y ENCARGADO_PLANTA). Se abre desde el historial de Producción;
    no es una página. Un producto por lote: la persona elige QUÉ y CUÁNTO y, en cuanto ambos tienen valor,
    se muestran los requerimientos que calcula el SERVIDOR con la receta activa (aquí no se calcula nada).
    Elegir, cambiar la cantidad y previsualizar NUNCA descuentan nada: solo "Iniciar producción".
    (El backend sigue aceptando varios productos por lote para la API y los lotes históricos.)
    Variables: $leche (Insumo), $productos (id, nombre, etiqueta, unidad, imagen), $rutas (requerimientos, recetas, store),
    $puedeConfigurarRecetas, $abrirModal (bool).
--}}
@php
    $fmtLeche = rtrim(rtrim(number_format((float) $leche->stockActual(), 2), '0'), '.');
    $anterior = old('productos.0', []);
@endphp

<x-ui.modal id="modal-nueva-produccion" title="Nueva producción" subtitle="Selecciona qué fabricar. EcoLact calculará automáticamente los insumos necesarios." size="lg">
    <div
        class="space-y-5"
        data-produccion-nueva
        data-url-requerimientos="{{ $rutas['requerimientos'] }}"
        data-abrir="{{ $abrirModal ? '1' : '0' }}"
    >
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

        @if (count($productos) === 0)
            <div class="rounded-xl border border-dashed border-text-muted/30 p-6 text-center">
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
                <template data-icono="check"><x-ui.icon name="check-circle" class="h-4 w-4 shrink-0" /></template>
                <template data-icono="alerta"><x-ui.icon name="alert-circle" class="h-4 w-4 shrink-0" /></template>
                <template data-icono="leche"><x-ui.icon name="droplet" class="h-4 w-4 shrink-0" /></template>

                {{-- 1 y 2: qué y cuánto. --}}
                <div class="grid gap-4 sm:grid-cols-5">
                    <div class="sm:col-span-3">
                        <label for="produccion-producto" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-text-muted">Producto</label>
                        <select
                            id="produccion-producto"
                            name="productos[0][producto_id]"
                            required
                            data-producto
                            class="w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2.5 text-sm text-text shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                        >
                            <option value="">Elegir un producto</option>
                            @foreach ($productos as $producto)
                                <option value="{{ $producto['id'] }}" data-unidad="{{ $producto['unidad'] }}" data-imagen="{{ $producto['imagen'] }}" @selected((string) ($anterior['producto_id'] ?? '') === (string) $producto['id'])>{{ $producto['etiqueta'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="produccion-cantidad" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-text-muted">Cantidad a producir</label>
                        <input
                            id="produccion-cantidad"
                            name="productos[0][cantidad]"
                            type="number"
                            step="any"
                            min="0"
                            inputmode="decimal"
                            required
                            placeholder="Ej. 10"
                            value="{{ $anterior['cantidad'] ?? '' }}"
                            data-cantidad
                            class="w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2.5 text-sm text-text shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                        />
                    </div>
                </div>

                {{-- 3 y 4: requerimientos calculados por el servidor. --}}
                <section class="border-t border-text-muted/10 pt-5" aria-labelledby="requerimientos-titulo">
                    <h3 id="requerimientos-titulo" class="text-xs font-semibold uppercase tracking-wide text-text-muted">Requerimientos de producción</h3>

                    <p class="mt-3 rounded-lg border border-dashed border-text-muted/25 px-4 py-6 text-center text-sm text-text-muted" data-sin-calculo>
                        Elige un producto y la cantidad para ver los insumos necesarios.
                    </p>

                    <p class="mt-3 flex items-center gap-2 text-sm text-text-muted" data-calculando hidden role="status">
                        <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" aria-hidden="true"></span>
                        Calculando insumos...
                    </p>

                    <div class="mt-3 space-y-3" data-recursos hidden aria-live="polite">
                        <div class="flex items-center gap-3">
                            <img src="" alt="" class="h-10 w-10 shrink-0 rounded-lg border border-text-muted/10 object-cover" data-resumen-imagen hidden />
                            <div class="flex min-w-0 flex-1 flex-wrap items-baseline justify-between gap-x-3">
                                <p class="text-sm font-semibold text-text" data-resumen-producto></p>
                                <p class="text-sm text-text-muted" data-resumen-cantidad></p>
                            </div>
                        </div>

                        <div class="overflow-hidden rounded-xl border border-text-muted/15">
                            <div class="hidden grid-cols-12 gap-3 border-b border-text-muted/10 bg-primary-soft/30 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-text-muted sm:grid">
                                <span class="col-span-4">Insumo</span>
                                <span class="col-span-2 text-right">Necesario</span>
                                <span class="col-span-3 text-right">Disponible</span>
                                <span class="col-span-3 text-right">Estado</span>
                            </div>
                            <ul class="divide-y divide-text-muted/10" data-recursos-cuerpo></ul>
                        </div>
                    </div>
                </section>

                {{-- Veredicto: lo redacta el servidor (misma regla que bloquea el inicio real). --}}
                <div data-veredicto hidden aria-live="polite"></div>

                <x-forms.textarea name="observacion" label="Observación (opcional)" :rows="2" placeholder="Producción turno tarde." />

                <div class="flex flex-col-reverse gap-3 border-t border-text-muted/10 pt-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-text-muted">Al iniciar se descuentan los insumos. El producto se suma al stock cuando finalices el lote.</p>
                    <div class="flex shrink-0 justify-end gap-2">
                        <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                        <x-ui.button type="submit" disabled data-iniciar>Iniciar producción</x-ui.button>
                    </div>
                </div>
            </form>
        @endif
    </div>
</x-ui.modal>
