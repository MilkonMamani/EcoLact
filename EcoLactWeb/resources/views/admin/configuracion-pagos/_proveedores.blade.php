{{--
    Pago a proveedores: UNA configuración general (no por proveedor). La semana se liquida con el
    PEOR resultado de calidad de sus entregas: ACEPTADA = precio normal, OBSERVADA = descuento por
    litro a TODOS los litros de la semana, RECHAZADA = la semana no se paga. Semanal (jueves a
    miércoles) y pago el miércoles son fijos: se muestran, no se editan.
    $proveedor: ObtenerConfiguracionesPago::execute()['proveedor'].
--}}
@php
    $puedeConfigurar = auth()->user()->can('create', \App\Infrastructure\Persistence\Models\ConfiguracionPagoModel::class);
    $configuracion = $proveedor['configuracion'];
    $precio = $proveedor['precioBase'];
    $observada = $proveedor['observada'];
    $porcentaje = ($observada['tipo'] ?? null) === 'PORCENTAJE';
    $descuento = $observada['valor'] ?? 0.0;
    $descuentoLitro = $precio !== null ? ($porcentaje ? round($precio * $descuento / 100, 4) : $descuento) : null;
    $soles = fn (?float $valor): string => 'S/ '.number_format((float) $valor, 2);
    $clave = 'config-proveedores';
    $sticky = old('_modal') === $clave;
@endphp

