{{--
    "Delimitación y ubicaciones" del Resumen de una zona: cuántos proveedores tienen punto de
    recolección registrado (ruta_detalles.latitud/longitud), si la zona ya tiene delimitación y
    qué ubicaciones quedaron fuera de ella. Sin mapa propio: los puntos se ven y se mueven en el
    mapa general de la pantalla.

    @var \App\Application\Zona\ZonaPanelData $panel
--}}
@php
    $fueraDeDelimitacion = $panel->proveedoresFueraDeDelimitacion();
    $total = count($panel->proveedores);
@endphp

<div class="fichas-seccion">
    <div class="fichas-seccion-head">
        <h3>Delimitación y ubicaciones</h3>
        {{-- Atajo a Editar zona → Editar delimitación, con el editor ya abierto. --}}
        <button type="button" class="fichas-editar" data-modal-open="{{ $modalEditar }}" data-delimitacion-abrir>
            <x-ui.icon name="pen-line" class="h-3.5 w-3.5" /> {{ $panel->delimitacion ? 'Editar delimitación' : 'Dibujar delimitación' }}
        </button>
    </div>

    @if ($total > 0)
        <dl class="zonas-coordenadas">
            <div><dt>Con coordenadas</dt><dd class="is-num">{{ count($panel->proveedoresConUbicacion()) }}</dd></div>
            <div><dt>Sin coordenadas</dt><dd class="is-num">{{ $panel->proveedoresSinUbicacion() }}</dd></div>
        </dl>
    @endif

    @if ($panel->delimitacion === null)
        <p class="zonas-mapa-nota is-bloque"><strong>Delimitación pendiente.</strong> Esta zona aún no tiene una delimitación operativa.</p>
    @endif

    @if ($total === 0)
        <p class="zonas-mapa-nota is-bloque">Esta zona aún no tiene proveedores.</p>
    @endif

    @foreach ($fueraDeDelimitacion as $fila)
        <p class="zonas-fuera" role="note">
            <x-ui.icon name="triangle-alert" class="h-4 w-4" />
            <span><strong>{{ $fila->nombre }}:</strong> la ubicación registrada se encuentra fuera de la delimitación operativa de {{ $panel->nombre }}.</span>
        </p>
    @endforeach
</div>
