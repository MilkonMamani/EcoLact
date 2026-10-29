{{--
    "¿Cómo se mide este insumo?": primero la magnitud (peso, volumen o unidades) y después la unidad
    base de esa familia. Solo `unidad_base` viaja al servidor; la magnitud es ayuda visual.
    No hay unidad preseleccionada en un alta: elegir "ml" por descuido es lo que dejó la Sal en volumen.
    resources/js/pages/insumos.js muestra las unidades de la magnitud elegida y el texto de ayuda.
    Variables: $familiasUnidad (UnidadMedida::familias()), $idPrefijo, $valor (?string).
--}}
@php
    $iconosFamilia = ['MASA' => 'weight', 'VOLUMEN' => 'droplet', 'CONTEO' => 'package'];
    $familiaActual = null;
    foreach ($familiasUnidad as $clave => $familia) {
        if ($valor !== null && array_key_exists($valor, $familia['unidades'])) {
            $familiaActual = $clave;
        }
    }
@endphp

<fieldset class="insumos-medicion" data-selector-unidad>
    <legend class="text-sm font-semibold text-text">¿Cómo se mide este insumo? <span class="text-danger">*</span></legend>

    <div class="insumos-medicion-familias" role="radiogroup" aria-label="Magnitud">
        @foreach ($familiasUnidad as $clave => $familia)
            <label class="insumos-medicion-opcion">
                <input type="radio" name="{{ $idPrefijo }}_medicion" value="{{ $clave }}" class="sr-only" data-familia-radio @checked($familiaActual === $clave)>
                <span class="insumos-medicion-icono"><x-ui.icon :name="$iconosFamilia[$clave]" class="h-5 w-5" /></span>
                <span class="insumos-medicion-titulo">{{ $familia['titulo'] }}</span>
                <span class="insumos-medicion-unidades">{{ implode(' o ', array_map(fn ($c) => \App\Domain\Produccion\UnidadMedida::simbolo($c), array_keys($familia['unidades']))) }}</span>
            </label>
        @endforeach
    </div>

    @foreach ($familiasUnidad as $clave => $familia)
        <div class="insumos-medicion-base" data-familia-unidades="{{ $clave }}" @if ($familiaActual !== $clave) hidden @endif>
            <p class="text-xs font-medium text-text-muted">Unidad para controlar el inventario</p>
            <div class="mt-1.5 flex flex-wrap gap-2">
                @foreach ($familia['unidades'] as $codigo => $etiqueta)
                    <label class="insumos-medicion-unidad">
                        <input type="radio" name="unidad_base" value="{{ $codigo }}" class="sr-only" data-unidad-radio data-simbolo="{{ \App\Domain\Produccion\UnidadMedida::simbolo($codigo) }}" data-etiqueta="{{ $etiqueta }}" data-familia="{{ $clave }}" @checked($valor === $codigo) required>
                        {{ $etiqueta }}
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach

    <p class="insumos-medicion-ayuda" data-ayuda-unidad aria-live="polite">Elige si lo controlas por peso, por volumen o por unidades.</p>
    <x-forms.error name="unidad_base" />
</fieldset>
