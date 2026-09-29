@extends('layouts.admin')

@section('title', 'Parámetros de calidad')

@section('content')
    <x-ui.card title="Parámetros de calidad" subtitle="Qué se mide en cada análisis de leche (densidad, grasa, proteína, etc.).">
        <x-slot:actions>
            @can('create', \App\Infrastructure\Persistence\Models\ParametroCalidadModel::class)
                <x-ui.button data-modal-open="modal-nuevo-parametro" size="sm" as="button" type="button">Nuevo parámetro</x-ui.button>
            @endcan
        </x-slot:actions>

        @if (count($parametros) === 0)
            <x-ui.empty-state title="No hay parámetros de calidad" description="Registra el primer parámetro para poder definir reglas y análisis." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Código</th>
                        <th class="py-2 pr-4">Nombre</th>
                        <th class="py-2 pr-4">Unidad</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pl-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($parametros as $parametro)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 font-mono text-xs text-text">{{ $parametro->codigo() }}</td>
                            <td class="py-3 pr-4 font-medium text-text">{{ $parametro->nombre() }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $parametro->unidad() ?? '—' }}</td>
                            <td class="py-3 pr-4"><x-ui.badge compact :status="$parametro->estado()" /></td>
                            <td class="py-3 pl-4">
                                <x-tables.actions>
                                    <x-ui.action-button type="view" label="Ver parámetro de calidad" :href="route('admin.parametros-calidad.show', $parametro->id())" />
                                    <x-ui.action-button type="edit" label="Editar parámetro de calidad" data-modal-open="modal-editar-parametro-{{ $parametro->id() }}" />
                                </x-tables.actions>
                            </td>
                        </tr>

                        <x-ui.modal :id="'modal-editar-parametro-' . $parametro->id()" title="Editar parámetro">
                            <form method="POST" action="{{ route('admin.parametros-calidad.update', $parametro->id()) }}" class="space-y-4">
                                @csrf
                                @method('PATCH')
                                <x-forms.input name="nombre" label="Nombre" :value="$parametro->nombre()" required />
                                <x-forms.input name="unidad" label="Unidad" :value="$parametro->unidad()" />
                                <x-forms.select name="estado" label="Estado" required :options="['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo']" :value="$parametro->estado()" />
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

    <x-ui.modal id="modal-nuevo-parametro" title="Nuevo parámetro de calidad">
        <form method="POST" action="{{ route('admin.parametros-calidad.store') }}" class="space-y-4">
            @csrf
            <x-forms.input name="codigo" label="Código (ej. DENSIDAD)" required />
            <x-forms.input name="nombre" label="Nombre" required />
            <x-forms.input name="unidad" label="Unidad" />
            <x-forms.select name="estado" label="Estado" required :options="['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo']" value="ACTIVO" />
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar parámetro</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endsection
