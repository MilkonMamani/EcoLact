@php
    /** @var list<\App\Application\Zona\ProveedorAsignadoFila> $proveedores */
    $esSinAsignar = $esSinAsignar ?? false;
    $filas = array_values($proveedores);
    $celda = 'block px-0 py-1 before:mr-2 before:text-xs before:font-medium before:uppercase before:tracking-wide before:text-text-muted before:content-[attr(data-label)] md:table-cell md:px-3 md:py-3 md:before:content-none';
@endphp

@if (count($filas) === 0)
    @if ($esSinAsignar)
        <x-ui.empty-state title="Todos los proveedores tienen zona" description="No hay proveedores activos pendientes de asignar a una zona." :icon="false" />
    @else
        <x-ui.empty-state title="Esta zona aún no tiene proveedores" description="Usa el buscador de arriba para encontrar un proveedor y asignarlo a esta zona." :icon="false" />
    @endif
@else
    <table class="w-full border-collapse text-left text-sm" data-proveedores-tabla>
        <thead class="hidden md:table-header-group">
            <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                <th scope="col" class="px-3 py-2 font-medium">Proveedor</th>
                <th scope="col" class="px-3 py-2 font-medium">DNI</th>
                <th scope="col" class="px-3 py-2 font-medium">Zona</th>
                <th scope="col" class="px-3 py-2 font-medium">Ubicación</th>
                <th scope="col" class="px-3 py-2 text-right font-medium">Acciones</th>
            </tr>
        </thead>
        <tbody class="block md:table-row-group">
            @foreach ($filas as $fila)
                <tr class="mb-3 block rounded-lg border border-text-muted/10 p-3 md:mb-0 md:table-row md:rounded-none md:border-0 md:border-b md:border-text-muted/10 md:p-0 md:last:border-b-0">
                    <td data-label="Proveedor" class="{{ $celda }} font-medium text-text">{{ $fila->nombre }}</td>
                    <td data-label="DNI" class="{{ $celda }} text-text-muted">{{ $fila->dni }}</td>
                    <td data-label="Zona" class="{{ $celda }} text-text">{{ $fila->zonaNombre ?? 'Sin zona' }}</td>

                    <td data-label="Ubicación" class="{{ $celda }}">
                        @if ($fila->tieneUbicacion())
                            <a
                                href="https://www.google.com/maps/search/?api=1&query={{ $fila->latitud }},{{ $fila->longitud }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                title="Enfocar en el mapa de la zona"
                                data-ver-ubicacion="{{ $fila->proveedorId }}"
                                class="inline-flex items-center gap-1.5 font-medium text-primary hover:underline"
                            >
                                <x-ui.icon name="map-pin" class="h-4 w-4" />
                                Ver ubicación
                            </a>
                            @if ($fila->referenciaUbicacion)
                                <p class="mt-0.5 max-w-[14rem] truncate text-xs text-text-muted" title="{{ $fila->referenciaUbicacion }}">{{ $fila->referenciaUbicacion }}</p>
                            @endif
                        @else
                            <span class="inline-flex items-center rounded-full bg-warning/10 px-2.5 py-0.5 text-xs font-medium text-warning">Sin ubicación</span>
                        @endif
                    </td>

                    <td data-label="Acciones" class="{{ $celda }} md:text-right">
                        <div class="inline-flex items-center gap-2">
                            <x-ui.action-button
                                type="edit"
                                :label="$fila->estaAsignado() ? 'Editar asignación de' : 'Asignar a zona a'"
                                :subject="$fila->nombre"
                                data-asignar-abrir="{{ json_encode($fila->paraModal()) }}"
                            />

                            @if ($fila->tieneUbicacion())
                                <x-ui.action-button
                                    type="location"
                                    label="Ver ubicación de"
                                    :subject="$fila->nombre"
                                    :href="'https://www.google.com/maps/search/?api=1&query='.$fila->latitud.','.$fila->longitud"
                                    target="_blank"
                                />
                            @endif

                            @if ($fila->estaAsignado())
                                <form method="POST" action="{{ route('admin.rutas.detalles.destroy', [$fila->rutaId, $fila->detalleId]) }}" data-confirm="¿Quitar a {{ $fila->nombre }} de {{ $fila->zonaNombre }}?&#10;&#10;El proveedor seguirá existiendo; únicamente se eliminará su asignación actual y su ubicación.">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.action-button type="unlink" :submit="true" label="Quitar de la zona a" :subject="$fila->nombre" />
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
