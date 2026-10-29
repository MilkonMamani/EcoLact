{{--
    Panel derecho de Pagos a trabajadores: PREVISUALIZACIÓN REAL del comprobante del trabajador
    seleccionado (la misma hoja A4 que se imprime, escalada por admin-pago-trabajadores.js) y sus
    acciones: Imprimir comprobante y, solo si está PENDIENTE, Registrar pago (modal con
    confirmación). Aquí nunca se configura el sueldo: eso vive en Configuración de pagos.

    Se pinta en la carga inicial (index, ?trabajador=ID) y como fragmento al elegir otra fila
    (admin-fichas.js). Solo muestra datos del backend; no calcula dinero.

    @var \App\Application\Pago\TrabajadorPagoFila|null $seleccion
    @var \App\Application\Pago\ComprobantePagoTrabajador|null $comprobante
--}}
@php
    $soles = fn ($valor): string => 'S/ '.number_format((float) $valor, 2);
    $puedePagar = auth()->user()?->can('pay', \App\Infrastructure\Persistence\Models\ObligacionPagoModel::class) ?? false;
    $comprobante ??= null;
@endphp

@if ($seleccion === null)
    <div class="fichas-panel-vacio pt-vacio">
        <span class="fichas-panel-vacio-icono"><x-ui.icon name="file-text" class="h-6 w-6" /></span>
        <p class="font-semibold text-text">Selecciona un trabajador</p>
        <p>Haz clic en una fila para revisar el detalle y su comprobante de pago.</p>
    </div>
@elseif ($seleccion->sinSueldo() || $comprobante === null)
    <div class="fichas-panel-contenido" data-fichas-contenido data-ficha-id="{{ $seleccion->clave() }}">
        <header class="pt-panel-cabecera">
            <div class="min-w-0">
                <h2 class="pt-panel-titulo">{{ $seleccion->nombre }}</h2>
                <p class="pt-panel-subtitulo">{{ $seleccion->rol }}</p>
            </div>
            <button type="button" class="fichas-cerrar" data-fichas-cerrar aria-label="Cerrar panel" title="Cerrar panel">
                <x-ui.icon name="x-mark" class="h-5 w-5" />
            </button>
        </header>

        <div class="fichas-panel-cuerpo">
            {{-- Su rol no tiene sueldo vigente: no hay obligación ni comprobante. Solo texto informativo, sin acciones. --}}
            <div class="pt-no-disponible" data-sin-sueldo>
                <x-ui.icon name="minus-circle" class="h-6 w-6" />
                <p class="pt-no-disponible-titulo">Pago no disponible</p>
                <p>No existe un sueldo vigente para el rol de este trabajador en {{ $seleccion->periodoEtiqueta }}.</p>
                <p class="pt-no-disponible-ruta">Configúrala desde: Configuración &gt; Configuración de pagos.</p>
            </div>
        </div>
    </div>
