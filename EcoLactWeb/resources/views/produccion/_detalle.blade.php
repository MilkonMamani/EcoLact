{{--
    Detalle de un lote (ADMINISTRADOR y ENCARGADO_PLANTA): qué se planificó, qué insumos se descontaron
    (fotografía histórica, no cambia si la receta cambia después) y el resultado. Mientras el lote está
    EN_PROCESO, la acción principal es registrar lo realmente obtenido y finalizar; finalizar NO vuelve a
    descontar insumos y es lo único que suma el producto terminado al inventario.
    Variables: $detalle (LoteProduccionDetalleData), $prefijo ('admin' | 'planta'), $puedeFinalizar (bool).
--}}
@php
    $lote = $detalle->lote;
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
    $cantidad = fn ($v, $u) => \App\Application\Produccion\LoteProduccionDetalleData::cantidad((float) $v, $u);
@endphp

<x-ui.button :href="route($prefijo.'.produccion.index')" variant="ghost" size="sm" as="a">&larr; Volver a producción</x-ui.button>

{{-- Cabecera: qué lote es, qué se fabrica y cuándo empezó. --}}
<section class="rounded-xl border border-text-muted/10 bg-surface p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="font-mono text-sm text-text-muted">{{ $lote->codigo() }}</p>
            <h1 class="mt-1 text-xl font-bold text-text">{{ $detalle->titulo() }}</h1>
        </div>
        <x-ui.badge :status="$lote->estado()" />
    </div>

    <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
        <div>
            <dt class="text-xs uppercase tracking-wide text-text-muted">Inicio</dt>
            <dd class="mt-1 text-sm font-medium text-text">{{ $lote->fechaHoraInicio()->format('d/m/Y · H:i') }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-text-muted">Leche utilizada</dt>
            <dd class="mt-1 text-sm font-medium text-text">{{ $fmt($detalle->lecheUtilizada) }} L</dd>
        </div>
        @if (! $detalle->estaEnProceso())
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Finalizado</dt>
                <dd class="mt-1 text-sm font-medium text-text">{{ $lote->fechaHoraFin()?->format('d/m/Y · H:i') ?? '—' }}</dd>
            </div>
        @endif
    </dl>

    @if ($lote->observacion())
        <p class="mt-4 text-sm text-text-muted"><span class="font-medium text-text">Observación al iniciar:</span> {{ $lote->observacion() }}</p>
    @endif
</section>

<div class="grid gap-4 lg:grid-cols-5 lg:items-start">
    {{-- Acción principal: finalizar (en proceso) o el resultado (finalizado). --}}
    <div class="space-y-4 lg:col-span-3">
        @if ($detalle->estaEnProceso())
            @if ($puedeFinalizar)
                <x-ui.card title="Finalizar producción" subtitle="Registra cuánto se obtuvo realmente.">
                    <form
                        method="POST"
                        action="{{ route($prefijo.'.produccion.finalizar', $lote->id()) }}"
                        class="space-y-5"
                        data-produccion-finalizar
                        data-confirm="¿Finalizar la producción {{ $lote->codigo() }}? Lo obtenido se sumará al stock de Productos y el lote ya no se podrá modificar."
                    >
                        @csrf

                        @foreach ($detalle->productos as $producto)
                            @php($campo = 'obtenido.'.$producto['detalleId'])
                            <div
                                class="space-y-4 {{ ! $loop->first ? 'border-t border-text-muted/10 pt-5' : '' }}"
                                data-producto-final
                                data-planificado="{{ $producto['cantidadPlanificada'] }}"
                                data-stock="{{ $producto['stockActual'] }}"
                                data-unidad="{{ $producto['unidadMedida'] }}"
                                data-etiqueta="{{ $producto['etiqueta'] }}"
                            >
                                <dl class="grid grid-cols-2 gap-4">
                                    <div>
                                        <dt class="text-xs uppercase tracking-wide text-text-muted">Producto</dt>
                                        <dd class="mt-1 text-sm font-semibold text-text">{{ $producto['etiqueta'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs uppercase tracking-wide text-text-muted">Cantidad planificada</dt>
                                        <dd class="mt-1 text-sm font-semibold text-text">{{ $cantidad($producto['cantidadPlanificada'], $producto['unidadMedida']) }}</dd>
                                    </div>
                                </dl>

                                <div class="grid gap-4 sm:grid-cols-2 sm:items-end">
                                    <div>
                                        <x-forms.input
                                            :name="'obtenido['.$producto['detalleId'].']'"
                                            :id="'obtenido_'.$producto['detalleId']"
                                            type="number"
                                            step="any"
                                            min="0"
                                            inputmode="decimal"
                                            label="Cantidad obtenida"
                                            :value="old($campo)"
                                            placeholder="Ej. {{ $fmt($producto['cantidadPlanificada']) }}"
                                            required
                                            :sticky="false"
                                            data-obtenido
                                        />
                                        <x-forms.error :name="$campo" />
                                    </div>
                                    <p class="rounded-lg bg-primary-soft/40 px-3 py-2 text-sm text-text-muted" aria-live="polite">
                                        <span data-diferencia-etiqueta>Merma</span>:
                                        <strong class="font-semibold text-text" data-diferencia>—</strong>
                                    </p>
                                </div>

                                {{-- Vista previa: el stock real recién cambia al confirmar "Finalizar producción". --}}
                                <div class="rounded-lg border border-text-muted/15 p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Impacto en inventario</p>
                                    <dl class="mt-2 grid grid-cols-3 gap-2 text-sm">
                                        <div>
                                            <dt class="text-text-muted">Stock actual</dt>
                                            <dd class="mt-0.5 font-medium text-text">{{ $cantidad($producto['stockActual'], $producto['unidadMedida']) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-text-muted">Producción obtenida</dt>
                                            <dd class="mt-0.5 font-medium text-success" data-incremento>—</dd>
                                        </div>
                                        <div>
                                            <dt class="text-text-muted">Stock resultante</dt>
                                            <dd class="mt-0.5 font-semibold text-text" data-resultante>—</dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                        @endforeach

                        <x-forms.textarea name="observacion" label="Observación (opcional)" :rows="2" placeholder="Se perdió una unidad durante el moldeado." />

                        <div class="space-y-1.5 text-sm">
                            <p class="flex items-center gap-2 text-success">
                                <x-ui.icon name="check-circle" class="h-4 w-4 shrink-0" />
                                <span>Los insumos ya fueron descontados al iniciar el lote.</span>
                            </p>
                            <p class="text-text-muted" data-resumen-final aria-live="polite">Escribe la cantidad obtenida para ver cuánto se agregará al stock de Productos.</p>
                        </div>

                        <div class="flex justify-end border-t border-text-muted/10 pt-4">
                            <x-ui.button type="submit" data-finalizar>Finalizar producción</x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            @else
                <x-ui.card title="Lote en proceso" subtitle="Falta registrar lo obtenido para finalizar este lote.">
                    <p class="text-sm text-text-muted">Los insumos ya fueron descontados al iniciar el lote.</p>
                </x-ui.card>
            @endif
        @else
            <x-ui.card title="Producción finalizada" subtitle="Lo obtenido ya está en el stock de Productos. Este registro no se puede modificar.">
                <div class="space-y-5">
                    @foreach ($detalle->productos as $producto)
                        @php($obtenido = $producto['cantidadObtenida'] ?? 0.0)
                        <div class="{{ ! $loop->first ? 'border-t border-text-muted/10 pt-5' : '' }}">
                            @if (count($detalle->productos) > 1)
                                <p class="mb-3 text-sm font-semibold text-text">{{ $producto['etiqueta'] }}</p>
                            @endif
                            <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                <div>
                                    <dt class="text-xs uppercase tracking-wide text-text-muted">Planificado</dt>
                                    <dd class="mt-1 text-sm text-text">{{ $cantidad($producto['cantidadPlanificada'], $producto['unidadMedida']) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs uppercase tracking-wide text-text-muted">Obtenido</dt>
                                    <dd class="mt-1 text-sm font-semibold text-text">{{ $cantidad($obtenido, $producto['unidadMedida']) }}</dd>
                                </div>
                                <div>
                                    @if ($producto['excedente'] > 0)
                                        <dt class="text-xs uppercase tracking-wide text-text-muted">Excedente</dt>
                                        <dd class="mt-1 text-sm text-text">{{ $cantidad($producto['excedente'], $producto['unidadMedida']) }}</dd>
                                    @else
                                        <dt class="text-xs uppercase tracking-wide text-text-muted">Merma</dt>
                                        <dd class="mt-1 text-sm {{ ($producto['merma'] ?? 0) > 0 ? 'text-danger' : 'text-text' }}">{{ $cantidad($producto['merma'] ?? 0, $producto['unidadMedida']) }}</dd>
                                    @endif
                                </div>
                                <div>
                                    <dt class="text-xs uppercase tracking-wide text-text-muted">Stock incorporado</dt>
                                    <dd class="mt-1 text-sm font-semibold text-success">+{{ $cantidad($obtenido, $producto['unidadMedida']) }}</dd>
                                </div>
                            </dl>
                        </div>
                    @endforeach

                    @if ($lote->observacionCierre())
                        <p class="border-t border-text-muted/10 pt-4 text-sm text-text-muted"><span class="font-medium text-text">Observación al finalizar:</span> {{ $lote->observacionCierre() }}</p>
                    @endif
                </div>
            </x-ui.card>
        @endif
    </div>

    {{-- Lo que se planificó y lo que ya se descontó (fotografía histórica). --}}
    <div class="space-y-4 lg:col-span-2">
        <x-ui.card title="Producción planificada">
            <ul class="divide-y divide-text-muted/10">
                @foreach ($detalle->productos as $producto)
                    <li class="flex items-baseline justify-between gap-3 py-2 first:pt-0 last:pb-0">
                        <span class="text-sm text-text">{{ $producto['etiqueta'] }}</span>
                        <span class="whitespace-nowrap text-sm font-medium text-text">{{ $cantidad($producto['cantidadPlanificada'], $producto['unidadMedida']) }}</span>
                    </li>
                @endforeach
            </ul>

            <details class="mt-4 text-xs text-text-muted">
                <summary class="cursor-pointer select-none">Detalle técnico</summary>
                <ul class="mt-2 space-y-1">
                    @foreach ($detalle->productos as $producto)
                        <li>{{ $producto['productoNombre'] }}: {{ $producto['recetaVersion'] ? 'receta versión '.$producto['recetaVersion'] : 'sin receta (lote histórico)' }}</li>
                    @endforeach
                </ul>
            </details>
        </x-ui.card>

        <x-ui.card title="Insumos consumidos">
            @if (count($detalle->insumosUtilizados) === 0)
                <p class="text-sm text-text-muted">Este lote no registró consumo de insumos.</p>
            @else
                <ul class="divide-y divide-text-muted/10">
                    @foreach ($detalle->insumosUtilizados as $insumo)
                        <li class="flex items-baseline justify-between gap-3 py-2 first:pt-0 last:pb-0">
                            <span class="inline-flex items-center gap-1.5 text-sm text-text">
                                @if ($insumo['esLeche'])
                                    <x-ui.icon name="droplet" class="h-4 w-4 text-info" />
                                @endif
                                {{ $insumo['insumoNombre'] }}
                            </span>
                            <span class="whitespace-nowrap text-sm font-medium text-text">{{ $fmt($insumo['cantidadUsada']) }} {{ $insumo['unidadBase'] }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-xs text-text-muted">Estos insumos ya fueron descontados al iniciar el lote.</p>
            @endif
        </x-ui.card>
    </div>
</div>
