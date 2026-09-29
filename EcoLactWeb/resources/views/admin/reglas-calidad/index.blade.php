@extends('layouts.admin')

@section('title', 'Reglas de calidad')

@section('content')
    <x-ui.card title="Reglas de calidad" subtitle="Límites usados para evaluar cada parámetro de calidad.">
        <x-slot:actions>
            @can('create', \App\Infrastructure\Persistence\Models\ReglaCalidadModel::class)
                <x-ui.button data-modal-open="modal-nueva-regla" size="sm" as="button" type="button">Nueva regla</x-ui.button>
            @endcan
        </x-slot:actions>

        @php($nombresParametros = collect($parametros)->mapWithKeys(fn ($p) => [$p->id() => $p->nombre().' ('.$p->codigo().')']))

        @if (count($reglas) === 0)
            <x-ui.empty-state title="No hay reglas de calidad" description="Define reglas para que los análisis puedan evaluarse automáticamente." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Parámetro</th>
                        <th class="py-2 pr-4">Mínimo</th>
                        <th class="py-2 pr-4">Máximo</th>
                        <th class="py-2 pr-4">Si incumple</th>
                        <th class="py-2 pr-4">Prioridad</th>
                        <th class="py-2 pr-4">Vigencia</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pl-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reglas as $regla)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 text-text">{{ $nombresParametros[$regla->parametroId()] ?? 'Parámetro #'.$regla->parametroId() }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $regla->valorMinimo() ?? '—' }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $regla->valorMaximo() ?? '—' }}</td>
                            <td class="py-3 pr-4"><x-ui.badge compact :status="$regla->resultadoIncumplimiento()->value" /></td>
                            <td class="py-3 pr-4 text-text-muted">{{ $regla->prioridad() }}</td>
                            <td class="py-3 pr-4 text-text-muted">
                                {{ $regla->fechaInicio()->format('d/m/Y') }} – {{ $regla->fechaFin()?->format('d/m/Y') ?? 'vigente' }}
                            </td>
                            <td class="py-3 pr-4"><x-ui.badge compact :status="$regla->estado()" /></td>
                            <td class="py-3 pl-4">
                                <x-tables.actions>
                                    <x-ui.action-button type="edit" label="Editar regla de calidad" data-modal-open="modal-editar-regla-{{ $regla->id() }}" />
                                </x-tables.actions>
                            </td>
                        </tr>

                        <x-ui.modal :id="'modal-editar-regla-' . $regla->id()" title="Editar regla de calidad">
                            <form method="POST" action="{{ route('admin.reglas-calidad.update', $regla->id()) }}" class="space-y-4">
                                @csrf
                                @method('PATCH')
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <x-forms.input name="valor_minimo" type="number" label="Valor mínimo" :value="$regla->valorMinimo()" />
                                    <x-forms.input name="valor_maximo" type="number" label="Valor máximo" :value="$regla->valorMaximo()" />
                                    <x-forms.select name="resultado_incumplimiento" label="Resultado si incumple" required :options="['ACEPTADA' => 'Aceptada', 'OBSERVADA' => 'Observada', 'RECHAZADA' => 'Rechazada']" :value="$regla->resultadoIncumplimiento()->value" />
                                    <x-forms.input name="prioridad" type="number" label="Prioridad" :value="$regla->prioridad()" />
                                    <x-forms.input name="fecha_inicio" type="date" label="Fecha inicio" :value="$regla->fechaInicio()->format('Y-m-d')" />
                                    <x-forms.input name="fecha_fin" type="date" label="Fecha fin" :value="$regla->fechaFin()?->format('Y-m-d')" />
                                    <x-forms.select name="estado" label="Estado" required :options="['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo']" :value="$regla->estado()" />
                                </div>
                                <div class="flex justify-end gap-2 pt-2">
                                    <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                                    <x-ui.button type="submit">Guardar cambios</x-ui.button>
                                </div>
                            </form>
                        </x-ui.modal>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>

    <x-ui.modal id="modal-nueva-regla" title="Nueva regla de calidad">
        <form method="POST" action="{{ route('admin.reglas-calidad.store') }}" class="space-y-4">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <x-forms.select name="parametro_id" label="Parámetro" required :options="collect($parametros)->mapWithKeys(fn ($p) => [$p->id() => $p->nombre().' ('.$p->codigo().')'])" />
                <x-forms.input name="prioridad" type="number" label="Prioridad" value="1" />
                <x-forms.input name="valor_minimo" type="number" label="Valor mínimo" />
                <x-forms.input name="valor_maximo" type="number" label="Valor máximo" />
                <x-forms.select name="resultado_incumplimiento" label="Resultado si incumple" required :options="['ACEPTADA' => 'Aceptada', 'OBSERVADA' => 'Observada', 'RECHAZADA' => 'Rechazada']" />
                <x-forms.select name="estado" label="Estado" required :options="['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo']" value="ACTIVO" />
                <x-forms.input name="fecha_inicio" type="date" label="Fecha inicio" required />
                <x-forms.input name="fecha_fin" type="date" label="Fecha fin" />
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar regla</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endsection
