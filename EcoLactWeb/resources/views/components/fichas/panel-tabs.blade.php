@props(['pestanas', 'etiqueta'])

{{-- Pestañas del panel derecho; admin-fichas.js las activa y conserva la elegida al cambiar de registro. --}}
<div class="fichas-tabs" role="tablist" aria-label="{{ $etiqueta }}">
    @foreach ($pestanas as $clave => $texto)
        <button type="button" role="tab" id="ficha-tab-{{ $clave }}" aria-controls="ficha-panel-{{ $clave }}" data-fichas-tab="{{ $clave }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" @if (! $loop->first) tabindex="-1" @endif>{{ $texto }}</button>
    @endforeach
</div>
