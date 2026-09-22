@php
    /** @var int|string $seleccion */
    $esSinAsignar = $seleccion === 'sin-asignar';
    $panelUrl = $esSinAsignar ? route('admin.zonas.panel.sin-asignar') : route('admin.zonas.panel', $seleccion);
@endphp

{{--
    Fragmento cargado por AJAX al cambiar de zona (ver cargarPanel en
    admin-zonas.js). Sus dos hijos directos se distribuyen en las dos
    columnas del layout: la tabla de proveedores a la derecha y el mapa
    debajo del listado de zonas a la izquierda (data-zona-panel, en el
    Blade que lo incluye, usa display:contents para que ambos participen
    del grid exterior sin un contenedor visual de por medio).
--}}
<div data-zona-panel-contenido data-panel-url="{{ $panelUrl }}" data-seleccion="{{ $seleccion }}" aria-live="polite" class="min-w-0 lg:col-start-2 lg:row-start-1 lg:row-span-2">
    @if ($esSinAsignar)
        <x-ui.card title="Proveedores sin zona" subtitle="Proveedores registrados que todavía no están en ninguna zona. Pulsa el lápiz para asignarles una.">
            @include('admin.zonas._tabla-proveedores', ['esSinAsignar' => true])
        </x-ui.card>
    @elseif ($zonaSeleccionada === null)
        <x-ui.card>
            <x-ui.empty-state title="Aún no hay zonas" description="Crea la primera zona con el botón + del panel izquierdo para empezar a asignar proveedores." :icon="false" />
        </x-ui.card>
    @else
        <x-ui.card title="Proveedores de la zona" subtitle="Proveedores asignados a la zona seleccionada.">
            @include('admin.zonas._tabla-proveedores', ['esSinAsignar' => false])
        </x-ui.card>
    @endif
</div>

<div data-zona-mapa-contenido class="min-w-0 lg:col-start-1 lg:row-start-2">
    @include('admin.zonas._mapa', ['esSinAsignar' => $esSinAsignar])
</div>
