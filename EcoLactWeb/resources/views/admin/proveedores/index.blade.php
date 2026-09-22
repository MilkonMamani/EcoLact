@extends('layouts.admin')

@section('title', 'Proveedores')

@section('content')
    @if (isset($datos))
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.stat-card label="Proveedores activos" :value="$datos->kpis['activos']" accent="success" />
            <x-ui.stat-card label="Proveedores inactivos" :value="$datos->kpis['inactivos']" />
            <x-ui.stat-card label="Zonas con proveedores" :value="$datos->kpis['zonasConProveedores']" />
            <x-ui.stat-card label="Requieren seguimiento" :value="$datos->kpis['requierenSeguimiento']" accent="warning" />
        </div>

        <x-ui.card title="Proveedores" subtitle="Gestión y seguimiento de los proveedores de EcoLact.">
            <x-slot:actions>
                @can('create', \App\Infrastructure\Persistence\Models\ProveedorModel::class)
                    <x-ui.button data-modal-open="modal-nuevo-proveedor" size="sm" as="button" type="button">+ Nuevo proveedor</x-ui.button>
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
                    <div class="w-48">
                        <x-forms.select name="seguimiento" label="Seguimiento" :options="['con_seguimiento' => 'Requiere seguimiento', 'sin_seguimiento' => 'Sin seguimiento']" :value="request('seguimiento')" placeholder="Todos" />
                    </div>
                    <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
                    @if (request()->anyFilled(['q', 'estado', 'zona_id', 'seguimiento']))
                        <x-ui.button :href="route('admin.proveedores.index')" variant="ghost" size="sm" as="a">Limpiar</x-ui.button>
                    @endif
                </div>
            </form>

            @if (count($datos->filas) === 0)
                <x-ui.empty-state title="No hay proveedores registrados" description="Registra al primer proveedor para comenzar a acopiar su producción." />
            @else
                <x-tables.table>
                    <thead>
                        <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                            <th class="py-2 pr-4">Proveedor</th>
                            <th class="py-2 pr-4">DNI</th>
                            <th class="py-2 pr-4">Zona actual</th>
                            <th class="py-2 pr-4">Teléfono</th>
                            <th class="py-2 pr-4">Advertencias</th>
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
                                <td class="py-3 pr-4 text-text">{{ $fila['advertencias'] }}</td>
                                <td class="py-3 pr-4"><x-ui.badge :status="$fila['estado']" /></td>
                                <td class="py-3 pl-4">
                                    <x-tables.actions>
                                        <x-ui.action-button type="view" label="Ver perfil" :subject="$fila['nombre']" :href="route('admin.proveedores.show', $fila['id'])" />
                                        <x-ui.action-button type="edit" label="Editar proveedor" :subject="$fila['nombre']" data-modal-open="modal-editar-proveedor-{{ $fila['id'] }}" />
                                        @if ($fila['estado'] === 'ACTIVO')
                                            <x-ui.action-button type="deactivate" label="Desactivar proveedor" :subject="$fila['nombre']" data-modal-open="modal-desactivar-proveedor-{{ $fila['id'] }}" />
                                        @else
                                            <x-ui.action-button type="activate" label="Activar proveedor" :subject="$fila['nombre']" data-modal-open="modal-reactivar-proveedor-{{ $fila['id'] }}" />
                                        @endif
                                    </x-tables.actions>
                                </td>
                            </tr>

                            <x-ui.modal :id="'modal-editar-proveedor-' . $fila['id']" title="Editar proveedor">
                                <form method="POST" action="{{ route('admin.proveedores.update', $fila['id']) }}" class="space-y-4">
                                    @csrf
                                    @method('PUT')
                                    <x-forms.input name="dni" label="DNI" :value="$fila['dni']" required />
                                    <x-forms.input name="nombres" label="Nombres" required />
                                    <x-forms.input name="apellidos" label="Apellidos" required />
                                    <x-forms.input name="telefono" label="Teléfono" :value="$fila['telefono']" />

                                    <div class="flex justify-end gap-2 pt-2">
                                        <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                                        <x-ui.button type="submit">Guardar cambios</x-ui.button>
                                    </div>
                                </form>
                            </x-ui.modal>

                            <x-ui.modal :id="'modal-desactivar-proveedor-' . $fila['id']" title="Desactivar proveedor">
                                <p class="text-sm text-text">¿Desactivar a <strong>{{ $fila['nombre'] }}</strong>?</p>
                                <p class="mt-2 text-xs text-text-muted">No se elimina: el registro y su historial se conservan, pero dejará de estar disponible para operar. Podrás reactivarlo cuando quieras.</p>
                                <form method="POST" action="{{ route('admin.proveedores.desactivar', $fila['id']) }}" class="mt-4 flex justify-end gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                                    <x-ui.button type="submit" variant="danger">Sí, desactivar</x-ui.button>
                                </form>
                            </x-ui.modal>

                            <x-ui.modal :id="'modal-reactivar-proveedor-' . $fila['id']" title="Reactivar proveedor">
                                <p class="text-sm text-text">¿Reactivar a <strong>{{ $fila['nombre'] }}</strong>?</p>
