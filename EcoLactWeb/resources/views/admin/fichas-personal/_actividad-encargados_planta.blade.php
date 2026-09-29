{{-- Lotes que registró el encargado (lotes_produccion.registrado_por_user_id). --}}
@php
    $etiquetasEstado = ['EN_PROCESO' => 'En proceso', 'FINALIZADO' => 'Finalizado'];
@endphp

@if ($panel->actividad === [])
    <p class="fichas-panel-sin-datos">{{ $config['sinActividad'] }}</p>
@else
    <div class="fichas-mini-tabla">
        <table>
            <thead>
                <tr>
                    <th scope="col">Fecha</th>
                    <th scope="col">Lote</th>
                    <th scope="col">Producto</th>
                    <th scope="col" class="is-num">Leche</th>
                    <th scope="col">Estado</th>
                    <th scope="col" class="is-accion">Ver</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($panel->actividad as $lote)
                    <tr>
                        <td>{{ $lote['fecha'] }}</td>
                        <td class="font-semibold">{{ $lote['codigo'] }}</td>
                        <td class="fichas-mini-producto" title="{{ $lote['productos'] }}">{{ $lote['productos'] }}</td>
                        <td class="is-num">{{ number_format($lote['litros'], 2) }} L</td>
                        <td><x-ui.badge compact :status="$lote['estado']">{{ $etiquetasEstado[$lote['estado']] ?? str($lote['estado'])->replace('_', ' ')->lower()->ucfirst() }}</x-ui.badge></td>
                        <td class="is-accion"><a href="{{ route('admin.produccion.show', $lote['id']) }}" class="fichas-ver" aria-label="Ver lote {{ $lote['codigo'] }}" title="Ver lote"><x-ui.icon name="eye" class="h-4 w-4" /></a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
