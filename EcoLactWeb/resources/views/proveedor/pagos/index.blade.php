@extends('layouts.proveedor')

@section('title', 'Mis pagos')

@section('content')
    <x-ui.card title="Mis pagos" subtitle="Pagos recibidos por tus entregas de leche.">
        @if (count($pagos) === 0)
            <x-ui.empty-state title="No tienes pagos registrados" description="Cuando se registre un pago a tu nombre, aparecerá aquí." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Fecha</th>
                        <th class="py-2 pr-4">Monto</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pl-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pagos as $pago)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 text-text">{{ $pago->fechaPago()->format('d/m/Y') }}</td>
                            <td class="py-3 pr-4 font-medium text-text">S/ {{ number_format($pago->monto(), 2) }}</td>
                            <td class="py-3 pr-4"><x-ui.badge compact :status="$pago->estado()->value" /></td>
                            <td class="py-3 pl-4">
                                <x-tables.actions>
                                    <x-ui.action-button type="view" label="Ver pago" :href="route('proveedor.pagos.show', $pago->id())" />
                                </x-tables.actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>
@endsection
