{{--
    FORMULARIO ÚNICO "Registrar compra". Tres etapas:
      1. Datos: fecha, proveedor comercial y comprobante. La foto de la boleta es OPCIONAL: al
         elegirla el servidor la guarda en privado, la lee con el OCR existente y PROPONE datos y
         artículos (no registra nada). Lo poco usado (tipo de compra, RUC, concepto, observación,
         otros documentos) queda en «Más datos».
      2. Artículos: varias líneas, cada una con su destino (inventario, consumo, bien, servicio).
         Las de inventario eligen insumo + presentación; el SERVIDOR convierte a la unidad base.
      3. Revisar y confirmar: resumen, impacto en inventario y condición de pago. Pago y recepción
         se eligen a propósito (nada viene marcado); recién aquí se registra.
    resources/js/pages/compras.js maneja las etapas. La leche no aparece: no se compra.
    Variables: $categoriasActivas, $catalogoCompra, $proveedoresComerciales, $abrirFormulario,
    $insumoPreseleccionado.
--}}
@php
    $tratamientos = \App\Domain\Shared\Enums\TratamientoCompra::opciones();
    $categoriasJs = array_map(fn ($c) => ['id' => $c->id(), 'nombre' => $c->nombre(), 'codigo' => $c->codigo(), 'tratamiento' => $c->tratamientoSugerido()->value, 'caja' => $c->categoriaCaja()->etiquetaPlural()], $categoriasActivas);
    $pasos = ['datos' => 'Datos de compra', 'articulos' => 'Artículos', 'revision' => 'Revisar y confirmar'];
    $hoy = now(config('app.zona_horaria_negocio'))->format('Y-m-d');
@endphp

