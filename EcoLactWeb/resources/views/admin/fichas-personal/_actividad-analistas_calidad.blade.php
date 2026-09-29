{{-- Análisis que confirmó el analista (analisis_calidad.confirmado_por_user_id). --}}
@php
    $etiquetasResultado = ['ACEPTADA' => 'Aceptada', 'OBSERVADA' => 'Observada', 'RECHAZADA' => 'Rechazada'];
    $etiquetasEstado = ['CONFIRMADO' => 'Confirmado', 'BORRADOR' => 'Borrador'];
@endphp

@if ($panel->actividad === [])
    <p class="fichas-panel-sin-datos">{{ $config['sinActividad'] }}</p>
@else
    <div class="fichas-mini-tabla">
        <table>
            <thead>
                <tr>
                    <th scope="col">Fecha</th>
                    <th scope="col">Entrega</th>
                    <th scope="col">Proveedor</th>
                    <th scope="col">Resultado</th>
                    <th scope="col">Estado</th>
                    <th scope="col" class="is-accion">Ver</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($panel->actividad as $analisis)
                    <tr>
                        <td>{{ $analisis['fecha'] }}</td>
                        <td class="is-muted">Entrega #{{ $analisis['entregaId'] }}</td>
                        <td class="fichas-mini-producto" title="{{ $analisis['proveedor'] }}">{{ $analisis['proveedor'] }}</td>
                        <td>
                            @if ($analisis['resultado'])
                                <x-ui.badge compact :status="$analisis['resultado']">{{ $etiquetasResultado[$analisis['resultado']] ?? $analisis['resultado'] }}</x-ui.badge>
                            @else
                                <span class="is-muted">—</span>
                            @endif
                        </td>
                        <td><x-ui.badge compact :status="$analisis['estado']">{{ $etiquetasEstado[$analisis['estado']] ?? $analisis['estado'] }}</x-ui.badge></td>
                        <td class="is-accion"><a href="{{ route('admin.analisis-calidad.show', $analisis['id']) }}" class="fichas-ver" aria-label="Ver análisis de la entrega #{{ $analisis['entregaId'] }}" title="Ver análisis"><x-ui.icon name="eye" class="h-4 w-4" /></a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($panel->actividad !== [])
    <p class="fichas-panel-pie"><a href="{{ route('admin.analisis-calidad.index', ['analista_id' => $panel->userId]) }}">Ver todos sus análisis en Calidad →</a></p>
@endif
