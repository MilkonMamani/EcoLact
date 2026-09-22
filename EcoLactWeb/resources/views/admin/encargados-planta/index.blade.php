@extends('layouts.admin')

@section('title', 'Encargados de planta')

@section('content')
    <x-ui.card title="Encargados de planta" subtitle="Personal que gestiona producción e insumos de planta.">
        <x-slot:actions>
            @can('create', \App\Infrastructure\Persistence\Models\EncargadoPlantaModel::class)
                <x-ui.button data-modal-open="modal-nuevo-encargado" size="sm" as="button" type="button">Nuevo encargado</x-ui.button>
            @endcan
        </x-slot:actions>

        @if (count($encargados) === 0)
            <x-ui.empty-state title="No hay encargados de planta registrados" description="Registra al primer encargado de planta." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">DNI</th>
                        <th class="py-2 pr-4">Nombre</th>
                        <th class="py-2 pr-4">Teléfono</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pl-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($encargados as $encargado)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 text-text">{{ $encargado->dni() }}</td>
                            <td class="py-3 pr-4 font-medium text-text">{{ $encargado->nombreCompleto() }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $encargado->telefono() ?? '—' }}</td>
                            <td class="py-3 pr-4"><x-ui.badge :status="$encargado->estado()->value" /></td>
                            <td class="py-3 pl-4">
                                <x-tables.actions>
                                    <x-ui.action-button type="view" label="Ver encargado de planta" :subject="$encargado->nombreCompleto()" :href="route('admin.encargados-planta.show', $encargado->id())" />
                                    <x-ui.action-button type="edit" label="Editar encargado de planta" :subject="$encargado->nombreCompleto()" data-modal-open="modal-editar-encargado-{{ $encargado->id() }}" />
                                    @if ($encargado->estado()->value === 'ACTIVO')
                                        <x-ui.action-button type="deactivate" label="Desactivar encargado de planta" :subject="$encargado->nombreCompleto()" data-modal-open="modal-desactivar-encargado-{{ $encargado->id() }}" />
                                    @else
                                        <x-ui.action-button type="activate" label="Activar encargado de planta" :subject="$encargado->nombreCompleto()" data-modal-open="modal-reactivar-encargado-{{ $encargado->id() }}" />
                                    @endif
                                </x-tables.actions>
                            </td>
                        </tr>

                        <x-ui.modal :id="'modal-editar-encargado-' . $encargado->id()" title="Editar encargado de planta">
                            <form method="POST" action="{{ route('admin.encargados-planta.update', $encargado->id()) }}" class="space-y-4">
                                @csrf
                                @method('PATCH')
                                <x-forms.input name="nombres" label="Nombres" :value="$encargado->nombres()" required />
                                <x-forms.input name="apellidos" label="Apellidos" :value="$encargado->apellidos()" required />
                                <x-forms.input name="telefono" label="Teléfono" :value="$encargado->telefono()" />
                                <div class="flex justify-end gap-2 pt-2">
                                    <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                                    <x-ui.button type="submit">Guardar cambios</x-ui.button>
                                </div>
                            </form>
                        </x-ui.modal>

                        <x-ui.modal :id="'modal-desactivar-encargado-' . $encargado->id()" title="Desactivar encargado de planta">
                            <p class="text-sm text-text">¿Desactivar a <strong>{{ $encargado->nombreCompleto() }}</strong>?</p>
                            <p class="mt-2 text-xs text-text-muted">No se elimina: el registro y su historial se conservan, pero dejará de estar disponible para operar. Podrás reactivarlo cuando quieras.</p>
                            <form method="POST" action="{{ route('admin.encargados-planta.desactivar', $encargado->id()) }}" class="mt-4 flex justify-end gap-2">
                                @csrf
                                @method('PATCH')
                                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                                <x-ui.button type="submit" variant="danger">Sí, desactivar</x-ui.button>
                            </form>
                        </x-ui.modal>

                        <x-ui.modal :id="'modal-reactivar-encargado-' . $encargado->id()" title="Reactivar encargado de planta">
                            <p class="text-sm text-text">¿Reactivar a <strong>{{ $encargado->nombreCompleto() }}</strong>?</p>
<p class="mt-2 text-xs text-text-muted">Volverá a estar disponible para operar con normalidad.</p>
                            <form method="POST" action="{{ route('admin.encargados-planta.reactivar', $encargado->id()) }}" class="mt-4 flex justify-end gap-2">
                                @csrf
                                @method('PATCH')
                                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                                <x-ui.button type="submit" variant="secondary">Reactivar</x-ui.button>
                            </form>
                        </x-ui.modal>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>

    <x-ui.modal id="modal-nuevo-encargado" title="Nuevo encargado de planta">
        <form method="POST" action="{{ route('admin.encargados-planta.store') }}" class="space-y-4">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <x-forms.input name="dni" label="DNI" required />
                <x-forms.input name="nombres" label="Nombres" required />
                <x-forms.input name="apellidos" label="Apellidos" required />
                <x-forms.input name="telefono" label="Teléfono" />
                <x-forms.input name="fecha_ingreso" type="date" label="Fecha de ingreso" />
                <x-forms.input name="username" label="Usuario" required />
                <x-forms.input name="password" type="password" label="Contraseña" required />
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar encargado</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endsection
