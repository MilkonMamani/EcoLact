@extends('layouts.admin')

@section('title', 'Analistas de calidad')

@section('content')
    <x-ui.card title="Analistas de calidad" subtitle="Personal que realiza el control de calidad técnico, en cualquier zona.">
        <x-slot:actions>
            @can('create', \App\Infrastructure\Persistence\Models\AnalistaCalidadModel::class)
                <x-ui.button data-modal-open="modal-nuevo-analista" size="sm" as="button" type="button">Nuevo analista</x-ui.button>
            @endcan
        </x-slot:actions>

        @if (count($analistas) === 0)
            <x-ui.empty-state title="No hay analistas de calidad registrados" description="Registra al primer analista de calidad." />
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
                    @foreach ($analistas as $analista)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 text-text">{{ $analista->dni() }}</td>
                            <td class="py-3 pr-4 font-medium text-text">{{ $analista->nombreCompleto() }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $analista->telefono() ?? '—' }}</td>
                            <td class="py-3 pr-4"><x-ui.badge :status="$analista->estado()->value" /></td>
                            <td class="py-3 pl-4">
                                <x-tables.actions>
                                    <x-ui.action-button type="view" label="Ver analista de calidad" :subject="$analista->nombreCompleto()" :href="route('admin.analistas-calidad.show', $analista->id())" />
                                    <x-ui.action-button type="edit" label="Editar analista de calidad" :subject="$analista->nombreCompleto()" data-modal-open="modal-editar-analista-{{ $analista->id() }}" />
                                    @if ($analista->estado()->value === 'ACTIVO')
                                        <x-ui.action-button type="deactivate" label="Desactivar analista de calidad" :subject="$analista->nombreCompleto()" data-modal-open="modal-desactivar-analista-{{ $analista->id() }}" />
                                    @else
                                        <x-ui.action-button type="activate" label="Activar analista de calidad" :subject="$analista->nombreCompleto()" data-modal-open="modal-reactivar-analista-{{ $analista->id() }}" />
                                    @endif
                                </x-tables.actions>
                            </td>
                        </tr>

                        <x-ui.modal :id="'modal-editar-analista-' . $analista->id()" title="Editar analista de calidad">
                            <form method="POST" action="{{ route('admin.analistas-calidad.update', $analista->id()) }}" class="space-y-4">
                                @csrf
                                @method('PATCH')
                                <x-forms.input name="nombres" label="Nombres" :value="$analista->nombres()" required />
                                <x-forms.input name="apellidos" label="Apellidos" :value="$analista->apellidos()" required />
                                <x-forms.input name="telefono" label="Teléfono" :value="$analista->telefono()" />
                                <div class="flex justify-end gap-2 pt-2">
                                    <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                                    <x-ui.button type="submit">Guardar cambios</x-ui.button>
                                </div>
                            </form>
                        </x-ui.modal>

                        <x-ui.modal :id="'modal-desactivar-analista-' . $analista->id()" title="Desactivar analista de calidad">
                            <p class="text-sm text-text">¿Desactivar a <strong>{{ $analista->nombreCompleto() }}</strong>?</p>
                            <p class="mt-2 text-xs text-text-muted">No se elimina: el registro y su historial se conservan, pero dejará de estar disponible para operar. Podrás reactivarlo cuando quieras.</p>
                            <form method="POST" action="{{ route('admin.analistas-calidad.desactivar', $analista->id()) }}" class="mt-4 flex justify-end gap-2">
                                @csrf
                                @method('PATCH')
                                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                                <x-ui.button type="submit" variant="danger">Sí, desactivar</x-ui.button>
                            </form>
                        </x-ui.modal>

                        <x-ui.modal :id="'modal-reactivar-analista-' . $analista->id()" title="Reactivar analista de calidad">
                            <p class="text-sm text-text">¿Reactivar a <strong>{{ $analista->nombreCompleto() }}</strong>?</p>
<p class="mt-2 text-xs text-text-muted">Volverá a estar disponible para operar con normalidad.</p>
                            <form method="POST" action="{{ route('admin.analistas-calidad.reactivar', $analista->id()) }}" class="mt-4 flex justify-end gap-2">
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

    <x-ui.modal id="modal-nuevo-analista" title="Nuevo analista de calidad">
        <form method="POST" action="{{ route('admin.analistas-calidad.store') }}" class="space-y-4">
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
                <x-ui.button type="submit">Guardar analista</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endsection
