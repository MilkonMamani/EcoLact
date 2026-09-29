{{--
    Panel derecho de Pagos a trabajadores: resumen del trabajador seleccionado. Se pinta en la
    carga inicial (index, ?trabajador=ID) y como fragmento (admin.pagos.trabajadores.panel) al
    elegir otra fila. Solo muestra TrabajadorPagoFila; el único cambio de datos que se hace aquí
    es el que ya existía: bonificación/descuento y registrar el pago mientras está PENDIENTE.

    @var \App\Application\Pago\TrabajadorPagoFila|null $seleccion
--}}
@php
    $soles = fn ($valor): string => 'S/ '.number_format((float) $valor, 2);
@endphp

@if ($seleccion === null)
    <x-fichas.panel-vacio entidad="trabajador">Haz click en una fila del listado para ver su asistencia y el detalle de su pago.</x-fichas.panel-vacio>
@else
    <div class="fichas-panel-contenido" data-fichas-contenido data-ficha-id="{{ $seleccion->clave() }}">
        <header class="fichas-panel-head">
            <span class="fichas-avatar" aria-hidden="true">{{ $seleccion->iniciales }}</span>
            <div class="min-w-0 flex-1">
                <h2 class="fichas-panel-nombre">{{ $seleccion->nombre }}</h2>
                <p class="fichas-panel-dni">{{ $seleccion->rol }} · {{ $seleccion->dni ? 'DNI '.$seleccion->dni : 'Sin DNI' }}</p>
            </div>
            <button type="button" class="fichas-cerrar" data-fichas-cerrar aria-label="Cerrar panel" title="Cerrar panel">
                <x-ui.icon name="x-mark" class="h-5 w-5" />
            </button>
        </header>

        <div class="fichas-panel-cuerpo pagos-panel" @unless ($seleccion->sinSueldo()) data-panel-obligacion="{{ $seleccion->id }}" @endunless>
            <dl class="pagos-periodo">
                <dt>Periodo</dt>
                <dd>{{ $seleccion->periodoEtiqueta }}</dd>
                <dd class="is-rango">{{ $seleccion->periodoRango }}</dd>
            </dl>

            <section class="pagos-bloque" aria-labelledby="pagos-asistencia-{{ $seleccion->clave() }}">
                <h3 id="pagos-asistencia-{{ $seleccion->clave() }}">Resumen de asistencia</h3>
                <dl class="pagos-asistencia">
                    <div><dt>Asistencias</dt><dd>{{ $seleccion->diasTrabajados }}</dd></div>
                    <div @class(['is-falta' => $seleccion->faltas > 0])><dt>Faltas</dt><dd>{{ $seleccion->faltas }}</dd></div>
                </dl>
            </section>

            @if ($seleccion->sinSueldo())
                {{-- Sin sueldo configurado para su rol: no hay obligación que pagar, no se inventan montos. --}}
                <section class="pagos-bloque">
                    <div class="pagos-estado-fila">
                        <h3>Estado del pago</h3>
                        <x-pagos.estado-pago :estado="$seleccion->estado" />
                    </div>
                    <p class="pagos-sin-sueldo" data-sin-sueldo>El rol <strong>{{ $seleccion->rol }}</strong> no tiene sueldo configurado. Configura su sueldo mensual para que se genere el pago de {{ $seleccion->periodoEtiqueta }}.</p>
                    <a href="{{ route('admin.configuracion-pagos.index') }}" class="fichas-btn is-outline pagos-accion">Configurar sueldo del rol</a>
                </section>
            @else
            <section class="pagos-bloque" aria-labelledby="pagos-detalle-{{ $seleccion->id }}">
                <h3 id="pagos-detalle-{{ $seleccion->id }}">Detalle de pago</h3>
                <dl class="pagos-detalle">
                    <div><dt>Sueldo mensual</dt><dd>{{ $soles($seleccion->sueldoBase) }}</dd></div>
                    <div><dt>Valor por día</dt><dd>{{ $soles($seleccion->valorDia) }}</dd></div>
                    <div>
                        <dt>
                            Acumulado
                            <span class="pagos-nota">{{ $seleccion->esMesCompleto ? 'Sueldo mensual menos faltas registradas.' : 'Proporcional a los días trabajados'.($seleccion->fechaIngresoEtiqueta ? ' desde su ingreso ('.$seleccion->fechaIngresoEtiqueta.')' : '').'.' }}</span>
                        </dt>
                        <dd>{{ $soles($seleccion->acumulado) }}</dd>
                    </div>
                    <div>
                        <dt><label for="pago-bonificacion-{{ $seleccion->id }}">Bonificaciones</label></dt>
                        <dd>
                            @if ($seleccion->estaPendiente())
                                <span class="pagos-ajuste">S/ <input id="pago-bonificacion-{{ $seleccion->id }}" type="number" step="0.01" min="0" inputmode="decimal" value="{{ number_format($seleccion->bonificacion, 2, '.', '') }}" data-campo-bonificacion></span>
                            @else
                                {{ $soles($seleccion->bonificacion) }}
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt><label for="pago-descuento-{{ $seleccion->id }}">Descuentos</label></dt>
                        <dd>
                            @if ($seleccion->estaPendiente())
                                <span class="pagos-ajuste">S/ <input id="pago-descuento-{{ $seleccion->id }}" type="number" step="0.01" min="0" inputmode="decimal" value="{{ number_format($seleccion->descuento, 2, '.', '') }}" data-campo-descuento></span>
                            @else
                                {{ $soles($seleccion->descuento) }}
                            @endif
                        </dd>
                    </div>
                </dl>
                <p data-ajuste-error hidden class="mt-2 text-xs text-danger"></p>

                <div class="pagos-total-final">
                    <span>Total final</span>
                    <strong data-campo-total>{{ $soles($seleccion->total) }}</strong>
                </div>
            </section>

            <section class="pagos-bloque">
                <div class="pagos-estado-fila">
                    <h3>Estado del pago</h3>
                    <x-pagos.estado-pago :estado="$seleccion->estado" />
                </div>

                @if ($seleccion->estaEnCurso())
                    <p class="pagos-nota">El mes aún no ha sido cerrado: el pago estará disponible al cierre del mes.</p>
                    <button type="button" class="fichas-btn is-outline pagos-accion" disabled>Registrar pago</button>
                @elseif ($seleccion->estaPendiente())
                    <form method="POST" action="{{ route('admin.pagos.trabajadores.pagar', $seleccion->id) }}" enctype="multipart/form-data" class="pagos-form" data-form-confirmar-pago data-metodos-electronicos="{{ json_encode($metodosElectronicos) }}">
                        @csrf
                        <input type="hidden" name="q" value="{{ request('q') }}">
                        <input type="hidden" name="rol" value="{{ request('rol') }}">
                        <input type="hidden" name="periodo" value="{{ request('periodo') }}">
                        <input type="hidden" name="estado" value="{{ request('estado') }}">

                        <x-forms.select name="metodo_pago" :id="'trabajador-metodo-'.$seleccion->id" label="Método de pago" required :options="$metodos" placeholder="Selecciona un método" data-campo-metodo />

                        <div>
                            <x-forms.label :for="'trabajador-operacion-'.$seleccion->id"><span data-campo-operacion-etiqueta>N° de operación / referencia</span><span class="text-danger" aria-hidden="true" data-campo-operacion-req hidden> *</span></x-forms.label>
                            <input id="trabajador-operacion-{{ $seleccion->id }}" name="numero_operacion" type="text" maxlength="100" autocomplete="off" class="pagos-campo" data-campo-operacion>
                            <p class="mt-1 text-xs text-text-muted" data-campo-operacion-ayuda>Opcional para efectivo; obligatorio para métodos electrónicos.</p>
                        </div>

                        <div>
                            <x-forms.label :for="'trabajador-fecha-'.$seleccion->id" :required="true">Fecha de pago</x-forms.label>
                            <input id="trabajador-fecha-{{ $seleccion->id }}" name="fecha_pago" type="date" required max="{{ now()->toDateString() }}" min="{{ $seleccion->periodoInicioIso }}" value="{{ now()->toDateString() }}" class="pagos-campo">
                        </div>

                        <x-forms.textarea name="observacion" :id="'trabajador-observacion-'.$seleccion->id" label="Observación (opcional)" placeholder="Ej. Pago en efectivo" :rows="2" maxlength="500" />

                        <div>
                            <x-forms.label :for="'trabajador-comprobante-'.$seleccion->id">Constancia adjunta (opcional)</x-forms.label>
                            <input id="trabajador-comprobante-{{ $seleccion->id }}" name="comprobante" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" class="pagos-campo is-archivo">
                        </div>

                        <button type="submit" class="fichas-btn is-primary pagos-accion">Registrar pago</button>
                    </form>
                @else
                    <p class="pagos-pagado">
                        Pagado el {{ $seleccion->pagadoEl }} · {{ $seleccion->metodoPago }}@if ($seleccion->registradoPor) · por {{ $seleccion->registradoPor }}@endif
                    </p>
                    @if ($seleccion->observacion)
                        <p class="pagos-nota">"{{ $seleccion->observacion }}"</p>
                    @endif
                @endif

                {{-- Pagado: comprobante definitivo. Antes: vista previa claramente marcada en el propio documento. --}}
                <a href="{{ route('admin.pagos.trabajadores.comprobante', $seleccion->id) }}" target="_blank" rel="noopener" class="fichas-btn is-outline pagos-accion" data-imprimir-comprobante>
                    <x-ui.icon name="printer" class="h-4 w-4" /> {{ $seleccion->estado === 'PAGADO' ? 'Imprimir comprobante' : 'Imprimir vista previa' }}
                </a>
            </section>
            @endif
        </div>
    </div>
@endif
