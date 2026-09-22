@extends('layouts.admin')

@section('title', 'Personal de ventas')

@section('content')
    <x-ui.card title="Personal de ventas" subtitle="Personal que gestiona inventario y ventas de productos terminados.">
        <x-slot:actions>
            @can('create', \App\Infrastructure\Persistence\Models\PersonalVentasModel::class)
                <x-ui.button data-modal-open="modal-nuevo-personal" size="sm" as="button" type="button">Nuevo personal</x-ui.button>
            @endcan
        </x-slot:actions>

        @if (count($personal) === 0)
            <x-ui.empty-state title="No hay personal de ventas registrado" description="Registra al primer miembro del personal de ventas." />
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
                    @foreach ($personal as $miembro)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 text-text">{{ $miembro->dni() }}</td>
                            <td class="py-3 pr-4 font-medium text-text">{{ $miembro->nombreCompleto() }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $miembro->telefono() ?? '—' }}</td>
                            <td class="py-3 pr-4"><x-ui.badge :status="$miembro->estado()->value" /></td>
                            <td class="py-3 pl-4">
                                <x-tables.actions>
                                    <x-ui.action-button type="view" label="Ver personal de ventas" :subject="$miembro->nombreCompleto()" :href="route('admin.personal-ventas.show', $miembro->id())" />
                                    <x-ui.action-button type="edit" label="Editar personal de ventas" :subject="$miembro->nombreCompleto()" data-modal-open="modal-editar-personal-{{ $miembro->id() }}" />
                                    @if ($miembro->estado()->value === 'ACTIVO')
                                        <x-ui.action-button type="deactivate" label="Desactivar personal de ventas" :subject="$miembro->nombreCompleto()" data-modal-open="modal-desactivar-personal-{{ $miembro->id() }}" />
                                    @else
                                        <x-ui.action-button type="activate" label="Activar personal de ventas" :subject="$miembro->nombreCompleto()" data-modal-open="modal-reactivar-personal-{{ $miembro->id() }}" />
                                    @endif
                                </x-tables.actions>
                            </td>
                        </tr>

                        <x-ui.modal :id="'modal-editar-personal-' . $miembro->id()" title="Editar personal de ventas">
                            <form method="POST" action="{{ route('admin.personal-ventas.update', $miembro->id()) }}" class="space-y-4">
                                @csrf
                                @method('PATCH')
                                <x-forms.input name="nombres" label="Nombres" :value="$miembro->nombres()" required />
                                <x-forms.input name="apellidos" label="Apellidos" :value="$miembro->apellidos()" required />
                                <x-forms.input name="telefono" label="Teléfono" :value="$miembro->telefono()" />
                                <div class="flex justify-end gap-2 pt-2">
                                    <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                                    <x-ui.button type="submit">Guardar cambios</x-ui.button>
                                </div>
                            </form>
                        </x-ui.modal>

                        <x-ui.modal :id="'modal-desactivar-personal-' . $miembro->id()" title="Desactivar personal de ventas">
                            <p class="text-sm text-text">¿Desactivar a <strong>{{ $miembro->nombreCompleto() }}</strong>?</p>
                            <p class="mt-2 text-xs text-text-muted">No se elimina: el registro y su historial se conservan, pero dejará de estar disponible para operar. Podrás reactivarlo cuando quieras.</p>
                            <form method="POST" action="{{ route('admin.personal-ventas.desactivar', $miembro->id()) }}" class="mt-4 flex justify-end gap-2">
                                @csrf
                                @method('PATCH')
                                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                                <x-ui.button type="submit" variant="danger">Sí, desactivar</x-ui.button>
                            </form>
                        </x-ui.modal>

                        <x-ui.modal :id="'modal-reactivar-personal-' . $miembro->id()" title="Reactivar personal de ventas">
                            <p class="text-sm text-text">¿Reactivar a <strong>{{ $miembro->nombreCompleto() }}</strong>?</p>
<p class="mt-2 text-xs text-text-muted">Volverá a estar disponible para operar con normalidad.</p>
                            <form method="POST" action="{{ route('admin.personal-ventas.reactivar', $miembro->id()) }}" class="mt-4 flex justify-end gap-2">
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

    <x-ui.modal id="modal-nuevo-personal" title="Nuevo personal de ventas">
        <form method="POST" action="{{ route('admin.personal-ventas.store') }}" class="space-y-4">
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
                <x-ui.button type="submit">Guardar personal</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endsection
