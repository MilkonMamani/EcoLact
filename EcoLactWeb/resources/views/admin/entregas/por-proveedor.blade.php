@extends('layouts.admin')

@section('title', 'Entregas del proveedor')

@section('content')
    <x-ui.card title="Entregas del proveedor" subtitle="Historial de acopio para este proveedor.">
        @if (count($entregas) === 0)
            <x-ui.empty-state title="No hay entregas registradas" description="Este proveedor todavía no registra entregas." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Fecha</th>
                        <th class="py-2 pr-4">Litros</th>
                        <th class="py-2 pr-4">Método</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pl-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entregas as $entrega)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 text-text">{{ $entrega->fechaHora()->format('d/m/Y H:i') }}</td>
                            <td class="py-3 pr-4 font-medium text-text">{{ number_format($entrega->litros(), 2) }} L</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $entrega->metodoIdentificacion() }}</td>
                            <td class="py-3 pr-4"><x-ui.badge :status="$entrega->estado()->value" /></td>
                            <td class="py-3 pl-4">
                                <x-tables.actions>
                                    <x-ui.action-button type="view" label="Ver entrega" :href="route('admin.entregas.show', $entrega->id())" />
                                </x-tables.actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>
@endsection
