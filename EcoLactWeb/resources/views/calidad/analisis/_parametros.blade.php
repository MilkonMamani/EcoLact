{{--
    Campos de los parámetros de calidad ACTIVOS, por código (nunca por id).
    origenes[CODIGO] queda en OCR si el valor lo propuso el ticket y el analista
    no lo tocó; al editarlo pasa a MANUAL.
--}}
<div>
    <p class="mb-2 text-sm font-medium text-text">Parámetros de calidad</p>
    @if (count($parametros) === 0)
        <x-ui.empty-state title="No hay parámetros de calidad configurados" :icon="false" />
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($parametros as $parametro)
                <div>
                    <x-forms.input
                        name="valores[{{ $parametro->codigo() }}]"
                        type="number"
                        step="any"
                        inputmode="decimal"
                        :label="$parametro->nombre() . ($parametro->unidad() ? ' (' . $parametro->unidad() . ')' : '')"
                        data-parametro-codigo="{{ $parametro->codigo() }}"
                    />
                    <input type="hidden" name="origenes[{{ $parametro->codigo() }}]" value="MANUAL" data-parametro-origen="{{ $parametro->codigo() }}">
                    <p class="mt-1 text-xs" data-parametro-estado="{{ $parametro->codigo() }}" hidden></p>
                </div>
            @endforeach
        </div>
    @endif
</div>
