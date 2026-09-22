@php
    $liquidacion = $detalle->liquidacion;
@endphp

@extends('layouts.proveedor')

@section('title', 'Liquidación #' . $liquidacion->id())

@section('content')
    <x-ui.button :href="route('proveedor.liquidaciones.index')" variant="ghost" size="sm" as="a">&larr; Volver a mis liquidaciones</x-ui.button>

    <x-ui.card :title="'Liquidación #' . $liquidacion->id()" subtitle="Resumen de tu cierre de pago.">
        <x-slot:actions>
            <x-ui.badge :status="$liquidacion->estado()->value" />
            @if ($liquidacion->tuvoIncidencia())
                <x-ui.badge status="OBSERVADA">Con incidencia</x-ui.badge>
            @endif
        </x-slot:actions>

        <div class="mb-4 flex items-center justify-between rounded-lg bg-primary-soft px-4 py-3">
            <span class="text-sm font-medium text-primary-dark">Total a pagar</span>
            <span class="text-xl font-semibold text-primary-dark">S/ {{ number_format($liquidacion->totalPagar(), 2) }}</span>
        </div>

        <div class="rounded-xl border border-text-muted/10 p-4">
            <h3 class="mb-3 text-sm font-semibold text-text">¿Cómo se calculó?</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-text-muted">Litros entregados</dt><dd class="text-text">{{ number_format($liquidacion->totalLitrosEntregados(), 2) }} L</dd></div>
                <div class="flex justify-between"><dt class="text-text-muted">Litros pagables</dt><dd class="text-text">{{ number_format($liquidacion->totalLitrosPagables(), 2) }} L</dd></div>
                <div class="flex justify-between"><dt class="text-text-muted">Precio aplicado</dt><dd class="text-text">S/ {{ number_format($liquidacion->precioLitroAplicado(), 4) }} / L</dd></div>
                <div class="flex justify-between border-t border-text-muted/10 pt-2"><dt class="text-text-muted">Subtotal</dt><dd class="text-text">S/ {{ number_format($liquidacion->subtotal(), 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-text-muted">Ajustes</dt><dd class="text-text">S/ {{ number_format($liquidacion->totalAjustes(), 2) }}</dd></div>
            </dl>
        </div>

        <div class="mt-4 rounded-xl border border-text-muted/10 p-4">
            <h3 class="mb-3 text-sm font-semibold text-text">Entregas incluidas</h3>
            @if (count($detalle->entregas) === 0)
                <x-ui.empty-state title="Sin entregas en el periodo" :icon="false" />
            @else
                <x-tables.table>
                    <thead>
                        <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                            <th class="py-2 pr-4">Fecha</th>
                            <th class="py-2 pr-4">Litros</th>
                            <th class="py-2 pr-4">Resultado</th>
                            <th class="py-2 pr-4">Pagables</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($detalle->entregas as $fila)
                            <tr class="border-b border-text-muted/5 last:border-0">
                                <td class="py-2 pr-4 text-text">{{ $fila['fecha'] }}</td>
                                <td class="py-2 pr-4 text-text">{{ number_format($fila['litros'], 2) }} L</td>
                                <td class="py-2 pr-4">
                                    @if ($fila['resultado'])
                                        <x-ui.badge :status="$fila['resultado']" />
                                    @else
                                        <span class="text-xs text-text-muted">No analizada</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-text">{{ number_format($fila['pagables'], 2) }} L</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-tables.table>
            @endif
        </div>

        @if ($detalle->incidencia)
            <div class="mt-4 rounded-xl border border-warning/30 bg-warning/5 p-4">
                <h3 class="mb-2 text-sm font-semibold text-warning">⚠ Incidencia de calidad</h3>
                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-text-muted">Fecha</dt><dd class="text-text">{{ $detalle->incidencia['fecha'] }}</dd></div>
                    <div class="flex justify-between"><dt class="text-text-muted">Resultado</dt><dd><x-ui.badge :status="$detalle->incidencia['resultado']" /></dd></div>
                    <div class="flex justify-between"><dt class="text-text-muted">Referencia</dt><dd><a class="text-primary hover:underline" href="{{ route('proveedor.entregas.show', $detalle->incidencia['entregaId']) }}">Entrega #{{ $detalle->incidencia['entregaId'] }}</a></dd></div>
                </dl>
            </div>
        @endif

        @if (count($detalle->compras) > 0)
            <div class="mt-4 rounded-xl border border-text-muted/10 p-4">
                <h3 class="mb-3 text-sm font-semibold text-text">Compras del periodo</h3>
                <x-tables.table>
                    <thead>
                        <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                            <th class="py-2 pr-4">Cantidad</th>
                            <th class="py-2 pr-4">Importe</th>
                            <th class="py-2 pr-4">Forma de pago</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($detalle->compras as $compra)
                            <tr class="border-b border-text-muted/5 last:border-0">
                                <td class="py-2 pr-4 text-text">{{ number_format($compra->cantidad(), 2) }}</td>
                                <td class="py-2 pr-4 text-text">S/ {{ number_format($compra->importeTotal(), 2) }}</td>
                                <td class="py-2 pr-4 text-text-muted">{{ $compra->formaPago() === 'DESCUENTO_PLANILLA' ? 'Descuento en planilla' : 'Contado' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-tables.table>
            </div>
        @endif

        @if (count($detalle->ajustes) > 0)
            <div class="mt-4 rounded-xl border border-text-muted/10 p-4">
                <h3 class="mb-3 text-sm font-semibold text-text">Ajustes</h3>
                <x-tables.table>
                    <thead>
                        <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                            <th class="py-2 pr-4">Descripción</th>
                            <th class="py-2 pl-4 text-right">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($detalle->ajustes as $ajuste)
                            <tr class="border-b border-text-muted/5 last:border-0">
                                <td class="py-2 pr-4 text-text">{{ $ajuste->descripcion() }}</td>
                                <td class="py-2 pl-4 text-right text-text">S/ {{ number_format($ajuste->monto(), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-tables.table>
            </div>
        @endif

        <div class="mt-4 rounded-xl border border-text-muted/10 p-4">
            <h3 class="mb-3 text-sm font-semibold text-text">Información del pago</h3>

            @if ($detalle->saldoPendiente > 0)
                <p class="text-sm text-text">🟡 Pendiente de pago</p>
                <p class="mt-1 text-sm text-text-muted">Monto pendiente: <strong class="text-text">S/ {{ number_format($detalle->saldoPendiente, 2) }}</strong></p>
            @else
                <p class="text-sm text-success">🟢 Pagado</p>
            @endif
            <p class="mt-1 text-xs text-text-muted">El pago es siempre en efectivo, entregado en sobre cerrado.</p>

            @if (count($detalle->pagos) > 0)
                <h4 class="mt-4 mb-2 text-xs font-semibold uppercase tracking-wide text-text-muted">Historial de pagos</h4>
                <x-tables.table>
                    <thead>
                        <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                            <th class="py-2 pr-4">Fecha</th>
                            <th class="py-2 pr-4">Monto</th>
                            <th class="py-2 pr-4">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($detalle->pagos as $pago)
                            <tr class="border-b border-text-muted/5 last:border-0">
                                <td class="py-2 pr-4 text-text">{{ $pago->fechaPago()->format('d/m/Y H:i') }}</td>
                                <td class="py-2 pr-4 text-text">S/ {{ number_format($pago->monto(), 2) }}</td>
                                <td class="py-2 pr-4"><x-ui.badge :status="$pago->estado()->value" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-tables.table>
            @endif
        </div>
    </x-ui.card>
@endsection