<x-ui.modal id="modal-compra" title="Registrar compra" size="2xl">
    <div
        class="compra-form mt-4"
        data-compra-form-raiz
        data-url-boleta="{{ route('admin.compras.boleta') }}"
        data-url-previsualizar="{{ route('admin.compras.previsualizar') }}"
        data-catalogo="{{ json_encode($catalogoCompra) }}"
        data-categorias="{{ json_encode($categoriasJs) }}"
        data-tratamientos="{{ json_encode($tratamientos) }}"
        @if ($abrirFormulario) data-abrir="{{ $abrirFormulario }}" @endif
        @if ($insumoPreseleccionado) data-insumo="{{ $insumoPreseleccionado }}" @endif
    >
        <template data-icono="check"><x-ui.icon name="check-circle" class="h-4 w-4 shrink-0" /></template>
        <template data-icono="alerta"><x-ui.icon name="alert-circle" class="h-4 w-4 shrink-0" /></template>
        <template data-icono="trash"><x-ui.icon name="trash" class="h-4 w-4" /></template>

        <form method="POST" action="{{ route('admin.compras.store') }}" enctype="multipart/form-data" class="space-y-5" data-compra-form novalidate>
            @csrf
            <input type="hidden" name="clave_envio" value="" data-clave-envio>
            <input type="hidden" name="documento_escaneado" value="" data-documento-escaneado>
            <input type="hidden" name="moneda" value="PEN">
            <div data-ocultos></div>

            <ol class="compra-pasos" aria-label="Etapas del registro">
                @foreach ($pasos as $clave => $nombre)
                    <li data-indicador="{{ $clave }}"><span class="compra-pasos-num">{{ $loop->iteration }}</span> <span class="compra-pasos-nombre">{{ $nombre }}</span></li>
                @endforeach
            </ol>

            {{-- ETAPA 1: datos de la compra (+ boleta opcional). --}}
            <section class="space-y-4" data-paso="datos" aria-label="Datos de compra">
                <div class="compra-escaneo" data-escaneo>
                    <div class="compra-escaneo-cabeza">
                        <span class="compra-escaneo-icono" aria-hidden="true"><x-ui.icon name="camera" class="h-5 w-5" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-text">¿Tienes la boleta? <span class="font-normal text-text-muted">(opcional)</span></p>
                            <p class="text-xs text-text-muted">Toma una foto y EcoLact te propondrá los datos y artículos. Tú los revisas.</p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <label class="fichas-btn is-outline is-sm cursor-pointer">
                                <x-ui.icon name="camera" class="h-4 w-4" /> Tomar foto
                                <input type="file" accept="image/*" capture="environment" class="sr-only" data-foto>
                            </label>
                            <label class="fichas-btn is-outline is-sm cursor-pointer">
                                <x-ui.icon name="image" class="h-4 w-4" /> Subir imagen
                                <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" data-foto>
                            </label>
                        </div>
                    </div>

                    <div class="compra-escaneo-resultado" data-escaneo-resultado hidden>
                        <img src="" alt="Boleta escaneada" class="compra-escaneo-img" data-escaneo-img>
                        <div class="min-w-0 flex-1 space-y-2">
                            <p class="flex items-center gap-2 text-sm text-text-muted" data-leyendo hidden role="status">
                                <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" aria-hidden="true"></span>
                                Leyendo la boleta...
                            </p>
                            <div class="space-y-2" data-error-ocr hidden role="alert">
                                <p class="text-sm font-medium text-warning" data-error-ocr-texto>No se pudo leer la boleta automáticamente.</p>
                                <p class="text-xs text-text-muted">La foto se guarda igual como comprobante: completa los datos a mano.</p>
                                <button type="button" class="text-sm font-medium text-primary hover:text-primary-dark" data-reintentar>Volver a intentar</button>
                            </div>
                            <div data-ocr-lectura hidden>
                                <dl class="compra-ocr" data-ocr-datos></dl>
                                <ul class="mt-2 space-y-1.5 text-sm" data-ocr-avisos></ul>
                            </div>
                            <button type="button" class="text-xs font-medium text-danger hover:underline" data-quitar-foto>Quitar foto</button>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-forms.input name="fecha" id="compra-fecha" type="date" label="Fecha" required :value="$hoy" :max="$hoy" :sticky="false" data-cabecera="fecha" />
                    <div>
                        <x-forms.input name="proveedor_comercial" id="compra-proveedor" label="Proveedor comercial" maxlength="150" placeholder="Ej. Distribuidora Andina" list="compra-proveedores" :sticky="false" data-cabecera="proveedor" />
                        <datalist id="compra-proveedores">
                            @foreach ($proveedoresComerciales as $proveedor)
                                <option value="{{ $proveedor->razonSocial() }}" data-ruc="{{ $proveedor->ruc() }}">{{ $proveedor->ruc() }}</option>
                            @endforeach
                        </datalist>
                    </div>
                    <x-forms.select name="tipo_comprobante" id="compra-tipo-comprobante" label="Comprobante" required :options="\App\Domain\Shared\Enums\TipoComprobanteCompra::opciones()" value="BOLETA" placeholder="Elige el comprobante" :sticky="false" />
                    <x-forms.input name="numero_comprobante" id="compra-numero" label="Serie y número" maxlength="30" placeholder="B001-203" :sticky="false" data-cabecera="numero_comprobante" />
                </div>

                <details class="compra-mas-datos">
                    <summary>Más datos <span>(opcional)</span> <x-ui.icon name="chevron-down" class="compra-mas-datos-flecha h-4 w-4" /></summary>
                    <div class="grid gap-4 pt-3 sm:grid-cols-2">
                        <div>
                            <x-forms.select name="categoria_compra_id" id="compra-categoria" label="Tipo de compra" :options="array_column($categoriasJs, 'nombre', 'id')" placeholder="Automático según los artículos" :sticky="false" data-categoria />
                            <p class="mt-1 text-xs text-text-muted">Si no lo eliges, se deduce de los artículos.</p>
                        </div>
                        <x-forms.input name="ruc" id="compra-ruc" label="RUC del proveedor" maxlength="11" inputmode="numeric" placeholder="20123456789" :sticky="false" data-cabecera="ruc" />
                        <x-forms.input name="concepto" id="compra-concepto" label="Concepto" maxlength="150" placeholder="Se completa con el primer artículo" :sticky="false" />
                        <x-forms.input name="observacion" id="compra-observacion" label="Observación" maxlength="500" :sticky="false" />
                        <div class="sm:col-span-2">
                            <x-forms.label for="compra-documentos">Otros documentos</x-forms.label>
                            <input id="compra-documentos" name="documentos[]" type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" class="block w-full text-sm text-text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-2 file:text-sm file:font-semibold file:text-primary-dark hover:file:bg-primary-soft/70" data-documentos>
                            <p class="mt-1 text-xs text-text-muted">Factura en PDF, constancia de transferencia... (hasta 5 archivos de 8 MB). También puedes adjuntarlos después desde la ficha.</p>
                            <ul class="mt-2 space-y-1 text-sm text-text" data-documentos-lista></ul>
                        </div>
                    </div>
                </details>

                <p class="text-xs text-text-muted">Registra: <strong class="font-medium text-text">{{ auth()->user()->username }}</strong></p>
            </section>

            {{-- ETAPA 2: artículos o conceptos. --}}
            <section class="space-y-3" data-paso="articulos" aria-label="Artículos" hidden>
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-text-muted">Artículos comprados</h3>
                    <button type="button" class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:text-primary-dark" data-agregar>
                        <x-ui.icon name="plus" class="h-4 w-4" /> Agregar artículo
                    </button>
                </div>
                <div class="space-y-3" data-lineas></div>

                <div class="compra-totales">
                    <div>
                        <x-forms.input name="impuesto" id="compra-impuesto" type="number" step="0.01" min="0" label="IGV aparte (S/)" placeholder="0.00" :sticky="false" data-impuesto />
                        <p class="mt-1 text-xs text-text-muted">Solo si la factura lo suma aparte.</p>
                    </div>
                    <p class="text-sm text-text-muted sm:text-right">Subtotal <strong class="block text-base text-text" data-subtotal>—</strong></p>
                    <p class="text-sm text-text-muted sm:text-right">Total <strong class="block text-xl text-text" data-total>—</strong></p>
                </div>

                <p class="flex items-center gap-2 text-sm text-text-muted" data-calculando hidden role="status">
                    <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" aria-hidden="true"></span>
                    Calculando...
                </p>
            </section>

            {{-- ETAPA 3: revisar y confirmar. --}}
            <section class="space-y-5" data-paso="revision" aria-label="Revisar y confirmar" hidden>
                <div class="compra-resumen" data-resumen></div>

                <section class="space-y-2" data-impacto hidden aria-live="polite">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-text-muted">Impacto en inventario</h3>
                    <div class="overflow-hidden rounded-xl border border-text-muted/15">
                        <div class="hidden grid-cols-12 gap-3 border-b border-text-muted/10 bg-primary-soft/30 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-text-muted sm:grid">
                            <span class="col-span-3">Insumo</span>
                            <span class="col-span-3 text-right">Stock actual</span>
                            <span class="col-span-3 text-right">Ingresa</span>
                            <span class="col-span-3 text-right">Stock resultante</span>
                        </div>
                        <ul class="divide-y divide-text-muted/10" data-impacto-cuerpo></ul>
                    </div>
                </section>

                <fieldset class="space-y-2" data-recepcion hidden>
                    <legend class="text-xs font-semibold uppercase tracking-wide text-text-muted">¿Ya llegaron los artículos de inventario?</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label class="compra-opcion">
                            <input type="radio" name="recepcion" value="RECIBIDA" data-recepcion-opcion>
                            <span><strong>Sí, ya llegaron</strong><small>El stock aumenta al confirmar.</small></span>
                        </label>
                        <label class="compra-opcion">
                            <input type="radio" name="recepcion" value="PENDIENTE" data-recepcion-opcion>
                            <span><strong>Todavía no</strong><small>El stock aumentará cuando confirmes la recepción.</small></span>
                        </label>
                    </div>
                </fieldset>

                <fieldset class="space-y-2">
                    <legend class="text-xs font-semibold uppercase tracking-wide text-text-muted">¿Cómo se paga?</legend>
                    <div class="grid gap-2 sm:grid-cols-3">
                        @foreach (['CONTADO' => ['Contado', 'Se paga todo ahora.'], 'CREDITO' => ['Crédito', 'No se paga nada ahora.'], 'PARCIAL' => ['Pago parcial', 'Se paga una parte ahora.']] as $valor => [$titulo, $ayuda])
                            <label class="compra-opcion">
                                <input type="radio" name="condicion_pago" value="{{ $valor }}" data-condicion>
                                <span><strong>{{ $titulo }}</strong><small>{{ $ayuda }}</small></span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="grid gap-4 sm:grid-cols-3" data-campos-pago hidden>
                    <div data-campo-monto hidden>
                        <x-forms.input name="monto_pago" id="compra-monto-pago" type="number" step="0.01" min="0.01" label="Monto que pagas ahora (S/)" :sticky="false" data-monto-pago />
                    </div>
                    <x-forms.select name="metodo_pago" id="compra-metodo" label="Método de pago" :options="\App\Domain\Shared\Enums\MetodoPago::opciones()" value="EFECTIVO" placeholder="Elige el método" :sticky="false" data-metodo />
                    <x-forms.input name="numero_operacion" id="compra-operacion" label="N.º de operación" maxlength="60" placeholder="En transferencias, Yape o Plin" :sticky="false" />
                </div>

                <dl class="compra-saldo" data-saldo hidden></dl>
            </section>

            <div data-mensaje aria-live="polite"></div>

            <div class="flex flex-col-reverse gap-3 border-t border-text-muted/10 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <x-ui.button type="button" variant="ghost" data-atras>Atrás</x-ui.button>
                <div class="flex shrink-0 justify-end gap-2">
                    <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                    <x-ui.button type="button" data-siguiente>Siguiente</x-ui.button>
                    <x-ui.button type="submit" data-confirmar hidden>Confirmar y registrar</x-ui.button>
                </div>
            </div>
        </form>
    </div>
</x-ui.modal>
