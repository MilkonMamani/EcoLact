{{--
    Mapa general de Zonas: las zonas que pasan el filtro con su delimitación operativa (polígono
    GeoJSON guardado en zonas.poligono_geojson), una etiqueta "nombre + N proveedores" en el punto
    representativo del polígono y un pin (lágrima) por proveedor con coordenadas. Lo pinta admin-zonas.js
    desde [data-zonas-mapa-general-datos]; click en zona, etiqueta o leyenda selecciona la zona
    en la tabla y en el panel (mismo estado que hacer click en la fila).

    @var \App\Application\Zona\ZonasMapaData $mapaZonas
--}}
<section class="zonas-mapa-general" aria-labelledby="zonas-mapa-general-titulo" data-zonas-mapa-general data-url-proveedor="{{ route('admin.proveedores.index', ['proveedor' => '__ID__']) }}">
    <div class="zonas-seccion-head">
        <h2 id="zonas-mapa-general-titulo" class="zonas-seccion-titulo">Mapa de zonas</h2>
        <span class="zonas-mapa-nota">Delimitación operativa referencial</span>
    </div>

    <div class="zonas-mapa-general-marco">
        <div class="zonas-mapa-general-lienzo" data-zonas-mapa-general-lienzo role="application" aria-label="Mapa general con las zonas de acopio"></div>

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

    <p class="zonas-mapa-aviso" data-zonas-mapa-general-aviso hidden></p>

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
