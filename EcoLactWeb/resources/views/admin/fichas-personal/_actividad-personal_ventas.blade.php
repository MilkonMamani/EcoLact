{{--
    Ventas que registró el vendedor (ventas_producto.registrado_por_user_id). El filtro de tipo
    de cliente son enlaces a esta misma pantalla: admin-fichas.js los resuelve dentro del panel.
    Los tipos son los del dominio (PROVEEDOR, PUBLICO, MAYORISTA).
--}}
@php
    $tipos = ['PROVEEDOR' => 'Proveedor', 'PUBLICO' => 'Público general', 'MAYORISTA' => 'Mayorista'];
    $filtros = [null => 'Todos'] + $tipos;
@endphp

<nav class="fichas-segmento" aria-label="Tipo de cliente">
    @foreach ($filtros as $valor => $texto)
        <a href="{{ request()->fullUrlWithQuery(['tipo_cliente' => $valor ?: null]) }}" @if (($valor ?: null) === $panel->tipoCliente) aria-current="true" @endif>{{ $texto }}</a>
    @endforeach
</nav>

@if ($panel->actividad === [])
    <p class="fichas-panel-sin-datos">{{ $panel->tipoCliente ? 'No hay ventas de este tipo de cliente.' : $config['sinActividad'] }}</p>
@else
    <div class="fichas-mini-tabla">
        <table>
            <thead>
                <tr>
                    <th scope="col">Fecha</th>
                    <th scope="col">Comprobante</th>
                    <th scope="col">Cliente</th>
                    <th scope="col">Tipo</th>
                    <th scope="col" class="is-num">Total</th>
                    <th scope="col">Pago</th>
                    <th scope="col" class="is-accion">Ver</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($panel->actividad as $venta)
                    <tr>
                        <td>{{ $venta['fecha'] }}</td>
                        <td class="is-muted">Venta #{{ $venta['id'] }}</td>
                        <td class="fichas-mini-producto" title="{{ $venta['cliente'] }}">{{ $venta['cliente'] }}</td>
                        <td class="is-muted">{{ $tipos[$venta['tipoCliente']] ?? $venta['tipoCliente'] }}</td>
                        <td class="is-num font-semibold">S/ {{ number_format($venta['total'], 2) }}</td>
                        <td class="is-muted">{{ $venta['formaPago'] === 'DESCUENTO_PLANILLA' ? 'Descuento semanal' : 'Contado' }}</td>
                        <td class="is-accion"><a href="{{ route('admin.ventas.show', $venta['id']) }}" class="fichas-ver" aria-label="Ver venta #{{ $venta['id'] }}" title="Ver venta"><x-ui.icon name="eye" class="h-4 w-4" /></a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
