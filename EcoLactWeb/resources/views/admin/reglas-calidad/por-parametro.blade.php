@extends('layouts.admin')

@section('title', 'Reglas del parámetro')

@section('content')
    <x-ui.card title="Reglas de calidad" subtitle="Reglas definidas para este parámetro.">
        @if (count($reglas) === 0)
            <x-ui.empty-state title="Sin reglas registradas" description="Este parámetro todavía no tiene reglas de evaluación." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Mínimo</th>
                        <th class="py-2 pr-4">Máximo</th>
                        <th class="py-2 pr-4">Si incumple</th>
                        <th class="py-2 pr-4">Prioridad</th>
                        <th class="py-2 pr-4">Vigencia</th>
                        <th class="py-2 pr-4">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reglas as $regla)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 text-text-muted">{{ $regla->valorMinimo() ?? '—' }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $regla->valorMaximo() ?? '—' }}</td>
                            <td class="py-3 pr-4"><x-ui.badge :status="$regla->resultadoIncumplimiento()->value" /></td>
                            <td class="py-3 pr-4 text-text-muted">{{ $regla->prioridad() }}</td>
                            <td class="py-3 pr-4 text-text-muted">
                                {{ $regla->fechaInicio()->format('d/m/Y') }} – {{ $regla->fechaFin()?->format('d/m/Y') ?? 'vigente' }}
                            </td>
                            <td class="py-3 pr-4"><x-ui.badge :status="$regla->estado()" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>
@endsection