@else
    @php $c = $comprobante; @endphp
    <div class="fichas-panel-contenido" data-fichas-contenido data-ficha-id="{{ $seleccion->clave() }}" data-panel-obligacion="{{ $c->obligacionId }}">
        <header class="pt-panel-cabecera">
            <div class="min-w-0">
                <h2 class="pt-panel-titulo">Comprobante del trabajador</h2>
                <p class="pt-panel-subtitulo">Vista previa del comprobante del trabajador seleccionado.</p>
            </div>
            <button type="button" class="fichas-cerrar" data-fichas-cerrar aria-label="Cerrar panel" title="Cerrar panel">
                <x-ui.icon name="x-mark" class="h-5 w-5" />
            </button>
        </header>

        <div class="fichas-panel-cuerpo pt-panel-cuerpo">
            <div class="pt-preview" data-comprobante-preview>
                <div class="pt-escala" data-comprobante-escala>
                    @include('admin.pagos.trabajadores._comprobante', ['c' => $c])
                </div>
            </div>
        </div>

        <footer class="pt-panel-acciones">
            @if ($errors->any())
                <p class="pt-nota is-error" role="alert">{{ $errors->first('pago') ?: $errors->first() }}</p>
            @endif

            @if ($c->estaPagado())
                <p class="pt-nota is-pagado">Pagado el {{ $c->pagadoEl }}@if ($c->pagadoPor) · registrado por {{ $c->pagadoPor }}@endif</p>
            @elseif ($c->estado === 'EN_CURSO')
                <p class="pt-nota">El mes aún está en curso: el pago se podrá registrar al cierre del mes.</p>
            @endif

            <div class="pt-botones">
                <a href="{{ route('admin.pagos.trabajadores.comprobante', $c->obligacionId) }}" target="_blank" rel="noopener" class="fichas-btn is-outline" data-imprimir-comprobante>
                    <x-ui.icon name="printer" class="h-4 w-4" /> Imprimir comprobante
                </a>
                @if ($seleccion->estaPendiente() && $puedePagar)
                    <button type="button" class="fichas-btn is-primary" data-modal-open="modal-registrar-pago" data-registrar-pago>
                        <x-ui.icon name="check" class="h-4 w-4" /> Registrar pago
                    </button>
                @endif
            </div>
        </footer>

        @if ($seleccion->estaPendiente() && $puedePagar)
            {{-- admin-pago-trabajadores.js lo mueve a <body> (el panel puede ser un drawer con transform). --}}
            <x-ui.modal id="modal-registrar-pago" title="Registrar pago" subtitle="Confirma la entrega del pago al trabajador.">
                <form method="POST" action="{{ route('admin.pagos.trabajadores.pagar', $c->obligacionId) }}" enctype="multipart/form-data" class="pt-form" data-form-confirmar-pago data-obligacion="{{ $c->obligacionId }}" data-metodos-electronicos="{{ json_encode($metodosElectronicos) }}">
                    @csrf
                    @foreach (['q', 'rol', 'periodo', 'estado'] as $filtroActivo)
                        <input type="hidden" name="{{ $filtroActivo }}" value="{{ request($filtroActivo) }}">
                    @endforeach
                    <input type="hidden" name="monto" value="{{ number_format($c->total, 2, '.', '') }}" data-campo-monto>

                    <dl class="pt-resumen-pago">
                        <div><dt>Trabajador</dt><dd>{{ $c->nombre }}</dd></div>
                        <div><dt>Periodo</dt><dd>{{ $c->periodoEtiqueta }}</dd></div>
                        <div class="is-total"><dt>Total</dt><dd data-campo-total>{{ $soles($c->total) }}</dd></div>
                    </dl>

                    {{-- Ajustes del pago pendiente (AjustarObligacionPersonal): se guardan al cambiar y el comprobante los refleja. --}}
                    <details class="pt-ajustes" @if ($c->bonificacion > 0 || $c->descuento > 0) open @endif>
                        <summary>Bonificación o descuento (opcional)</summary>
                        <div class="pt-form-fila">
                            <div>
                                <label for="pago-bonificacion">Bonificación (S/)</label>
                                <input id="pago-bonificacion" type="number" step="0.01" min="0" inputmode="decimal" value="{{ number_format($c->bonificacion, 2, '.', '') }}" class="pt-campo" data-campo-bonificacion>
                            </div>
                            <div>
                                <label for="pago-descuento">Descuento (S/)</label>
                                <input id="pago-descuento" type="number" step="0.01" min="0" inputmode="decimal" value="{{ number_format($c->descuento, 2, '.', '') }}" class="pt-campo" data-campo-descuento>
                            </div>
                        </div>
                        <p class="pt-ayuda">Acumulado del periodo: {{ $soles($c->acumulado) }}.</p>
                        <p data-ajuste-error hidden class="pt-ayuda is-error"></p>
                    </details>

                    <div class="pt-form-fila">
                        <div>
                            <label for="pago-metodo">Método <span class="text-danger" aria-hidden="true">*</span></label>
                            <select id="pago-metodo" name="metodo_pago" required class="pt-campo" data-campo-metodo>
                                @foreach ($metodos as $valor => $etiqueta)
                                    <option value="{{ $valor }}" @selected($valor === \App\Domain\Shared\Enums\MetodoPago::EFECTIVO->value)>{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="pago-fecha">Fecha de pago <span class="text-danger" aria-hidden="true">*</span></label>
                            <input id="pago-fecha" name="fecha_pago" type="date" required max="{{ now()->toDateString() }}" min="{{ $c->periodoInicio }}" value="{{ now()->toDateString() }}" class="pt-campo">
                        </div>
                    </div>

                    <div data-campo-operacion-grupo hidden>
                        <label for="pago-operacion">N.° de operación <span class="text-danger" aria-hidden="true">*</span></label>
                        <input id="pago-operacion" name="numero_operacion" type="text" maxlength="100" autocomplete="off" class="pt-campo" data-campo-operacion>
                    </div>

                    <div>
                        <label for="pago-observacion">Observación <span class="pt-opcional">(opcional)</span></label>
                        <textarea id="pago-observacion" name="observacion" rows="2" maxlength="500" class="pt-campo is-area" placeholder="Ej. Pago entregado en planta"></textarea>
                    </div>

                    <div>
                        <label for="pago-constancia">Constancia adjunta <span class="pt-opcional">(opcional)</span></label>
                        <input id="pago-constancia" name="comprobante" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" class="pt-campo is-archivo">
                    </div>

                    <div class="pt-form-acciones">
                        <button type="button" class="fichas-btn is-outline" data-modal-close>Cancelar</button>
                        <button type="submit" class="fichas-btn is-primary" data-confirmar-pago>Confirmar pago</button>
                    </div>
                </form>
            </x-ui.modal>
        @endif
    </div>
@endif
