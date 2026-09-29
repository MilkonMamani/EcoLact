{{--
    "Ubicación de proveedores" del Resumen de una zona: mini mapa de Google Maps con SOLO la zona
    seleccionada (su delimitación, si ya se dibujó) y sus puntos de recolección registrados
    (ruta_detalles.latitud/longitud), y "Ver mapa completo" en un modal. El mapa general de la
    pantalla es el que muestra todas las zonas. admin-zonas.js lee los datos de [data-zona-mapa-datos].

    @var \App\Application\Zona\ZonaPanelData $panel
--}}
@php
    $conUbicacion = $panel->proveedoresConUbicacion();
    $fueraDeDelimitacion = $panel->proveedoresFueraDeDelimitacion();
    $idsFuera = array_map(fn ($fila) => $fila->proveedorId, $fueraDeDelimitacion);
    $total = count($panel->proveedores);
    $hayMapa = $conUbicacion !== [] || $panel->delimitacion !== null;
@endphp

<div class="fichas-seccion">
    <div class="fichas-seccion-head">
        <h3>Ubicación de proveedores</h3>
        <div class="flex flex-wrap items-center justify-end gap-1.5">
            {{-- Atajo a Editar zona → Editar delimitación, con el editor ya abierto. --}}
            <button type="button" class="fichas-editar" data-modal-open="{{ $modalEditar }}" data-delimitacion-abrir>
                <x-ui.icon name="pen-line" class="h-3.5 w-3.5" /> {{ $panel->delimitacion ? 'Editar delimitación' : 'Dibujar delimitación' }}
            </button>
            @if ($hayMapa)
                <button type="button" class="fichas-editar" data-modal-open="modal-mapa-zona" data-zona-mapa-ampliar>
                    <x-ui.icon name="map" class="h-3.5 w-3.5" /> Ver mapa completo
                </button>
            @endif
        </div>
    </div>

    @if ($total > 0)
        <dl class="zonas-coordenadas">
            <div><dt>Con coordenadas</dt><dd class="is-num">{{ count($conUbicacion) }}</dd></div>
            <div><dt>Sin coordenadas</dt><dd class="is-num">{{ $panel->proveedoresSinUbicacion() }}</dd></div>
        </dl>
    @endif

    @if ($panel->delimitacion === null)
        <p class="zonas-mapa-nota is-bloque"><strong>Delimitación pendiente.</strong> Esta zona aún no tiene una delimitación operativa.</p>
    @endif

    @if (! $hayMapa)
        <div class="zonas-mapa-vacio">
            <x-ui.icon name="map-pin" class="h-6 w-6" />
            <p class="font-semibold text-text">Sin ubicaciones registradas</p>
            <p>{{ $total > 0 ? 'Ningún proveedor de esta zona tiene coordenadas todavía.' : 'Esta zona aún no tiene proveedores.' }}</p>
        </div>
    @else
        <div data-zona-mapa-lienzo class="zonas-mapa"></div>
        <p data-zona-mapa-aviso hidden class="mt-2 text-xs text-warning"></p>

        <script type="application/json" data-zona-mapa-datos>{!! json_encode([
            'zona' => [
                'id' => $panel->id,
                'nombre' => $panel->nombre,
                'color' => $panel->colorMapa,
                'geometry' => $panel->delimitacion?->aGeoJson(),
            ],
            'proveedores' => array_map(fn ($fila) => [
                'id' => $fila->proveedorId,
                'nombre' => $fila->nombre,
                'dni' => $fila->dni,
                'lat' => $fila->latitud,
                'lng' => $fila->longitud,
                'fueraDeDelimitacion' => in_array($fila->proveedorId, $idsFuera, true),
            ], $conUbicacion),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif

    @foreach ($fueraDeDelimitacion as $fila)
        <p class="zonas-fuera" role="note">
            <x-ui.icon name="triangle-alert" class="h-4 w-4" />
            <span><strong>{{ $fila->nombre }}:</strong> la ubicación registrada se encuentra fuera de la delimitación operativa de {{ $panel->nombre }}.</span>
        </p>
    @endforeach
</div>
