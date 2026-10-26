{{--
    MODAL "Registrar compra" (boleta escaneada o manual). Flujo:
      1. Captura (solo boleta): tomar foto / seleccionar imagen -> vista previa -> Procesar boleta.
         El servidor guarda la foto, la lee con Tesseract y PROPONE líneas; no registra nada.
      2. Revisión (o compra manual): insumo, presentación, cantidad y precio unitario por línea, más los
         datos de la compra (proveedor, boleta, fecha, total, observación y foto opcional).
         El SERVIDOR calcula la equivalencia (2 baldes de 20 L = 40 L) y el stock resultante.
      3. Confirmar compra: recién aquí se crea la compra y sus movimientos de entrada.
    La leche no aparece: no se compra. resources/js/pages/insumos-compra.js maneja los pasos.
    Variables: $catalogoCompra (ObtenerCatalogoCompraInsumos).
--}}
<x-ui.modal id="modal-compra-insumos" title="Registrar compra" size="xl">
    <div
        class="space-y-5"
        data-compra-insumos
        data-url-boleta="{{ route('admin.compras-insumo.boleta') }}"
        data-url-previsualizar="{{ route('admin.compras-insumo.previsualizar') }}"
        data-catalogo="{{ json_encode($catalogoCompra) }}"
    >
        <template data-icono="check"><x-ui.icon name="check-circle" class="h-4 w-4 shrink-0" /></template>
        <template data-icono="alerta"><x-ui.icon name="alert-circle" class="h-4 w-4 shrink-0" /></template>
        <template data-icono="trash"><x-ui.icon name="trash" class="h-4 w-4" /></template>

        {{-- PASO 1: foto de la boleta. --}}
        <section class="space-y-4" data-paso="captura">
            <p class="text-sm text-text-muted">Toma una foto de la boleta de frente y con buena luz. EcoLact leerá qué compraste y convertirá cada presentación a la unidad del insumo.</p>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-primary/30 bg-primary-soft/40 px-4 py-4 text-sm font-semibold text-primary-dark transition hover:border-primary">
                    <x-ui.icon name="camera" class="h-5 w-5" /> Tomar foto
                    <input type="file" accept="image/*" capture="environment" class="sr-only" data-foto>
                </label>
                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-text-muted/20 px-4 py-4 text-sm font-semibold text-text transition hover:border-primary">
                    <x-ui.icon name="image" class="h-5 w-5" /> Seleccionar imagen
                    <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" data-foto>
                </label>
            </div>

            <figure class="space-y-2" data-preview hidden>
                <figcaption class="text-xs font-semibold uppercase tracking-wide text-text-muted">Boleta</figcaption>
                <img src="" alt="Vista previa de la boleta" class="max-h-72 w-full rounded-xl border border-text-muted/15 bg-background object-contain" data-preview-img>
            </figure>

            <p class="flex items-center gap-2 text-sm text-text-muted" data-leyendo hidden role="status">
                <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" aria-hidden="true"></span>
                Leyendo boleta...
            </p>

            <div class="space-y-3 rounded-lg border border-danger/25 bg-danger-soft px-4 py-3" data-error-ocr hidden role="alert">
                <p class="text-sm font-medium text-danger" data-error-ocr-texto>No se pudo leer la boleta automáticamente.</p>
                <div class="flex flex-wrap gap-2">
                    <x-ui.button type="button" size="sm" variant="secondary" data-reintentar>Volver a intentar</x-ui.button>
                    <x-ui.button type="button" size="sm" data-ir-manual>Registrar manualmente</x-ui.button>
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-text-muted/10 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <button type="button" class="text-sm font-medium text-primary hover:text-primary-dark" data-ir-manual>¿Sin boleta? Registrar compra manual</button>
                <div class="flex justify-end gap-2">
                    <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                    <x-ui.button type="button" disabled data-procesar>Procesar boleta</x-ui.button>
                </div>
            </div>
        </section>

        {{-- PASO 2: revisión y confirmación. --}}
        <form method="POST" action="{{ route('admin.compras-insumo.store') }}" enctype="multipart/form-data" class="space-y-5" data-paso="revision" data-compra-form hidden>
            @csrf
            <input type="hidden" name="imagen_boleta" value="" data-imagen-boleta>
            <div data-ocultos></div>

            <div class="flex items-start gap-3 rounded-lg bg-info/10 px-4 py-3 text-sm text-primary-dark ring-1 ring-inset ring-info/20" data-aviso-ocr hidden>
                <img src="" alt="Boleta" class="h-14 w-14 shrink-0 rounded-md border border-text-muted/15 object-cover" data-miniatura>
                <p>Revisa lo que EcoLact leyó de la boleta y corrige lo necesario. <strong>El stock no cambia hasta que confirmes la compra.</strong></p>
            </div>

            <section class="space-y-3" aria-labelledby="compra-detectada-titulo">
                <div class="flex items-center justify-between gap-3">
                    <h3 id="compra-detectada-titulo" class="text-xs font-semibold uppercase tracking-wide text-text-muted" data-titulo-lineas>Compra detectada</h3>
                    <button type="button" class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:text-primary-dark" data-agregar>
                        <x-ui.icon name="plus" class="h-4 w-4" /> Agregar otro insumo
                    </button>
                </div>
                <div class="space-y-3" data-lineas></div>
            </section>

            <section class="space-y-2" data-impacto hidden aria-live="polite">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-text-muted">Impacto en stock</h3>
                <div class="overflow-hidden rounded-xl border border-text-muted/15">
                    <div class="hidden grid-cols-12 gap-3 border-b border-text-muted/10 bg-primary-soft/30 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-text-muted sm:grid">
                        <span class="col-span-3">Insumo</span>
                        <span class="col-span-3 text-right">Stock actual</span>
                        <span class="col-span-3 text-right">Compra</span>
                        <span class="col-span-3 text-right">Stock resultante</span>
                    </div>
                    <ul class="divide-y divide-text-muted/10" data-impacto-cuerpo></ul>
                </div>
            </section>

            <p class="flex items-center gap-2 text-sm text-text-muted" data-calculando hidden role="status">
                <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" aria-hidden="true"></span>
                Calculando...
            </p>
            <div data-mensaje aria-live="polite"></div>

            <section class="space-y-3 rounded-xl border border-text-muted/15 p-4" data-comprobante>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-text-muted">Datos de la compra</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-forms.input name="proveedor_comercial" id="compra-proveedor" label="Proveedor o tienda" maxlength="150" placeholder="Ej. Distribuidora Andina" :sticky="false" data-cabecera="proveedor" />
                    <x-forms.input name="numero_comprobante" id="compra-numero" label="N.º de boleta / comprobante" maxlength="30" placeholder="B001-203" :sticky="false" data-cabecera="numero_comprobante" />
                    <x-forms.input name="fecha" id="compra-fecha" type="date" label="Fecha" :value="now()->format('Y-m-d')" :max="now()->format('Y-m-d')" :sticky="false" data-cabecera="fecha" />
                    <div>
                        <x-forms.input name="total" id="compra-total" type="number" step="0.01" min="0" label="Precio total (S/)" :sticky="false" data-cabecera="total" />
                        <p class="mt-1 text-xs text-text-muted" data-total-ayuda>Opcional si indicas el precio unitario de cada línea.</p>
                    </div>
                    <x-forms.input name="ruc" id="compra-ruc" label="RUC (opcional)" maxlength="11" inputmode="numeric" placeholder="20123456789" :sticky="false" data-cabecera="ruc" />
                    <x-forms.input name="observacion" id="compra-observacion" label="Observación (opcional)" maxlength="500" :sticky="false" />
                </div>
                <div data-foto-manual>
                    <x-forms.label for="compra-imagen">Imagen de la boleta (opcional)</x-forms.label>
                    <input id="compra-imagen" name="imagen" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-2 file:text-sm file:font-semibold file:text-primary-dark hover:file:bg-primary-soft/70" data-imagen-manual>
                </div>
            </section>

            <div class="flex flex-col-reverse gap-3 border-t border-text-muted/10 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-text-muted">El stock de cada insumo aumenta recién al confirmar.</p>
                <div class="flex shrink-0 justify-end gap-2">
                    <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                    <x-ui.button type="submit" disabled data-confirmar>Confirmar compra</x-ui.button>
                </div>
            </div>
        </form>
    </div>
</x-ui.modal>