<p class="mt-2 text-xs text-text-muted">Volverá a estar disponible para operar con normalidad.</p>
                                <form method="POST" action="{{ route('admin.proveedores.reactivar', $fila['id']) }}" class="mt-4 flex justify-end gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                                    <x-ui.button type="submit" variant="secondary">Sí, reactivar</x-ui.button>
                                </form>
                            </x-ui.modal>
                        @endforeach
                    </tbody>
                </x-tables.table>
            @endif
        </x-ui.card>
    @else
        {{-- Rama heredada para ACOPIADOR (index() con recorte por zona propia, sin KPIs/filtros). --}}
        <x-ui.card title="Proveedores" subtitle="Productores registrados que entregan leche a EcoLact.">
            @if (count($proveedores) === 0)
                <x-ui.empty-state title="No hay proveedores registrados" description="Registra al primer proveedor para comenzar a acopiar su producción." />
            @else
                <x-tables.table>
                    <thead>
                        <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                            <th class="py-2 pr-4">DNI</th>
                            <th class="py-2 pr-4">Proveedor</th>
                            <th class="py-2 pr-4">Zona</th>
                            <th class="py-2 pr-4">Teléfono</th>
                            <th class="py-2 pr-4">Estado</th>
                            <th class="py-2 pl-4 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($proveedores as $proveedor)
                            <tr class="border-b border-text-muted/5 last:border-0">
                                <td class="py-3 pr-4 text-text">{{ $proveedor->dni() }}</td>
                                <td class="py-3 pr-4 font-medium text-text">{{ $proveedor->nombreCompleto() }}</td>
                                <td class="py-3 pr-4 text-text-muted">{{ $proveedor->zonaId() !== null ? 'Zona #'.$proveedor->zonaId() : 'Sin asignar' }}</td>
                                <td class="py-3 pr-4 text-text-muted">{{ $proveedor->telefono() ?? '—' }}</td>
                                <td class="py-3 pr-4"><x-ui.badge :status="$proveedor->estado()->value" /></td>
                                <td class="py-3 pl-4">
                                    <x-tables.actions>
                                        <x-ui.action-button type="view" label="Ver proveedor" :subject="$proveedor->nombreCompleto()" :href="route('admin.proveedores.show', $proveedor->id())" />
                                    </x-tables.actions>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-tables.table>
            @endif
        </x-ui.card>
    @endif

    <x-ui.modal id="modal-nuevo-proveedor" title="Nuevo proveedor">
        <form method="POST" action="{{ route('admin.proveedores.store') }}" class="space-y-4">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <x-forms.input name="dni" label="DNI" required />
                <x-forms.input name="nombres" label="Nombres" required />
                <x-forms.input name="apellidos" label="Apellidos" required />
                <x-forms.input name="telefono" label="Teléfono" />
                <x-forms.input name="username" label="Usuario" required />
                <x-forms.input name="password" type="password" label="Contraseña" required />
            </div>

            <p class="text-xs text-text-muted">El proveedor se crea sin zona ni ruta: asígnalo a una ruta desde el módulo <a href="{{ route('admin.zonas.index') }}" class="underline">Zonas</a> cuando corresponda.</p>

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar proveedor</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endsection
