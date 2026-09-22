@extends('layouts.proveedor')

@section('title', 'Mis liquidaciones')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card label="Última liquidación" :value="$resumen?->ultimaLiquidacion ? 'S/ '.number_format($resumen->ultimaLiquidacion->totalPagar(), 2) : '—'" />
        <x-ui.stat-card label="Por cobrar" :value="'S/ '.number_format($resumen->porCobrar ?? 0, 2)" accent="warning" />
        <x-ui.stat-card label="Pagado" :value="'S/ '.number_format($resumen->pagado ?? 0, 2)" accent="success" />
        <x-ui.stat-card label="Liquidaciones" :value="$resumen->totalLiquidaciones ?? 0" />
    </div>

    <x-ui.card title="Mis liquidaciones" subtitle="Consulta tus liquidaciones, montos a recibir y estado de tus pagos.">
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
            <div class="w-52">
                <x-forms.select name="estado" label="Estado" :options="['PENDIENTE' => 'Pendiente', 'PARCIALMENTE_PAGADA' => 'Pago parcial', 'PAGADA' => 'Pagada']" :value="request('estado')" placeholder="Todos" />
            </div>
            <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
            @if (request()->filled('estado'))
                <x-ui.button :href="route('proveedor.liquidaciones.index')" variant="ghost" size="sm" as="a">Limpiar</x-ui.button>
            @endif
        </form>

        @if (count($filas) === 0)
            <x-ui.empty-state title="Aún no tienes liquidaciones" description="Las liquidaciones correspondientes a tus entregas aparecerán aquí cuando sean generadas." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Fecha de cálculo</th>
                        <th class="py-2 pr-4">Litros pagables</th>
                        <th class="py-2 pr-4">Total a pagar</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pr-4">Saldo pendiente</th>
                        <th class="py-2 pl-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($filas as $fila)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 text-text">{{ $fila['liquidacion']->fechaCalculo()->format('d/m/Y') }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ number_format($fila['liquidacion']->totalLitrosPagables(), 2) }} L</td>
                            <td class="py-3 pr-4 font-medium text-text">S/ {{ number_format($fila['liquidacion']->totalPagar(), 2) }}</td>
                            <td class="py-3 pr-4"><x-ui.badge :status="$fila['liquidacion']->estado()->value" /></td>
                            <td class="py-3 pr-4 text-text-muted">S/ {{ number_format($fila['saldoPendiente'], 2) }}</td>
                            <td class="py-3 pl-4">
                                <x-tables.actions>
                                    <x-ui.action-button type="view" label="Ver liquidación" :href="route('proveedor.liquidaciones.show', $fila['liquidacion']->id())" />
                                </x-tables.actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>
@endsection