<div class="config-proveedores">
    <section class="config-proveedores-bloque">
        <div class="config-proveedores-head">
            <div>
                <h2>Pago a proveedores</h2>
                <p>Una sola configuración para todos los proveedores de leche.</p>
            </div>
            @if ($configuracion)
                @include('admin.configuracion-pagos._estado', ['estado' => $proveedor['estado']])
            @endif
        </div>

        @if ($configuracion === null)
            <div class="fichas-panel-sin-datos config-pagos-sin-sueldo">
                <p class="font-semibold text-text">Sin configurar</p>
                <p>Define el precio por litro para que EcoLact calcule el pago semanal de los proveedores.</p>
                @if ($puedeConfigurar)
                    <button type="button" class="fichas-btn is-primary is-sm mt-2" data-modal-open="modal-{{ $clave }}">
                        <x-ui.icon name="plus" class="h-3.5 w-3.5" /> Configurar pago a proveedores
                    </button>
                @endif
            </div>
        @else
            <dl class="fichas-datos config-proveedores-datos">
                <div>
                    <dt>Precio base</dt>
                    <dd class="is-num font-semibold">{{ $precio !== null ? $soles($precio).' / L' : 'Sin precio vigente' }}</dd>
                </div>
                <div>
                    <dt>Periodicidad</dt>
                    <dd>Semanal <span class="config-pagos-nota">(jueves a miércoles)</span></dd>
                </div>
                <div>
                    <dt>Día de pago</dt>
                    <dd>Miércoles</dd>
                </div>
                <div>
                    <dt>Vigente desde</dt>
                    <dd class="is-num">{{ $configuracion->fechaInicio()->format('d/m/Y') }}</dd>
                </div>
                @if ($configuracion->observacion())
                    <div class="is-ancho">
                        <dt>Observación</dt>
                        <dd>{{ $configuracion->observacion() }}</dd>
                    </div>
                @endif
            </dl>

            <h3 class="config-proveedores-subtitulo">Ajuste por calidad</h3>
            <p class="config-pagos-nota">Cada semana se paga según el peor resultado de calidad de sus entregas.</p>
            <ul class="config-calidad">
                <li>
                    <span class="config-calidad-resultado is-aceptada">Aceptada</span>
                    <span class="config-calidad-regla">Precio normal</span>
                    <span class="config-calidad-precio">{{ $precio !== null ? $soles($precio).' / L' : '—' }}</span>
                </li>
                <li>
                    <span class="config-calidad-resultado is-observada">Observada</span>
                    <span class="config-calidad-regla">
                        @if ($descuento > 0)
                            {{ $porcentaje ? '- '.rtrim(rtrim(number_format($descuento, 2), '0'), '.').' %' : '- '.$soles($descuento).' / L' }}
                        @else
                            Sin descuento
                        @endif
                        <small>Aplica a todos los litros de la semana</small>
                    </span>
                    <span class="config-calidad-precio">{{ $precio !== null ? $soles(max(0, $precio - $descuentoLitro)).' / L' : '—' }}</span>
                </li>
                <li>
                    <span class="config-calidad-resultado is-rechazada">Rechazada</span>
                    <span class="config-calidad-regla">No pagable<small>La semana completa no se paga</small></span>
                    <span class="config-calidad-precio">S/ 0.00</span>
                </li>
            </ul>

            @if ($puedeConfigurar)
                <button type="button" class="fichas-btn is-outline is-sm mt-5" data-modal-open="modal-{{ $clave }}">
                    <x-ui.icon name="pencil" class="h-3.5 w-3.5" /> Editar configuración
                </button>
            @endif

            @if ($proveedor['preciosAnteriores'] !== [])
                <h3 class="config-proveedores-subtitulo">Precios anteriores</h3>
                <ul class="config-pagos-historial">
                    @foreach ($proveedor['preciosAnteriores'] as $anterior)
                        <li>
                            <span class="is-num font-medium">{{ $soles($anterior->precioLitro()) }} / L</span>
                            <span>{{ $anterior->fechaInicio()->format('d/m/Y') }} – {{ $anterior->fechaFin()?->format('d/m/Y') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </section>
</div>

@if ($puedeConfigurar)
    <x-ui.modal :id="'modal-'.$clave" size="form" :title="$configuracion ? 'Editar pago a proveedores' : 'Configurar pago a proveedores'" subtitle="Precio por litro y descuento por calidad observada." :open="$sticky && $errors->any()">
        <form method="POST" action="{{ $configuracion ? route('admin.configuracion-pagos.update', $configuracion->id()) : route('admin.configuracion-pagos.store') }}" data-config-proveedor>
            @csrf
            @if ($configuracion)
                @method('PATCH')
            @else
                <input type="hidden" name="tipo_beneficiario" value="PROVEEDOR">
            @endif
            <input type="hidden" name="_modal" value="{{ $clave }}">

            <div class="config-pagos-campos">
                <x-forms.input name="precio_base_litro" :id="$clave.'-precio'" type="number" step="0.0001" min="0.0001" inputmode="decimal" label="Precio base por litro (S/)" required :sticky="$sticky" :value="$precio !== null ? rtrim(rtrim(number_format($precio, 4, '.', ''), '0'), '.') : null" data-precio-base />
                <x-forms.input name="fecha_inicio" :id="$clave.'-inicio'" type="date" label="Vigente desde" required :sticky="$sticky" :value="now()->toDateString()" />
                <div class="is-ancho">
                    <x-forms.input name="descuento_observada" :id="$clave.'-observada'" type="number" step="0.0001" min="0" inputmode="decimal" label="Descuento por litro si la semana es OBSERVADA (S/)" :sticky="$sticky" :value="$descuentoLitro !== null ? rtrim(rtrim(number_format($descuentoLitro, 4, '.', ''), '0'), '.') : '0'" data-descuento-observada />
                    <p class="config-pagos-nota mt-1">Precio resultante: <strong data-precio-observada>—</strong> por litro, para todos los litros de esa semana. Si la semana es RECHAZADA no se paga.</p>
                </div>
                <div class="is-ancho">
                    <x-forms.textarea name="observacion" :id="$clave.'-observacion'" label="Observación" rows="2" maxlength="500" :sticky="$sticky" :value="$configuracion?->observacion()" />
                </div>
            </div>
            @if ($configuracion)
                <p class="config-pagos-nota mt-3">Con una fecha posterior a {{ $configuracion->fechaInicio()->format('d/m/Y') }} los valores nuevos rigen desde esa fecha y los anteriores quedan en el historial. Los pagos ya generados no cambian.</p>
            @endif

            <div class="config-pagos-modal-pie">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">{{ $configuracion ? 'Guardar cambios' : 'Guardar configuración' }}</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endif
