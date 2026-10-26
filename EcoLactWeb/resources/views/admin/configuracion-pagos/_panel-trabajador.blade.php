{{--
    Panel de un trabajador: SOLO su sueldo (cuánto gana). Nada de asistencia, faltas ni pagos.
    El rol viene del trabajador real y no se elige aquí. Guardar con una fecha posterior abre una
    nueva vigencia (el sueldo anterior queda en el historial); los pagos ya generados no cambian.
    $trabajador: SueldosTrabajadoresQuery::detalle() (o null si ya no es un trabajador de la lista).
--}}
@php
    $puedeConfigurar = auth()->user()->can('create', \App\Infrastructure\Persistence\Models\ConfiguracionPagoModel::class);
    $fecha = fn (?string $valor): string => $valor ? \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y') : '—';
    $soles = fn (?float $valor): string => 'S/ '.number_format((float) $valor, 2);
@endphp

@if ($trabajador === null)
    @include('admin.configuracion-pagos._panel-trabajador-vacio')
@else
    @php
        $configurado = $trabajador['configuracionId'] !== null;
        $activo = $trabajador['estado'] !== 'INACTIVO';
        $clave = 'sueldo-'.$trabajador['userId'];
        $sticky = old('_modal') === $clave;
    @endphp

    <div class="fichas-panel-contenido" data-fichas-contenido data-ficha-id="{{ $trabajador['userId'] }}">
        <div class="fichas-panel-head">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="fichas-panel-nombre mt-0">{{ $trabajador['nombre'] }}</h2>
                    @include('admin.configuracion-pagos._estado', ['estado' => $trabajador['estado']])
                </div>
                <p class="config-pagos-panel-rol">{{ $trabajador['rol'] }}@if ($trabajador['dni']) · DNI {{ $trabajador['dni'] }}@endif</p>
            </div>
            <button type="button" class="fichas-cerrar" data-fichas-cerrar aria-label="Cerrar detalle">
                <x-ui.icon name="x-mark" class="h-5 w-5" />
            </button>
        </div>

        <div class="fichas-panel-cuerpo">
            <section class="fichas-seccion is-primera">
                <div class="fichas-seccion-head">
                    <h3>Sueldo</h3>
                </div>

                @if ($configurado)
                    <dl class="fichas-datos">
                        <div>
                            <dt>Sueldo mensual</dt>
                            <dd class="is-num font-semibold">{{ $soles($trabajador['sueldo']) }}</dd>
                        </div>
                        <div>
                            <dt>Valor diario</dt>
                            <dd class="is-num">{{ $soles($trabajador['valorDia']) }}</dd>
                        </div>
                        <div>
                            <dt>Vigente desde</dt>
                            <dd class="is-num">{{ $fecha($trabajador['vigenteDesde']) }}</dd>
                        </div>
                        <div>
                            <dt>Estado</dt>
                            <dd>@include('admin.configuracion-pagos._estado', ['estado' => $trabajador['estado']])</dd>
                        </div>
                        @if ($trabajador['proxima'])
                            <div class="is-ancho">
                                <dt>Próximo sueldo</dt>
                                <dd class="is-num">{{ $soles($trabajador['proxima']['sueldo']) }} desde {{ $fecha($trabajador['proxima']['desde']) }}</dd>
                            </div>
                        @endif
                        @if ($trabajador['observacion'])
                            <div class="is-ancho">
                                <dt>Observación</dt>
                                <dd>{{ $trabajador['observacion'] }}</dd>
                            </div>
                        @endif
                    </dl>
                    @if ($puedeConfigurar && $activo)
                        <button type="button" class="fichas-btn is-outline is-sm mt-4" data-modal-open="modal-{{ $clave }}">
                            <x-ui.icon name="pencil" class="h-3.5 w-3.5" /> Editar configuración
                        </button>
                    @endif
                @else
                    <div class="fichas-panel-sin-datos config-pagos-sin-sueldo">
                        <p class="font-semibold text-text">Sin configurar</p>
                        <p>Este trabajador todavía no tiene un sueldo definido.</p>
                        @if ($puedeConfigurar && $activo)
                            <button type="button" class="fichas-btn is-primary is-sm mt-2" data-modal-open="modal-{{ $clave }}">
                                <x-ui.icon name="plus" class="h-3.5 w-3.5" /> Configurar sueldo
                            </button>
                        @endif
                    </div>
                @endif
            </section>

            @if ($trabajador['historial'] !== [])
                <section class="fichas-seccion">
                    <div class="fichas-seccion-head">
                        <h3>Sueldos anteriores</h3>
                    </div>
                    <ul class="config-pagos-historial">
                        @foreach ($trabajador['historial'] as $vigencia)
                            <li>
                                <span class="is-num font-medium">{{ $soles($vigencia['sueldo']) }}</span>
                                <span>{{ $fecha($vigencia['desde']) }} – {{ $vigencia['hasta'] ? $fecha($vigencia['hasta']) : 'sin fin' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        @if ($puedeConfigurar && $activo)
            <x-ui.modal :id="'modal-'.$clave" size="form" :title="$configurado ? 'Editar sueldo' : 'Configurar sueldo'" :subtitle="$trabajador['nombre'].' · '.$trabajador['rol']" :open="$sticky && $errors->any()">
                <form method="POST" action="{{ $configurado ? route('admin.configuracion-pagos.update', $trabajador['configuracionId']) : route('admin.configuracion-pagos.store') }}" data-config-sueldo>
                    @csrf
                    @if ($configurado)
                        @method('PATCH')
                    @else
                        <input type="hidden" name="tipo_beneficiario" value="PERSONAL">
                        <input type="hidden" name="user_id" value="{{ $trabajador['userId'] }}">
                    @endif
                    <input type="hidden" name="_modal" value="{{ $clave }}">

                    <div class="config-pagos-campos">
                        <x-forms.input name="monto_base" :id="$clave.'-sueldo'" type="number" step="0.01" min="0.01" inputmode="decimal" label="Sueldo mensual (S/)" required :sticky="$sticky" :value="$configurado ? number_format($trabajador['sueldo'], 2, '.', '') : null" data-sueldo />
                        <div>
                            <p class="config-pagos-etiqueta">Valor por día</p>
                            <p class="config-pagos-calculado" data-valor-dia aria-live="polite">{{ $configurado ? $soles($trabajador['valorDia']) : '—' }}</p>
                            <p class="config-pagos-nota">Se calcula solo: sueldo mensual ÷ 30.</p>
                        </div>
                        <div class="is-ancho">
                            <x-forms.input name="fecha_inicio" :id="$clave.'-inicio'" type="date" label="Vigente desde" required :sticky="$sticky" :value="now()->toDateString()" />
                            @if ($configurado)
                                <p class="config-pagos-nota mt-1">Con una fecha posterior a {{ $fecha($trabajador['vigenteDesde']) }} se guarda como sueldo nuevo y el anterior queda en el historial.</p>
                            @endif
                        </div>
                        <div class="is-ancho">
                            <x-forms.textarea name="observacion" :id="$clave.'-observacion'" label="Observación" rows="2" maxlength="500" :sticky="$sticky" :value="$trabajador['observacion']" />
                        </div>
                    </div>

                    <div class="config-pagos-modal-pie">
                        <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                        <x-ui.button type="submit">{{ $configurado ? 'Guardar cambios' : 'Guardar sueldo' }}</x-ui.button>
                    </div>
                </form>
            </x-ui.modal>
        @endif
    </div>
@endif
