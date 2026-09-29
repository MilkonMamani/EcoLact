@props(['ficha'])

{{-- Cabecera del panel derecho de las fichas de personal (avatar, estado, nombre, DNI y cerrar). --}}
<header class="fichas-panel-head">
    <span class="fichas-avatar" aria-hidden="true">{{ $ficha->iniciales() }}</span>
    <div class="min-w-0 flex-1">
        <x-ui.badge :status="$ficha->estado">{{ $ficha->estaActivo() ? 'Activo' : 'Inactivo' }}</x-ui.badge>
        <h2 class="fichas-panel-nombre">{{ $ficha->nombreCompleto() }}</h2>
        <p class="fichas-panel-dni">DNI {{ $ficha->dni }}</p>
    </div>
    <button type="button" class="fichas-cerrar" data-fichas-cerrar aria-label="Cerrar panel" title="Cerrar panel">
        <x-ui.icon name="x-mark" class="h-5 w-5" />
    </button>
</header>
