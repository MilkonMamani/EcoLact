@extends('layouts.admin')

@section('title', $zona->nombre())

@section('content')
    <x-ui.button :href="route('admin.zonas.index')" variant="ghost" size="sm" as="a">&larr; Zonas</x-ui.button>

    <x-ui.card :title="$zona->nombre()" subtitle="Información de la zona">
        <x-slot:actions>
            <x-ui.badge :status="$zona->estado()" />
            <x-ui.action-button type="edit" :text="true" label="Editar" data-modal-open="modal-editar-zona" />
        </x-slot:actions>

        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Nombre</dt>
                <dd class="mt-1 text-sm text-text">{{ $zona->nombre() }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Estado</dt>
                <dd class="mt-1 text-sm text-text">{{ $zona->estaActiva() ? 'Activa' : 'Inactiva' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs uppercase tracking-wide text-text-muted">Descripción</dt>
                <dd class="mt-1 text-sm text-text">{{ $zona->descripcion() ?? 'Sin descripción registrada.' }}</dd>
            </div>
        </dl>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <x-ui.stat-card label="Proveedores asignados" :value="count($proveedores)" />
            <x-ui.stat-card label="Acopiadores asignados" :value="count($acopiadores)" />
        </div>
    </x-ui.card>

    <x-ui.card title="Rutas de esta zona" subtitle="Cada ruta agrupa proveedores y su orden de visita para el acopio." class="mt-4">
        <x-slot:actions>
            <x-ui.button data-modal-open="modal-nueva-ruta" variant="primary" size="sm" as="button" type="button">+ Nueva ruta</x-ui.button>
        </x-slot:actions>

        @if (count($rutas) === 0)
            <x-ui.empty-state title="Sin rutas" description="Crea una ruta para empezar a asignar proveedores y su orden de visita." :icon="false" />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Ruta</th>
                        <th class="py-2 pr-4">Descripción</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pl-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rutas as $ruta)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-2 pr-4 font-medium text-text">{{ $ruta->nombre() }}</td>
                            <td class="py-2 pr-4 text-text-muted">{{ $ruta->descripcion() ?? '—' }}</td>
                            <td class="py-2 pr-4"><x-ui.badge :status="$ruta->estado()" /></td>
                            <td class="py-2 pl-4 text-right">
                                <x-tables.actions>
                                    <x-ui.action-button type="manage" label="Gestionar ruta" :subject="$ruta->nombre()" :href="route('admin.rutas.show', $ruta->id())" />
                                </x-tables.actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>

    <x-ui.modal id="modal-nueva-ruta" title="Nueva ruta">
        <form method="POST" action="{{ route('admin.zonas.rutas.store', $zona->id()) }}" class="space-y-4">
            @csrf
            <x-forms.input name="nombre" label="Nombre de la ruta" required autofocus />
            <x-forms.textarea name="descripcion" label="Descripción" :rows="3" />
            <x-forms.select name="estado" label="Estado" required :options="['ACTIVO' => 'Activa', 'INACTIVO' => 'Inactiva']" value="ACTIVO" />
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit" variant="primary">Crear ruta</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.card title="Proveedores de esta zona" class="mt-4">
        @if (count($proveedores) === 0)
            <x-ui.empty-state title="Sin proveedores asignados" :icon="false" />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Proveedor</th>
                        <th class="py-2 pr-4">DNI</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pl-4 text-right">Ver</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($proveedores as $proveedor)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-2 pr-4 text-text">{{ $proveedor->nombreCompleto() }}</td>
                            <td class="py-2 pr-4 text-text-muted">{{ $proveedor->dni() }}</td>
                            <td class="py-2 pr-4"><x-ui.badge :status="$proveedor->estado()->value" /></td>
                            <td class="py-2 pl-4 text-right"><a class="text-primary hover:underline" href="{{ route('admin.proveedores.show', $proveedor->id()) }}">Ver →</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>

    <x-ui.card title="Acopiadores de esta zona" class="mt-4">
        @if (count($acopiadores) === 0)
            <x-ui.empty-state title="Sin acopiadores asignados" :icon="false" />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Acopiador</th>
                        <th class="py-2 pr-4">DNI</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pl-4 text-right">Ver</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($acopiadores as $acopiador)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-2 pr-4 text-text">{{ $acopiador->nombreCompleto() }}</td>
                            <td class="py-2 pr-4 text-text-muted">{{ $acopiador->dni() }}</td>
                            <td class="py-2 pr-4"><x-ui.badge :status="$acopiador->estado()->value" /></td>
                            <td class="py-2 pl-4 text-right"><a class="text-primary hover:underline" href="{{ route('admin.acopiadores.show', $acopiador->id()) }}">Ver →</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>

    <x-ui.modal id="modal-editar-zona" title="Editar zona">
        <form method="POST" action="{{ route('admin.zonas.update', $zona->id()) }}" class="space-y-4">
            @csrf
            @method('PATCH')
            <x-forms.input name="nombre" label="Nombre" :value="$zona->nombre()" required autofocus />
            <x-forms.textarea name="descripcion" label="Descripción" :value="$zona->descripcion()" :rows="3" />
            <x-forms.select name="estado" label="Estado" required :options="['ACTIVO' => 'Activa', 'INACTIVO' => 'Inactiva']" :value="$zona->estado()" />
            @if ($zona->estaActiva() && (count($proveedores) > 0 || count($acopiadores) > 0))
                <p class="rounded-lg bg-warning/10 p-3 text-xs text-warning">⚠ Esta zona tiene {{ count($proveedores) }} proveedor(es) y {{ count($acopiadores) }} acopiador(es) asignados. Antes de desactivarla, reasígnalos a otra zona desde su propio perfil.</p>
            @endif
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit" variant="primary">Guardar cambios</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endsection
