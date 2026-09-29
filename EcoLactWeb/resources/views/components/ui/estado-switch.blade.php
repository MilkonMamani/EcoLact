@props(['activo', 'nombre', 'entidad' => 'registro'])

{{--
    Switch de estado Activo/Inactivo (verde / rojo) para tablas: solo el interruptor, sin texto
    visible; el estado va en title (tooltip) y en aria-label ("Proveedor activo: Rosa Quispe").
    No cambia nada por sí solo: admin-estado-switch.js abre la confirmación de desactivación o el
    modal de reactivación con zona, y lo repinta cuando el servidor confirma. aria-checked
    siempre refleja el estado guardado.
--}}
@php
    $estadoTexto = $activo ? 'Activo' : 'Inactivo';
@endphp

<button type="button" role="switch" aria-checked="{{ $activo ? 'true' : 'false' }}"
    aria-label="{{ ucfirst($entidad) }} {{ mb_strtolower($estadoTexto) }}: {{ $nombre }}"
    title="{{ $estadoTexto }}"
    data-estado-switch data-nombre="{{ $nombre }}" data-entidad="{{ $entidad }}"
    {{ $attributes->class(['estado-switch']) }}>
    <span class="estado-switch-pista" aria-hidden="true"><span class="estado-switch-perilla"></span></span>
</button>
