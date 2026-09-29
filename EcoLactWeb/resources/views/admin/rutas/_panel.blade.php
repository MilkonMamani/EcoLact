@php
    $paradasParaMapa = collect($detalles)->map(fn ($d) => [
        'id' => $d->id,
        'nombre' => $d->proveedorNombre,
        'orden' => $d->ordenVisita,
        'lat' => $d->latitud,
        'lng' => $d->longitud,
        'referencia' => $d->referenciaUbicacion,
    ])->values();

    $opcionesAcopiador = collect($acopiadoresDeLaZona)->mapWithKeys(
        fn ($a) => [$a->id() => $a->nombreCompleto().' ('.$a->dni().')']
    )->all();
@endphp

<div data-ruta-detalle data-ruta-id="{{ $ruta->id() }}" data-panel-url="{{ route('admin.rutas.panel', $ruta->id()) }}">
    <x-ui.card :title="$ruta->nombre()" subtitle="Ruta de la zona {{ $zona->nombre }}">
        <x-slot:actions>
            <x-ui.badge :status="$ruta->estado()" />
            <x-ui.action-button type="edit" :text="true" label="Editar" data-modal-open="modal-editar-ruta-{{ $ruta->id() }}" />
        </x-slot:actions>

        <p class="text-sm text-text-muted">{{ $ruta->descripcion() ?? 'Sin descripción registrada.' }}</p>

        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <x-ui.stat-card label="Proveedores en la ruta" :value="count($detalles)" />
        </div>
    </x-ui.card>

    <x-ui.card title="Mapa de la ruta" subtitle="Vista previa de las ubicaciones registradas, en el orden de visita." class="mt-4">
        <div
            data-ruta-mapa
            data-paradas='@json($paradasParaMapa)'
            class="h-80 w-full rounded-lg bg-text-muted/5"
        ></div>
        <p class="mt-2 text-xs text-text-muted">La línea entre paradas, si se muestra, es solo una conexión visual entre puntos: no representa una ruta real por carreteras.</p>
    </x-ui.card>

    <x-ui.card title="Proveedores de la ruta" subtitle="Agrega proveedores existentes y define su orden de visita." class="mt-4">
        <div data-ruta-buscador data-ruta-buscador-url="{{ route('admin.rutas.proveedores-disponibles', $ruta->id()) }}" class="relative mb-4">
            <x-forms.input name="buscar_proveedor" data-ruta-buscador-input label="Buscar proveedor por nombre o DNI" placeholder="Ej. Ana Quispe o 12345678" autocomplete="off" />
            <div data-ruta-buscador-resultados class="absolute z-10 hidden max-h-64 w-full overflow-y-auto rounded-lg border border-text-muted/10 bg-surface shadow-lg"></div>
        </div>

        <form data-ruta-agregar-form method="POST" action="{{ route('admin.rutas.detalles.store', $ruta->id()) }}" class="mb-4 flex flex-wrap items-end gap-3 rounded-lg border border-text-muted/10 p-3">
            @csrf
            <input type="hidden" name="proveedor_id" data-ruta-agregar-proveedor-id>
            <div class="flex-1">
                <p class="text-xs uppercase tracking-wide text-text-muted">Proveedor seleccionado</p>
                <p data-ruta-agregar-proveedor-nombre class="hidden text-sm font-medium text-text"></p>
                <p class="text-sm text-text-muted" data-ruta-agregar-proveedor-placeholder>Busca y selecciona un proveedor arriba.</p>
            </div>
            <div class="w-32">
                <x-forms.input name="orden_visita" type="number" min="1" label="Orden (opcional)" />
            </div>
            <div class="w-56">
                <x-forms.input name="motivo" label="Motivo del traslado (si aplica)" />
            </div>
            <x-ui.button data-ruta-agregar-submit type="submit" variant="primary" size="sm" disabled>Agregar a la ruta</x-ui.button>
        </form>
        @error('proveedor_id')
            <p class="mb-4 text-sm text-danger">{{ $message }}</p>
        @enderror

        @if (count($detalles) === 0)
            <x-ui.empty-state title="Sin proveedores asignados" description="Busca proveedores existentes arriba para agregarlos a esta ruta." :icon="false" />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Orden</th>
                        <th class="py-2 pr-4">Proveedor</th>
                        <th class="py-2 pr-4">DNI</th>
                        <th class="py-2 pr-4">Ubicación</th>
                        <th class="py-2 pl-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody data-ruta-detalles-table data-reorder-url="{{ route('admin.rutas.detalles.reordenar', $ruta->id()) }}">
                    @foreach ($detalles as $fila)
                        <tr data-detalle-id="{{ $fila->id }}" class="border-b border-text-muted/5 last:border-0">
                            <td class="py-2 pr-4 text-text">
                                <div class="flex items-center gap-1">
                                    <button type="button" data-move-up aria-label="Mover hacia arriba" class="rounded p-1 text-text-muted hover:bg-text-muted/10 disabled:opacity-30" @if ($loop->first) disabled @endif>&uarr;</button>
                                    <button type="button" data-move-down aria-label="Mover hacia abajo" class="rounded p-1 text-text-muted hover:bg-text-muted/10 disabled:opacity-30" @if ($loop->last) disabled @endif>&darr;</button>
                                    <span class="ml-1 font-medium">{{ $fila->ordenVisita }}</span>
                                </div>
                            </td>
                            <td class="py-2 pr-4 text-text">{{ $fila->proveedorNombre }}</td>
                            <td class="py-2 pr-4 text-text-muted">{{ $fila->proveedorDni }}</td>
                            <td class="py-2 pr-4 text-text-muted">
                                @if ($fila->latitud !== null)
                                    {{ number_format($fila->latitud, 5) }}, {{ number_format($fila->longitud, 5) }}
                                @else
                                    <span class="text-warning">Sin ubicación</span>
                                @endif
                            </td>
                            <td class="py-2 pl-4 text-right">
                                <x-tables.actions>
                                    <form method="POST" action="{{ route('admin.rutas.detalles.destroy', [$ruta->id(), $fila->id]) }}" data-confirm="¿Quitar a {{ $fila->proveedorNombre }} de esta ruta?&#10;&#10;El proveedor seguirá existiendo; únicamente se eliminará su asignación actual y su ubicación en la ruta.">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.action-button type="unlink" :submit="true" label="Quitar de la ruta" :subject="$fila->proveedorNombre" />
                                    </form>
                                </x-tables.actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>

    <x-ui.card title="Recorridos" subtitle="Asigna un acopiador y abre el recorrido operativo del día." class="mt-4">
        <form method="POST" action="{{ route('admin.rutas.recorridos.store', $ruta->id()) }}" class="mb-4 flex flex-wrap items-end gap-3">
            @csrf
            <div class="w-64">
                <x-forms.select name="acopiador_id" label="Acopiador" required :options="$opcionesAcopiador" placeholder="Selecciona un acopiador" />
            </div>
            <div class="w-48">
                <x-forms.input name="fecha_operativa" type="date" label="Fecha operativa (opcional)" />
            </div>
            <x-ui.button type="submit" variant="primary" size="sm">Asignar y abrir recorrido</x-ui.button>
        </form>
        @error('acopiador_id')
            <p class="mb-4 text-sm text-danger">{{ $message }}</p>
        @enderror

        @if (count($recorridos) === 0)
            <x-ui.empty-state title="Sin recorridos" description="Todavía no se ha abierto ningún recorrido para esta ruta." :icon="false" />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Fecha</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pl-4 text-right">&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recorridos as $recorrido)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-2 pr-4 text-text">{{ $recorrido->fechaOperativa()->format('d/m/Y') }}</td>
                            <td class="py-2 pr-4"><x-ui.badge compact :status="$recorrido->estado()->value" /></td>
                            <td class="py-2 pl-4 text-right text-text-muted">#{{ $recorrido->id() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>

    <x-ui.modal id="modal-editar-ruta-{{ $ruta->id() }}" title="Editar ruta">
        <form method="POST" action="{{ route('admin.rutas.update', $ruta->id()) }}" class="space-y-4">
            @csrf
            @method('PATCH')
            <x-forms.input name="nombre" label="Nombre" :value="$ruta->nombre()" required autofocus />
            <x-forms.textarea name="descripcion" label="Descripción" :value="$ruta->descripcion()" :rows="3" />
            <x-forms.select name="estado" label="Estado" required :options="['ACTIVO' => 'Activa', 'INACTIVO' => 'Inactiva']" :value="$ruta->estado()" />
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit" variant="primary">Guardar cambios</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
