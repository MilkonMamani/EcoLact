{{--
    Mapa general de Zonas: las zonas que pasan el filtro con su delimitación operativa (polígono
    GeoJSON guardado en zonas.poligono_geojson), una etiqueta "nombre + N proveedores" en el punto
    representativo del polígono y un pin (lágrima) por proveedor con coordenadas. Lo pinta admin-zonas.js
    desde [data-zonas-mapa-general-datos]; click en zona, etiqueta o leyenda abre esa zona en el panel.
    Los pines nunca se arrastran salvo en "Mover ubicación" (barra [data-zonas-mover-barra]), y solo
    el del proveedor seleccionado.

    @var \App\Application\Zona\ZonasMapaData $mapaZonas
--}}
<section class="zonas-mapa-general" aria-labelledby="zonas-mapa-general-titulo" data-zonas-mapa-general data-url-proveedor="{{ route('admin.proveedores.index', ['proveedor' => '__ID__']) }}">
    <div class="zonas-seccion-head">
        <h2 id="zonas-mapa-general-titulo" class="zonas-seccion-titulo">Mapa de zonas</h2>
        <span class="zonas-mapa-nota">Delimitación operativa referencial</span>
    </div>

    <div class="zonas-mapa-general-marco">
        <div class="zonas-mapa-general-lienzo" data-zonas-mapa-general-lienzo role="application" aria-label="Mapa general con las zonas de acopio"></div>

        {{-- Mientras carga Google Maps: "Cargando mapa..."; si no se puede usar, el aviso de EcoLact con Reintentar. --}}
        <div class="zonas-mapa-estado" role="status" aria-live="polite" data-zonas-mapa-estado>
            <x-ui.icon name="map" class="h-6 w-6" />
            <p data-zonas-mapa-estado-texto>Cargando mapa...</p>
            <button type="button" class="fichas-btn is-outline is-sm" data-zonas-mapa-reintentar hidden>
                <x-ui.icon name="refresh" class="h-3.5 w-3.5" /> Reintentar
            </button>
        </div>

        <div class="zonas-mover-barra" role="status" aria-live="polite" data-zonas-mover-barra hidden>
            <div class="min-w-0">
                <p class="zonas-mover-titulo">Moviendo ubicación · <strong data-zonas-mover-nombre></strong></p>
                <p class="zonas-mover-ayuda" data-zonas-mover-ayuda>Arrastra el marcador a su nueva ubicación.</p>
            </div>
            <div class="zonas-mover-acciones">
                <button type="button" class="fichas-btn is-outline is-sm" data-zonas-mover-cancelar>Cancelar</button>
                <button type="button" class="fichas-btn is-primary is-sm" data-zonas-mover-guardar disabled>Guardar</button>
            </div>
        </div>

        <ul class="zonas-leyenda" aria-label="Leyenda de zonas">
            <li class="zonas-leyenda-titulo" aria-hidden="true">Leyenda</li>
            @foreach ($mapaZonas->zonas as $zona)
                <li>
                    <button type="button" data-zonas-seleccionar="{{ $zona['id'] }}" @class(['is-inactiva' => $zona['estado'] !== 'ACTIVO']) title="{{ $zona['geometry'] === null ? 'Sin delimitación dibujada' : 'Ver '.$zona['nombre'] }}">
                        <span class="zonas-leyenda-color" style="--zona-color: {{ $zona['color'] }}" aria-hidden="true"></span>{{ $zona['nombre'] }}@if ($zona['geometry'] === null)<span class="zonas-leyenda-pendiente">pendiente</span>@endif
                    </button>
                </li>
            @endforeach
            <li class="zonas-leyenda-pin">
                <svg viewBox="0 0 26 34" aria-hidden="true"><path d="M13 1.5C6.6 1.5 1.5 6.6 1.5 12.9c0 8.3 9.9 18.4 10.8 19.3a1 1 0 0 0 1.4 0c.9-.9 10.8-11 10.8-19.3C24.5 6.6 19.4 1.5 13 1.5z" fill="currentColor" stroke="#fff" stroke-width="2"/><circle cx="13" cy="12.8" r="4.3" fill="#fff"/></svg>
                Proveedor
            </li>
        </ul>
    </div>

    @php
        $pendientes = array_column(array_filter($mapaZonas->zonas, fn (array $zona): bool => $zona['geometry'] === null), 'nombre');
    @endphp
    @if (! $mapaZonas->hayDelimitaciones())
        <p class="zonas-mapa-nota is-bloque">Todavía no hay delimitaciones dibujadas. Elige una zona y usa <strong>Dibujar delimitación</strong> (o Editar zona → Editar delimitación).</p>
    @elseif ($pendientes !== [])
        <p class="zonas-mapa-nota is-bloque">Delimitación pendiente: {{ implode(', ', $pendientes) }}.</p>
    @endif

    <script type="application/json" data-zonas-mapa-general-datos>{!! json_encode($mapaZonas->toArray(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
</section>
