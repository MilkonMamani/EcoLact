{{--
    Panel derecho de Acopiadores: se pinta en la carga inicial (index) y como fragmento
    (admin.acopiadores.panel) al elegir otra fila o tras activar/desactivar desde el switch.
    Mismo patrón que Proveedores, adaptado al dominio: la zona tiene pestaña propia (con su
    historial y su único "Editar"), y Pagos es un resumen de consulta
    (x-pagos.pago-mensual) que enlaza a Pagar a trabajadores. El QR que se
    muestra es su QR real de asistencia (trabajadores.qr_token); si no tiene, no se inventa.

    @var \App\Application\Acopiador\AcopiadorPanelData $panel
    @var list<array{id: int, nombre: string}> $zonasActivas
--}}
@php
    $limite = \App\Infrastructure\Persistence\Queries\EloquentAcopiadorPanelQuery::LIMITE;
    $etiquetasEntrega = ['PENDIENTE_ANALISIS' => 'Pendiente análisis', 'ANALIZADA' => 'Analizada', 'ANULADA' => 'Anulada'];
    $pestanas = ['resumen' => 'Resumen', 'entregas' => 'Entregas', 'zona' => 'Zona', 'asistencia' => 'Asistencia', 'pagos' => 'Pagos'];
    $modalJornadas = 'modal-jornadas-acopiador-'.$panel->id;
    $opcionesZona = collect($zonasActivas)->pluck('nombre', 'id')->all();
@endphp

