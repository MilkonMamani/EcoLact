{{--
    Formulario "Configurar sueldo" de UN rol (fila de SueldosPorRolQuery::listar()). El rol no se
    edita. Sin sueldo previo crea la primera vigencia (POST); con sueldo previo crea una NUEVA versión
    (PATCH sobre la vigente): con una fecha posterior la anterior queda en el historial, con la misma
    fecha se corrige solo si todavía no generó pagos. El envío pasa por un paso de confirmación
    (admin-configuracion-pagos.js); sin JS se envía directo y el backend valida igual.
--}}
@php
    $configurado = $fila['configuracionId'] !== null;
    $clave = 'sueldo-rol-'.$fila['rolId'];
    $sticky = old('_modal') === $clave;
    $fechaCorta = fn (?string $valor): string => $valor ? \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y') : '—';
    $personas = $fila['personal'] === 1 ? '1 trabajador' : $fila['personal'].' trabajadores';
@endphp

<x-ui.modal :id="'modal-'.$clave" size="form" title="Configurar sueldo" :subtitle="$fila['rol'].' · '.$personas" :open="$sticky && $errors->any()">
    <form method="POST" action="{{ $configurado ? route('admin.configuracion-pagos.update', $fila['configuracionId']) : route('admin.configuracion-pagos.store') }}"
        data-config-sueldo data-config-sueldo-rol
        data-rol="{{ $fila['rol'] }}" data-personal="{{ $personas }}" data-sueldo-actual="{{ $configurado ? $fila['sueldo'] : '' }}">
        @csrf
        @if ($configurado)
            @method('PATCH')
        @else
            <input type="hidden" name="tipo_beneficiario" value="PERSONAL">
            <input type="hidden" name="rol_id" value="{{ $fila['rolId'] }}">
        @endif
        <input type="hidden" name="_modal" value="{{ $clave }}">

        <div data-paso-edicion>
            @if ($configurado)
                <div class="config-roles-aviso" role="note">
                    <x-ui.icon name="info" class="h-4 w-4 shrink-0" />
                    <p>
                        Sueldo actual: <strong>S/ {{ number_format($fila['sueldo'], 2) }}</strong> desde {{ $fechaCorta($fila['vigenteDesde']) }}.
                        Se creará una <strong>nueva versión salarial</strong>: con una fecha posterior, el sueldo actual queda en el historial y los periodos anteriores conservan su valor.
                    </p>
                </div>
            @endif

            <div class="config-pagos-campos">
                <div class="is-ancho">
                    <p class="config-pagos-etiqueta">Rol</p>
                    <p class="config-roles-fijo">{{ $fila['rol'] }}</p>
                </div>
                <x-forms.input name="monto_base" :id="$clave.'-sueldo'" type="number" step="0.01" min="0.01" max="9999999999.99" inputmode="decimal" label="Sueldo mensual (S/)" required :sticky="$sticky" :value="$configurado ? number_format($fila['sueldo'], 2, '.', '') : null" data-sueldo />
                <div>
                    <p class="config-pagos-etiqueta">Valor diario referencial</p>
                    <p class="config-pagos-calculado" data-valor-dia aria-live="polite">{{ $configurado ? 'S/ '.number_format($fila['valorDia'], 2) : '—' }}</p>
                    <p class="config-pagos-nota">Sueldo mensual ÷ 30. Solo informativo.</p>
                </div>
                <div class="is-ancho">
                    <x-forms.input name="fecha_inicio" :id="$clave.'-inicio'" type="date" label="Vigente desde" required :sticky="$sticky" :value="now()->toDateString()" data-fecha-inicio />
                    @if ($configurado)
                        <p class="config-pagos-nota mt-1">Con la misma fecha ({{ $fechaCorta($fila['vigenteDesde']) }}) solo se corrige el sueldo actual, y únicamente si todavía no generó pagos.</p>
                    @endif
                </div>
                <div class="is-ancho">
                    <x-forms.textarea name="observacion" :id="$clave.'-observacion'" :label="$configurado ? 'Motivo del cambio' : 'Observación (opcional)'" rows="2" maxlength="500" :required="$configurado" :sticky="$sticky" />
                </div>
            </div>
        </div>

        <div class="config-roles-confirmacion" data-paso-confirmacion hidden>
            <p class="config-roles-confirmacion-titulo">Confirma la configuración salarial</p>
            <dl>
                <div><dt>Rol</dt><dd>{{ $fila['rol'] }}</dd></div>
                @if ($configurado)
                    <div><dt>Sueldo actual</dt><dd class="is-num">S/ {{ number_format($fila['sueldo'], 2) }}</dd></div>
                @endif
                <div><dt>{{ $configurado ? 'Sueldo nuevo' : 'Sueldo mensual' }}</dt><dd class="is-num is-destacado" data-resumen-sueldo>—</dd></div>
                <div><dt>Vigente desde</dt><dd class="is-num" data-resumen-fecha>—</dd></div>
                <div><dt>Lo heredan</dt><dd>{{ $personas }} activos y quienes se registren después</dd></div>
            </dl>
            <p class="config-pagos-nota">Los pagos ya generados y los periodos anteriores no cambian.</p>
        </div>

        <div class="config-pagos-modal-pie">
            <x-ui.button type="button" data-modal-close data-boton-cancelar variant="ghost">Cancelar</x-ui.button>
            <x-ui.button type="button" data-boton-revisar variant="ghost" hidden>Volver a editar</x-ui.button>
            <x-ui.button type="submit" data-boton-guardar>Guardar configuración</x-ui.button>
        </div>
    </form>
</x-ui.modal>
