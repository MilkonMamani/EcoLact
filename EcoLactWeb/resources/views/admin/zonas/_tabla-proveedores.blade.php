{{--
    Proveedores de la pestaña Proveedores (o del panel "Proveedores sin zona"). El lápiz abre el
    modal de asignación ya cargado (zona, ruta, ubicación y referencia), el eslabón quita la
    asignación actual y el chevron lleva al perfil del proveedor: el perfil no se duplica aquí.

    @var list<array{fila: \App\Application\Zona\ProveedorAsignadoFila, telefono: ?string, ultimaEntrega: ?string, litrosSemana: float}> $proveedores
    @var bool $esSinAsignar
--}}
@if ($proveedores === [])
    <p class="fichas-panel-sin-datos">{{ $esSinAsignar ? 'Todos los proveedores activos ya tienen zona.' : 'Esta zona aún no tiene proveedores. Usa "Asignar proveedor" para agregarlos.' }}</p>
@else
    <div class="fichas-mini-tabla">
        <table data-proveedores-tabla>
            <thead>
                <tr>
                    <th scope="col">Proveedor</th>
                    <th scope="col">DNI</th>
                    @unless ($esSinAsignar)
                        <th scope="col">Teléfono</th>
                        <th scope="col">Última entrega</th>
                        <th scope="col" class="is-num">Litros (semana)</th>
                    @endunless
                    <th scope="col">Ubicación</th>
                    <th scope="col" class="is-accion"><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($proveedores as $proveedor)
                    @php($fila = $proveedor['fila'])
                    <tr data-zona-proveedor data-buscar-texto="{{ mb_strtolower($fila->nombre.' '.$fila->dni) }}">
                        <td class="fichas-mini-producto font-semibold" title="{{ $fila->nombre }}">{{ $fila->nombre }}</td>
                        <td class="is-muted">{{ $fila->dni }}</td>
                        @unless ($esSinAsignar)
                            <td class="is-muted">{{ $proveedor['telefono'] ?? '—' }}</td>
                            <td @class(['is-muted' => $proveedor['ultimaEntrega'] === null])>{{ $proveedor['ultimaEntrega'] ?? 'Sin entregas' }}</td>
                            <td class="is-num">{{ number_format($proveedor['litrosSemana'], 0) }} L</td>
                        @endunless
                        <td>
                            @if ($fila->tieneUbicacion())
                                <a href="https://www.google.com/maps/search/?api=1&query={{ $fila->latitud }},{{ $fila->longitud }}" target="_blank" rel="noopener noreferrer" class="zonas-ubicacion is-registrada" title="{{ $fila->referenciaUbicacion ?? 'Abrir en Google Maps' }}">
                                    <x-ui.icon name="map-pin" class="h-3.5 w-3.5" /> Registrada
                                </a>
                            @else
                                <button type="button" class="zonas-ubicacion is-ubicar" data-asignar-abrir="{{ json_encode($fila->paraModal()) }}" title="Ubicación no registrada: márcala en el mapa">
                                    <x-ui.icon name="map-pin" class="h-3.5 w-3.5" /> Ubicar en mapa
                                </button>
                            @endif
                        </td>
                        <td class="is-accion">
                            <div class="zonas-acciones">
                                <button type="button" class="fichas-ver" data-asignar-abrir="{{ json_encode($fila->paraModal()) }}" aria-label="{{ $fila->estaAsignado() ? 'Editar asignación de' : 'Asignar a zona a' }} {{ $fila->nombre }}" title="{{ $fila->estaAsignado() ? 'Editar asignación y ubicación' : 'Asignar a zona' }}">
                                    <x-ui.icon name="pencil" class="h-3.5 w-3.5" />
                                </button>
                                @if ($fila->estaAsignado())
                                    <form method="POST" action="{{ route('admin.rutas.detalles.destroy', [$fila->rutaId, $fila->detalleId]) }}" data-confirm="¿Quitar a {{ $fila->nombre }} de {{ $fila->zonaNombre }}?&#10;&#10;El proveedor seguirá existiendo; únicamente se eliminará su asignación actual y su ubicación.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="fichas-ver" aria-label="Quitar de la zona a {{ $fila->nombre }}" title="Quitar de la zona">
                                            <x-ui.icon name="x-mark" class="h-3.5 w-3.5" />
                                        </button>
                                    </form>
                                @endif
                                <a href="{{ route('admin.proveedores.index', ['proveedor' => $fila->proveedorId]) }}" class="fichas-ver" aria-label="Ver proveedor {{ $fila->nombre }}" title="Ver proveedor">
                                    <x-ui.icon name="chevron-right" class="h-4 w-4" />
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="fichas-panel-sin-datos mt-3" data-zona-proveedores-vacio hidden>Ningún proveedor coincide con la búsqueda.</p>
@endif
