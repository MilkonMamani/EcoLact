{{-- Skeleton del panel derecho mientras llega el registro seleccionado (lo clona admin-fichas.js). --}}
<div class="fichas-panel-cargando" aria-busy="true" aria-label="Cargando">
    <div class="fichas-panel-head">
        <span class="fichas-bone is-avatar"></span>
        <div class="grid flex-1 gap-2.5">
            <span class="fichas-bone is-pill"></span>
            <span class="fichas-bone is-xl"></span>
            <span class="fichas-bone is-md"></span>
        </div>
    </div>
    <div class="fichas-tabs"><span class="fichas-bone is-tabs"></span></div>
    <div class="fichas-panel-cuerpo grid gap-4">
        <span class="fichas-bone is-qr"></span>
        @foreach (['is-xl', 'is-lg', 'is-xl', 'is-md', 'is-lg'] as $ancho)
            <span class="fichas-bone {{ $ancho }}"></span>
        @endforeach
    </div>
</div>
