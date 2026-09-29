@props(['entidad'])

<div class="fichas-panel-vacio">
    <span class="fichas-panel-vacio-icono"><x-ui.icon name="user" class="h-6 w-6" /></span>
    <p class="font-semibold text-text">Selecciona un {{ $entidad }}</p>
    <p>{{ $slot }}</p>
</div>
