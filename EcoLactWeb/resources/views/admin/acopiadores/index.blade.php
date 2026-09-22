@extends('layouts.admin')

@section('title', 'Acopiadores')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card label="Acopiadores activos" :value="$datos->kpis['activos']" accent="success" />
        <x-ui.stat-card label="Acopiadores inactivos" :value="$datos->kpis['inactivos']" />
        <x-ui.stat-card label="Zonas asignadas" :value="$datos->kpis['zonasAsignadas']" />
        <x-ui.stat-card label="Con entregas" :value="$datos->kpis['conEntregas']" hint="Al menos una entrega registrada" />
    </div>

    <x-ui.card title="Acopiadores" subtitle="Gestión de los responsables del acopio de leche.">
        <x-slot:actions>
            @can('create', \App\Infrastructure\Persistence\Models\AcopiadorModel::class)
                <x-ui.button data-modal-open="modal-nuevo-acopiador" size="sm" as="button" type="button">+ Nuevo acopiador</x-ui.button>
            @endcan
        </x-slot:actions>

        <form method="GET" class="mb-4 space-y-3">
            <x-forms.input name="q" label="Buscar por nombre, apellido o DNI" value="{{ request('q') }}" />

            <div class="flex flex-wrap items-end gap-3">
                <div class="w-40">
                    <x-forms.select name="estado" label="Estado" :options="['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo']" :value="request('estado')" placeholder="Todos" />
                </div>
                <div class="w-44">
                    <x-forms.select name="zona_id" label="Zona" :options="collect($datos->zonas)->pluck('nombre', 'id')->all()" :value="request('zona_id')" placeholder="Todas" />
                </div>
                <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
                @if (request()->anyFilled(['q', 'estado', 'zona_id']))
                    <x-ui.button :href="route('admin.acopiadores.index')" variant="ghost" size="sm" as="a">Limpiar</x-ui.button>
                @endif
            </div>
        </form>

        @if (count($datos->filas) === 0)
            <x-ui.empty-state title="No hay acopiadores registrados" description="Registra al primer acopiador para asignarlo a una zona." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Acopiador</th>
                        <th class="py-2 pr-4">DNI</th>
                        <th class="py-2 pr-4">Zona actual</th>
                        <th class="py-2 pr-4">Teléfono</th>
                        <th class="py-2 pr-4">Entregas</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pl-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($datos->filas as $fila)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 font-medium text-text">{{ $fila['nombre'] }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $fila['dni'] }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $fila['zonaNombre'] }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $fila['telefono'] ?? '—' }}</td>
                            <td class="py-3 pr-4 text-text">{{ $fila['entregas'] }}</td>
                            <td class="py-3 pr-4"><x-ui.badge :status="$fila['estado']" /></td>
                            <td class="py-3 pl-4">
                                <x-tables.actions>
                                    <x-ui.action-button type="view" label="Ver perfil" :subject="$fila['nombre']" :href="route('admin.acopiadores.show', $fila['id'])" />
                                    <x-ui.action-button type="edit" label="Editar acopiador" :subject="$fila['nombre']" data-modal-open="modal-editar-acopiador-{{ $fila['id'] }}" />
                                    @if ($fila['estado'] === 'ACTIVO')
                                        <x-ui.action-button type="deactivate" label="Desactivar acopiador" :subject="$fila['nombre']" data-modal-open="modal-desactivar-acopiador-{{ $fila['id'] }}" />
                                    @else
                                        <x-ui.action-button type="activate" label="Activar acopiador" :subject="$fila['nombre']" data-modal-open="modal-reactivar-acopiador-{{ $fila['id'] }}" />
                                    @endif
                                </x-tables.actions>
                            </td>
                        </tr>

                        <x-ui.modal :id="'modal-editar-acopiador-' . $fila['id']" title="Editar acopiador">
                            <form method="POST" action="{{ route('admin.acopiadores.update', $fila['id']) }}" class="space-y-4">
                                @csrf
                                @method('PATCH')
                                <x-forms.input name="nombres" label="Nombres" required />
                                <x-forms.input name="apellidos" label="Apellidos" required />
                                <x-forms.input name="telefono" label="Teléfono" :value="$fila['telefono']" />
                                <x-forms.select name="zona_id" label="Zona" :options="collect($datos->zonas)->pluck('nombre', 'id')->all()" />
                                <x-forms.input name="motivo_cambio_zona" label="Motivo del cambio de zona (si aplica)" />
                                <div class="flex justify-end gap-2 pt-2">
                                    <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                                    <x-ui.button type="submit">Guardar cambios</x-ui.button>
                                </div>
                            </form>
                        </x-ui.modal>

                        <x-ui.modal :id="'modal-desactivar-acopiador-' . $fila['id']" title="Desactivar acopiador">
                            <p class="text-sm text-text">¿Desactivar a <strong>{{ $fila['nombre'] }}</strong>?</p>
                            <p class="mt-2 text-xs text-text-muted">No se elimina: el registro y su historial se conservan, pero dejará de estar disponible para operar. Podrás reactivarlo cuando quieras.</p>
                            <p class="mt-1 text-sm text-text-muted">No podrá registrar nuevas entregas. Su información histórica se conservará.</p>
                            <form method="POST" action="{{ route('admin.acopiadores.desactivar', $fila['id']) }}" class="mt-4 flex justify-end gap-2">
                                @csrf
                                @method('PATCH')
                                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                                <x-ui.button type="submit" variant="danger">Sí, desactivar</x-ui.button>
                            </form>
                        </x-ui.modal>

                        <x-ui.modal :id="'modal-reactivar-acopiador-' . $fila['id']" title="Reactivar acopiador">
                            <p class="text-sm text-text">Selecciona la zona a la que se reincorpora <strong>{{ $fila['nombre'] }}</strong>.</p>
                            <form method="POST" action="{{ route('admin.acopiadores.reactivar', $fila['id']) }}" class="mt-4 space-y-4">
                                @csrf
                                @method('PATCH')
                                <x-forms.select name="zona_id" label="Zona" required :options="collect($datos->zonas)->pluck('nombre', 'id')->all()" />
                                <div class="flex justify-end gap-2">
                                    <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                                    <x-ui.button type="submit" variant="secondary">Reactivar</x-ui.button>
                                </div>
                            </form>
                        </x-ui.modal>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>

    <x-ui.modal id="modal-nuevo-acopiador" title="Nuevo acopiador">
        <form method="POST" action="{{ route('admin.acopiadores.store') }}" class="space-y-4">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <x-forms.input name="dni" label="DNI" required />
                <x-forms.select name="zona_id" label="Zona" required :options="collect($datos->zonas)->pluck('nombre', 'id')->all()" />
                <x-forms.input name="nombres" label="Nombres" required />
                <x-forms.input name="apellidos" label="Apellidos" required />
                <x-forms.input name="telefono" label="Teléfono" />
                <x-forms.input name="fecha_ingreso" type="date" label="Fecha de ingreso" />
                <x-forms.input name="username" label="Usuario" required />
                <x-forms.input name="password" type="password" label="Contraseña" required />
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar acopiador</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endsection
