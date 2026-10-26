{{--
    Historial de ventas: filtros compactos (GET, se aplican solos con JS) y tabla con lo
    comercial: venta, fecha, cliente, producto, total, pago, estado. El resto va en el detalle.
--}}
@php
    $filtrosActivos = request()->anyFilled(['q', 'forma_pago', 'estado']) || $filtro->periodo !== 'esta_semana';
@endphp

<div class="vpos-historial">
    <h2 class="vpos-seccion-titulo">Historial de ventas</h2>

    <form method="GET" action="{{ route($portal.'.ventas.index') }}" class="vpos-filtros" data-vpos-filtros>
        <input type="hidden" name="tab" value="historial">

        <label class="fichas-search vpos-search vpos-filtro-buscar">
            <span class="sr-only">Buscar cliente, producto o venta</span>
            <x-ui.icon name="magnifying-glass" class="h-[18px] w-[18px] shrink-0" />
            <input type="search" name="q" value="{{ $filtro->busqueda }}" placeholder="Buscar cliente, producto o venta..." autocomplete="off" maxlength="100">
        </label>

        <label>
            <span class="sr-only">Periodo</span>
            <select name="periodo" class="pt-campo" data-vpos-periodo>
                @foreach (['hoy' => 'Hoy', 'esta_semana' => 'Esta semana', 'este_mes' => 'Este mes', 'personalizado' => 'Personalizado'] as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected($filtro->periodo === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </label>

        <label>
            <span class="sr-only">Pago</span>
            <select name="forma_pago" class="pt-campo">
                <option value="">Todos los pagos</option>
                <option value="CONTADO" @selected($filtro->formaPago === 'CONTADO')>Contado</option>
                <option value="DESCUENTO_PLANILLA" @selected($filtro->formaPago === 'DESCUENTO_PLANILLA')>Descuento semanal</option>
            </select>
        </label>

        <label>
            <span class="sr-only">Estado</span>
            <select name="estado" class="pt-campo">
                <option value="">Todos los estados</option>
                <option value="REGISTRADA" @selected($filtro->estado === 'REGISTRADA')>Registrada</option>
                <option value="ANULADA" @selected($filtro->estado === 'ANULADA')>Anulada</option>
            </select>
        </label>

        <div class="caja-rango vpos-filtro-rango" data-vpos-rango @if ($filtro->periodo !== 'personalizado') hidden @endif>
            <x-ui.icon name="calendar" class="h-4 w-4 shrink-0" />
            <label><span class="sr-only">Desde</span><input type="date" name="desde" value="{{ $filtro->fechaDesde->format('Y-m-d') }}"></label>
            <span aria-hidden="true">–</span>
            <label><span class="sr-only">Hasta</span><input type="date" name="hasta" value="{{ $filtro->fechaHasta->format('Y-m-d') }}"></label>
        </div>

        <noscript><button type="submit" class="fichas-btn is-primary is-sm">Filtrar</button></noscript>
        @if ($filtrosActivos)
            <a href="{{ route($portal.'.ventas.index') }}?tab=historial" class="fichas-btn is-outline is-sm vpos-limpiar">Limpiar</a>
        @endif
    </form>

    <div class="vpos-tabla">
        <table>
            <thead>
                <tr>
                    <th scope="col">Venta</th>
                    <th scope="col">Fecha</th>
                    <th scope="col">Cliente</th>
                    <th scope="col">Producto</th>
                    <th scope="col" class="is-num">Total</th>
                    <th scope="col">Pago</th>
                    <th scope="col">Estado</th>
                    <th scope="col" class="is-accion">Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($datos->filas as $fila)
                    <tr @class(['is-selected' => ($seleccion['id'] ?? null) === $fila['id']]) data-vpos-fila="{{ $fila['id'] }}">
                        <td class="vpos-codigo">{{ $fila['codigo'] }}</td>
                        <td class="vpos-fecha">{{ $fila['fechaCorta'] }}</td>
                        <td class="vpos-cliente">{{ $fila['clienteNombre'] }}</td>
                        <td>{{ $fila['productoNombre'] }}</td>
                        <td class="is-num vpos-total">S/ {{ number_format($fila['importeTotal'], 2) }}</td>
                        <td>
                            <span @class(['vpos-pago', 'is-descuento' => $fila['formaPago'] === 'DESCUENTO_PLANILLA'])>{{ \App\Http\Support\PantallaVentas::formaPago($fila['formaPago']) }}</span>
                        </td>
                        <td><span @class(['vpos-estado', 'is-anulada' => $fila['estado'] === 'ANULADA'])>{{ ucfirst(mb_strtolower($fila['estado'])) }}</span></td>
                        <td class="is-accion">
                            <a href="{{ route($portal.'.ventas.show', $fila['id']) }}{{ request()->getQueryString() ? '?'.request()->getQueryString() : '' }}" class="vpos-ver" data-vpos-ver="{{ $fila['id'] }}" aria-label="Ver venta {{ $fila['codigo'] }}">Ver</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="vpos-tabla-vacia">
                            @if ($filtrosActivos)
                                <strong>No hay ventas para estos filtros</strong> Prueba con otro periodo, pago o estado.
                            @else
                                <strong>Aún no hay ventas esta semana</strong> Las ventas que registres en Nueva venta aparecerán aquí.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if (count($datos->filas) > 0)
        <p class="vpos-pie">{{ count($datos->filas) }} {{ count($datos->filas) === 1 ? 'venta' : 'ventas' }} · S/ {{ number_format($datos->kpis['totalVendido'], 2) }}</p>
    @endif
</div>
