@extends('layouts.acopiador')

@section('title', 'Mis recorridos')

@section('content')
    <x-ui.card title="Mis recorridos" subtitle="Recorridos que te asignó el administrador desde el módulo Zonas.">
        @if (count($recorridos) === 0)
            <x-ui.empty-state title="Sin recorridos asignados" description="Todavía no tienes un recorrido asignado. Contacta al administrador para que te asigne una ruta." :icon="false" />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Fecha</th>
                        <th class="py-2 pr-4">Zona / Ruta</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pl-4 text-right">&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recorridos as $recorrido)
                        @php($ruta = $rutasPorId->get($recorrido->rutaId()))
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 text-text">{{ $recorrido->fechaOperativa()->format('d/m/Y') }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $ruta?->zona?->nombre }} · {{ $ruta?->nombre }}</td>
                            <td class="py-3 pr-4"><x-ui.badge :status="$recorrido->estado()->value" /></td>
                            <td class="py-3 pl-4 text-right">
                                <x-ui.button :href="route('acopiador.recorridos.show', $recorrido->id())" variant="ghost" size="sm" as="a">Abrir →</x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>
@endsection
