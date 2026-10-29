@extends('layouts.admin')

@section('title', 'Movimientos de insumos')
@section('body-class', 'admin-fichas-page')

{{--
    INSUMOS > MOVIMIENTOS: entradas y salidas de los insumos (paginado en SQL), sin los movimientos
    de leche por Acopio (se consultan en Acopio). Se llega desde el efecto en inventario de una compra.
    Cada ingreso por compra enlaza con la compra que lo generó (origen COMPRA_INSUMO #id), nunca por texto.
    Variables: $movimientos (paginador de InsumoMovimientoModel + compra_codigo), $filtros, $insumos.
--}}
@use('App\Domain\Produccion\UnidadMedida')
@use('App\Domain\Produccion\InsumoMovimiento')
@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
    $origenes = [
        InsumoMovimiento::ORIGEN_COMPRA_INSUMO => 'Compra',
        'LOTE_PRODUCCION' => 'Producción',
    ];
@endphp

@section('content')
    <div class="fichas">
        <header class="fichas-header">
            <div class="min-w-0">
                <x-layout.breadcrumbs :items="[['Inventario', null], ['Insumos', route('admin.insumos.index')], ['Movimientos', null]]" />
                <h1 class="fichas-title">Movimientos de insumos</h1>
                <p class="fichas-subtitle">Entradas, consumos y ajustes del inventario, con la operación que los originó.</p>
            </div>
            <a href="{{ route('admin.insumos.index') }}" class="fichas-btn is-outline"><x-ui.icon name="arrow-left" class="h-4 w-4" /> Volver a Insumos</a>
        </header>

        <form method="GET" action="{{ route('admin.insumos.movimientos') }}" class="fichas-filtros mt-5">
            <div class="fichas-filtros-fila">
                <x-forms.select name="insumo_id" label="Insumo" :options="$insumos" :value="$filtros['insumo_id']" placeholder="Todos" :sticky="false" />
                <x-forms.select name="tipo" label="Tipo" :options="['ENTRADA' => 'Entradas', 'SALIDA' => 'Salidas']" :value="$filtros['tipo']" placeholder="Todos" :sticky="false" />
                <x-forms.input name="desde" type="date" label="Desde" :value="$filtros['desde']" :sticky="false" />
                <x-forms.input name="hasta" type="date" label="Hasta" :value="$filtros['hasta']" :sticky="false" />
                <div class="fichas-filtros-acciones">
                    <button type="submit" class="fichas-btn is-dark">Filtrar</button>
                    @if (array_filter($filtros))
                        <a href="{{ route('admin.insumos.movimientos') }}" class="fichas-btn is-outline">Limpiar</a>
                    @endif
                </div>
            </div>
        </form>

        @if ($movimientos->isEmpty())
            <div class="fichas-vacio"><x-ui.icon name="package" class="h-5 w-5 shrink-0" /> <span>No hay movimientos para estos filtros.</span></div>
        @else
            <div class="fichas-tabla compras-tabla">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Fecha</th>
                            <th scope="col">Insumo</th>
                            <th scope="col">Motivo</th>
                            <th scope="col">Origen</th>
                            <th scope="col" class="is-num">Cantidad</th>
                            <th scope="col">Registrado por</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($movimientos as $movimiento)
                            @php $simbolo = UnidadMedida::simbolo((string) $movimiento->insumo?->unidad_base); @endphp
                            <tr>
                                <td data-etiqueta="Fecha">{{ $movimiento->fecha?->timezone(config('app.zona_horaria_negocio'))->format('d/m/Y H:i') }}</td>
                                <td data-etiqueta="Insumo"><a href="{{ route('admin.insumos.index', ['insumo' => $movimiento->insumo_id]) }}" class="fichas-nombre">{{ $movimiento->insumo?->nombre ?? 'Insumo eliminado' }}</a></td>
                                <td class="is-muted" data-etiqueta="Motivo">{{ \App\Domain\Shared\Enums\MotivoMovimientoInsumo::tryFrom($movimiento->motivo)?->etiqueta() ?? $movimiento->motivo }}</td>
                                <td data-etiqueta="Origen">
                                    @if ($movimiento->origen_tipo === InsumoMovimiento::ORIGEN_COMPRA_INSUMO && $movimiento->origen_id)
                                        <a href="{{ route('admin.compras.show', $movimiento->origen_id) }}" class="font-medium text-primary hover:text-primary-dark">Compra {{ $movimiento->compra_codigo ?? \App\Domain\Produccion\CompraInsumo::codigoPara((int) $movimiento->origen_id) }}</a>
                                    @elseif ($movimiento->origen_tipo)
                                        <span class="text-text-muted">{{ $origenes[$movimiento->origen_tipo] ?? $movimiento->origen_tipo }} #{{ $movimiento->origen_id }}</span>
                                    @else
                                        <span class="text-text-muted">{{ $movimiento->observacion ?? '—' }}</span>
                                    @endif
                                </td>
                                <td @class(['is-num font-semibold', 'text-success' => $movimiento->tipo === 'ENTRADA', 'text-danger' => $movimiento->tipo !== 'ENTRADA']) data-etiqueta="Cantidad">{{ $movimiento->tipo === 'ENTRADA' ? '+' : '−' }}{{ $fmt($movimiento->cantidad) }} {{ $simbolo }}</td>
                                <td class="is-muted" data-etiqueta="Registrado por">{{ $movimiento->registradoPor?->username ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <x-tables.pagination :paginator="$movimientos" noun="movimientos" class="fichas-pager" />
    </div>
@endsection
