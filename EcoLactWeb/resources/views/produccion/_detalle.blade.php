{{--
    Detalle de un lote (ADMINISTRADOR y ENCARGADO_PLANTA): productos planificados, insumos
    utilizados (fotografía histórica) y resultado final. Mientras el lote está EN_PROCESO se
    registra lo realmente obtenido. Finalizar NO vuelve a descontar insumos.
    Variables: $detalle (LoteProduccionDetalleData), $prefijo ('admin' | 'planta'), $puedeFinalizar (bool).
--}}
@php
    $lote = $detalle->lote;
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
@endphp

<x-ui.button :href="route($prefijo.'.produccion.index')" variant="ghost" size="sm" as="a">&larr; Volver a producción</x-ui.button>

<x-ui.card :title="$lote->codigo()" subtitle="Detalle del lote de producción">
    <x-slot:actions>
        <x-ui.badge :status="$lote->estado()" />
    </x-slot:actions>

    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <dt class="text-xs uppercase tracking-wide text-text-muted">Estado</dt>
            <dd class="mt-1 text-sm text-text">{{ $detalle->estaEnProceso() ? 'En proceso' : 'Finalizado' }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-text-muted">Inicio</dt>
            <dd class="mt-1 text-sm text-text">{{ $lote->fechaHoraInicio()->format('d/m/Y H:i') }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-text-muted">Fin</dt>
            <dd class="mt-1 text-sm text-text">{{ $lote->fechaHoraFin()?->format('d/m/Y H:i') ?? 'En proceso' }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-text-muted">Leche utilizada</dt>
            <dd class="mt-1 text-sm text-text">{{ $fmt($detalle->lecheUtilizada) }} L</dd>
        </div>
    </dl>

    @if ($lote->observacion())
        <p class="mt-4 text-sm text-text-muted">{{ $lote->observacion() }}</p>
    @endif
</x-ui.card>

<x-ui.card title="Productos planificados" subtitle="Lo que se planificó fabricar al iniciar la producción, con la receta usada.">
    <x-tables.table>
        <thead>
            <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                <th class="py-2 pr-4">Producto</th>
                <th class="py-2 pr-4">Receta usada</th>
                <th class="py-2 pr-4">Planificado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($detalle->productos as $producto)
                <tr class="border-b border-text-muted/5 last:border-0">
                    <td class="py-2 pr-4 text-text">{{ $producto['productoNombre'] }}</td>
                    <td class="py-2 pr-4 text-text-muted">{{ $producto['recetaVersion'] ? 'Versión '.$producto['recetaVersion'] : 'Sin receta (lote histórico)' }}</td>
                    <td class="py-2 pr-4 text-text">{{ $fmt($producto['cantidadPlanificada']) }} {{ $producto['unidadMedida'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </x-tables.table>
</x-ui.card>

<x-ui.card title="Insumos utilizados" subtitle="Fotografía de lo que se descontó al iniciar. No cambia aunque la receta se modifique después.">
    @if (count($detalle->insumosUtilizados) === 0)
        <x-ui.empty-state title="Sin insumos registrados" description="Este lote no registró consumo de insumos." :icon="false" />
    @else
        <x-tables.table>
            <thead>
                <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                    <th class="py-2 pr-4">Insumo</th>
                    <th class="py-2 pr-4">Cantidad utilizada</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($detalle->insumosUtilizados as $insumo)
                    <tr class="border-b border-text-muted/5 last:border-0">
                        <td class="py-2 pr-4 text-text">{{ $insumo['insumoNombre'] }}</td>
                        <td class="py-2 pr-4 text-text">{{ $fmt($insumo['cantidadUsada']) }} {{ $insumo['unidadBase'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </x-tables.table>
    @endif
</x-ui.card>

@if ($detalle->estaEnProceso())
    @if ($puedeFinalizar)
        <x-ui.card title="Registrar resultado y finalizar" subtitle="Escriba cuánto se obtuvo realmente de cada producto (puede ser menos de lo planificado).">
            <form method="POST" action="{{ route($prefijo.'.produccion.finalizar', $lote->id()) }}" class="space-y-4" data-confirm="¿Finalizar la producción? Los productos obtenidos entrarán al inventario y ya no se podrá cambiar.">
                @csrf

                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($detalle->productos as $producto)
                        <div>
                            <x-forms.input
                                :name="'obtenido['.$producto['detalleId'].']'"
                                :id="'obtenido_'.$producto['detalleId']"
                                type="number"
                                step="any"
                                min="0"
                                :label="'Obtenido: '.$producto['productoNombre'].' (planificado: '.$fmt($producto['cantidadPlanificada']).' '.$producto['unidadMedida'].')'"
                                required
                                :sticky="false"
                            />
                        </div>
                    @endforeach
                </div>

                <x-ui.alert variant="info">Al finalizar, lo obtenido pasa al inventario de productos. Los insumos <strong>no</strong> se vuelven a descontar: ya se descontaron al iniciar.</x-ui.alert>

                <div class="flex justify-end">
                    <x-ui.button type="submit">Finalizar producción</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif
@else
    <x-ui.card title="Resultado final" subtitle="Lo que realmente se obtuvo y ya está en el inventario de productos.">
        <x-tables.table>
            <thead>
                <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                    <th class="py-2 pr-4">Producto</th>
                    <th class="py-2 pr-4">Planificado</th>
                    <th class="py-2 pr-4">Obtenido</th>
                    <th class="py-2 pr-4">Diferencia</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($detalle->productos as $producto)
                    @php($diferencia = ($producto['cantidadObtenida'] ?? 0) - $producto['cantidadPlanificada'])
                    <tr class="border-b border-text-muted/5 last:border-0">
                        <td class="py-2 pr-4 text-text">{{ $producto['productoNombre'] }}</td>
                        <td class="py-2 pr-4 text-text-muted">{{ $fmt($producto['cantidadPlanificada']) }} {{ $producto['unidadMedida'] }}</td>
                        <td class="py-2 pr-4 font-medium text-text">{{ $fmt($producto['cantidadObtenida'] ?? 0) }} {{ $producto['unidadMedida'] }}</td>
                        <td class="py-2 pr-4 {{ $diferencia < 0 ? 'text-danger' : 'text-text-muted' }}">{{ ($diferencia > 0 ? '+' : '').$fmt($diferencia) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </x-tables.table>
    </x-ui.card>
@endif