<div class="fichas-panel-contenido" data-fichas-contenido data-ficha-id="{{ $panel->id }}" data-estado="{{ $panel->estado }}">
    <x-fichas.panel-cabecera :ficha="$panel" />

    <x-fichas.panel-tabs :pestanas="$pestanas" etiqueta="Información del acopiador" />

    <div class="fichas-panel-cuerpo">
        {{-- ------------------------------------------------------------ Resumen --}}
        <section id="ficha-panel-resumen" role="tabpanel" aria-labelledby="ficha-tab-resumen" data-fichas-tab-panel="resumen">
            <x-fichas.datos-personales :ficha="$panel" :modal-editar="'modal-editar-acopiador-'.$panel->id" />

            <dl class="fichas-resumen">
                <div><dt>Zona actual</dt><dd>{{ $panel->zona }}</dd></div>
                <div><dt>Entregas registradas</dt><dd class="is-num">{{ $panel->entregasTotal }}</dd></div>
                <div><dt>Litros acopiados</dt><dd class="is-num">{{ number_format($panel->litrosTotales, 2) }} L</dd></div>
            </dl>
        </section>

        {{-- ------------------------------------------------------------ Entregas --}}
        <section id="ficha-panel-entregas" role="tabpanel" aria-labelledby="ficha-tab-entregas" data-fichas-tab-panel="entregas" hidden>
            @if ($panel->entregas === [])
                <p class="fichas-panel-sin-datos">Este acopiador todavía no registró entregas.</p>
            @else
                <div class="fichas-mini-tabla">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Fecha</th>
                                <th scope="col">Proveedor</th>
                                <th scope="col">Zona</th>
                                <th scope="col" class="is-num">Litros</th>
                                <th scope="col">Identificación</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="is-accion">Ver</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($panel->entregas as $entrega)
                                <tr>
                                    <td>{{ $entrega['fecha'] }}</td>
                                    <td class="fichas-mini-producto" title="{{ $entrega['proveedor'] }}">{{ $entrega['proveedor'] }}</td>
                                    <td class="is-muted">{{ $entrega['zona'] }}</td>
                                    <td class="is-num font-semibold">{{ number_format($entrega['litros'], 2) }}</td>
                                    <td class="is-muted">{{ $entrega['identificacion'] }}</td>
                                    <td><x-ui.badge compact :status="$entrega['estado']">{{ $etiquetasEntrega[$entrega['estado']] ?? str($entrega['estado'])->replace('_', ' ')->lower()->ucfirst() }}</x-ui.badge></td>
                                    <td class="is-accion"><a href="{{ route('admin.entregas.show', $entrega['id']) }}" class="fichas-ver" aria-label="Ver entrega del {{ $entrega['fecha'] }}" title="Ver entrega"><x-ui.icon name="eye" class="h-4 w-4" /></a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($panel->entregasTotal > $limite)
                    <p class="fichas-panel-pie">Últimas {{ $limite }} de {{ $panel->entregasTotal }} entregas.</p>
                @endif
            @endif
        </section>

        {{-- ------------------------------------------------------------ Zona --}}
        <section id="ficha-panel-zona" role="tabpanel" aria-labelledby="ficha-tab-zona" data-fichas-tab-panel="zona" hidden>
            <div class="fichas-seccion is-primera">
                <div class="fichas-seccion-head">
                    <h3>Zona actual</h3>
                    @if ($panel->estaActivo())
                        <button type="button" class="fichas-editar" data-modal-open="modal-zona-acopiador-{{ $panel->id }}" aria-label="Cambiar la zona de {{ $panel->nombreCompleto() }}">
                            <x-ui.icon name="pencil" class="h-3.5 w-3.5" /> Editar
                        </button>
                    @endif
                </div>
                <p class="fichas-zona-actual">{{ $panel->zona }}</p>
                @unless ($panel->estaActivo())
                    <p class="fichas-nota">Inactivo: la zona se asigna de nuevo al reactivarlo desde el switch del listado.</p>
                @endunless
            </div>

            <div class="fichas-seccion">
                <div class="fichas-seccion-head"><h3>Historial de zonas</h3></div>
                @if ($panel->historialZonas === [])
                    <p class="fichas-panel-sin-datos">Sin historial de zonas.</p>
                @else
                    <div class="fichas-mini-tabla">
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">Zona</th>
                                    <th scope="col">Desde</th>
                                    <th scope="col">Hasta</th>
                                    <th scope="col">Motivo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($panel->historialZonas as $tramo)
                                    <tr>
                                        <td class="font-semibold">{{ $tramo['zona'] }}</td>
                                        <td class="is-muted">{{ $tramo['desde'] }}</td>
                                        <td @class(['is-muted' => $tramo['hasta'] !== null])>{{ $tramo['hasta'] ?? 'Actual' }}</td>
                                        <td class="fichas-mini-producto is-muted" title="{{ $tramo['motivo'] }}">{{ $tramo['motivo'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>

        {{-- ------------------------------------------------------------ Asistencia --}}
        <section id="ficha-panel-asistencia" role="tabpanel" aria-labelledby="ficha-tab-asistencia" data-fichas-tab-panel="asistencia" hidden>
            <x-fichas.asistencia :control-periodo="$controlPeriodo" :modal-jornadas="$modalJornadas" :url-jornadas="route('admin.trabajadores.jornadas.index', $panel->userId)" etiqueta-jornadas="Ver jornadas de este acopiador" />
        </section>

        {{-- ------------------------------------------------------------ Pagos --}}
        <section id="ficha-panel-pagos" role="tabpanel" aria-labelledby="ficha-tab-pagos" data-fichas-tab-panel="pagos" hidden>
            <x-pagos.pago-mensual :control-periodo="$controlPeriodo" :dni="$panel->dni" />
        </section>
    </div>
</div>

{{-- Jornadas del periodo (lo rellena admin-jornadas.js al abrirlo desde la pestaña Asistencia). --}}
<x-ui.modal :id="$modalJornadas" title="Jornadas del periodo">
    @if ($controlPeriodo)
        <p class="mb-3 text-sm text-text-muted">{{ $controlPeriodo->periodoRango }}</p>
    @endif
    <div data-jornadas-contenido>
        <p class="text-sm text-text-muted">Cargando…</p>
    </div>
</x-ui.modal>

{{-- Formularios del acopiador seleccionado (fuera del contenido animado para que el modal quede fijo a la ventana). --}}
<x-fichas.modal-editar-personal :ficha="$panel" :id="'modal-editar-acopiador-'.$panel->id" :action="route('admin.acopiadores.update', $panel->id)" entidad="acopiador" />

@if ($panel->estaActivo())
    <x-ui.modal :id="'modal-zona-acopiador-'.$panel->id" title="Editar zona" :open="$errors->any() && old('_modal') === 'zona-acopiador-'.$panel->id">
        <form method="POST" action="{{ route('admin.acopiadores.update', $panel->id) }}" class="space-y-4">
            @csrf
            @method('PATCH')
            <input type="hidden" name="_modal" value="zona-acopiador-{{ $panel->id }}">
            <x-forms.select name="zona_id" :id="'zona-acopiador-'.$panel->id" label="Zona" required :options="$opcionesZona" :value="$panel->zonaId" />
            <x-forms.input name="motivo_cambio_zona" :id="'motivo-zona-acopiador-'.$panel->id" label="Motivo del cambio" />
            <p class="text-xs text-text-muted">El tramo actual se cierra hoy y se abre uno nuevo en el historial de zonas.</p>
            <div class="flex justify-end gap-2 border-t border-text-muted/10 pt-4">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar zona</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endif
