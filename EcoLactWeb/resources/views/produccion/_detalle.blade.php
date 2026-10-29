{{--
    Detalle de un lote (ADMINISTRADOR y ENCARGADO_PLANTA) en UNA superficie con secciones, no en cards:
    cabecera (qué, cuánto, cuándo, leche), insumos consumidos (fotografía histórica: no cambia si la receta
    cambia después) y, según el estado, el formulario para finalizar o el resumen inmutable del resultado.
    Finalizar NO vuelve a descontar insumos y es lo único que suma el producto terminado al inventario.
    La versión de receta usada se conserva en BD para el histórico, pero no se muestra.
    Variables: $detalle (LoteProduccionDetalleData), $prefijo ('admin' | 'planta'), $puedeFinalizar (bool).
--}}
@php
    $lote = $detalle->lote;
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
    $cantidad = fn ($v, $u) => \App\Application\Produccion\LoteProduccionDetalleData::cantidad((float) $v, $u);
    $unico = count($detalle->productos) === 1 ? $detalle->productos[0] : null;
    $rotulo = 'text-xs font-medium uppercase tracking-wide text-text-muted';
@endphp

<x-ui.button :href="route($prefijo.'.produccion.index')" variant="ghost" size="sm" as="a">
    <x-ui.icon name="arrow-left" class="h-4 w-4" /> Volver a producción
</x-ui.button>

<article class="overflow-hidden rounded-2xl border border-text-muted/10 bg-surface">
    {{-- Cabecera: qué lote es, qué se fabrica, cuánto y cuándo empezó. --}}
    <header class="p-5 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="font-mono text-sm font-medium text-text-muted">{{ $lote->codigo() }}</p>
            <x-ui.badge :status="$lote->estado()" />
        </div>
        <h1 class="mt-2 text-2xl font-bold text-text">{{ $detalle->titulo() }}</h1>

        <dl class="mt-5 grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-4">
            <div>
                <dt class="{{ $rotulo }}">Inicio</dt>
                <dd class="mt-1 text-sm font-semibold text-text">{{ $lote->fechaHoraInicio()->format('d/m/Y · H:i') }}</dd>
            </div>
            <div>
                <dt class="{{ $rotulo }}">Cantidad planificada</dt>
                @if ($unico)
                    <dd class="mt-1 text-sm font-semibold text-text">{{ $cantidad($unico['cantidadPlanificada'], $unico['unidadMedida']) }}</dd>
                @else
                    @foreach ($detalle->productos as $producto)
                        <dd class="mt-1 text-sm text-text"><span class="font-semibold">{{ $cantidad($producto['cantidadPlanificada'], $producto['unidadMedida']) }}</span> · {{ $producto['etiqueta'] }}</dd>
                    @endforeach
                @endif
            </div>
            <div>
                <dt class="{{ $rotulo }}">Leche utilizada</dt>
                <dd class="mt-1 inline-flex items-center gap-1.5 text-sm font-semibold text-text">
                    <x-ui.icon name="droplet" class="h-4 w-4 text-info" /> {{ $fmt($detalle->lecheUtilizada) }} L
                </dd>
            </div>
            @if (! $detalle->estaEnProceso())
                <div>
                    <dt class="{{ $rotulo }}">Finalizado</dt>
                    <dd class="mt-1 text-sm font-semibold text-text">{{ $lote->fechaHoraFin()?->format('d/m/Y · H:i') ?? '—' }}</dd>
                </div>
            @endif
        </dl>

        @if ($lote->observacion())
            <p class="mt-4 text-sm text-text-muted"><span class="font-medium text-text">Observación al iniciar:</span> {{ $lote->observacion() }}</p>
        @endif

        {{-- Lotes iniciados en la misma planificación: cada uno se finaliza por separado. --}}
        @if ($detalle->planificacion)
            <div class="mt-5 rounded-xl border border-text-muted/10 bg-primary-soft/20 px-4 py-3">
                <p class="{{ $rotulo }}">Planificación {{ $detalle->planificacion['codigo'] }} · {{ count($detalle->planificacion['lotes']) }} lotes</p>
                <ul class="mt-2 flex flex-wrap gap-2">
                    @foreach ($detalle->planificacion['lotes'] as $hermano)
                        <li>
                            @if ($hermano['actual'])
                                <span class="inline-flex items-center gap-2 rounded-lg border border-primary/40 bg-surface px-3 py-1.5 text-xs font-semibold text-text" aria-current="page">
                                    <span class="font-mono">{{ $hermano['codigo'] }}</span> {{ $hermano['producto'] }}
                                </span>
                            @else
                                <a href="{{ route($prefijo.'.produccion.show', $hermano['id']) }}" class="inline-flex items-center gap-2 rounded-lg border border-text-muted/15 bg-surface px-3 py-1.5 text-xs text-text hover:border-primary/40">
                                    <span class="font-mono text-text-muted">{{ $hermano['codigo'] }}</span> {{ $hermano['producto'] }}
                                    <span class="{{ $hermano['estado'] === 'FINALIZADO' ? 'text-success' : 'text-warning' }}">{{ $hermano['estado'] === 'FINALIZADO' ? 'Finalizado' : 'En proceso' }}</span>
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </header>

    <div class="grid border-t border-text-muted/10 lg:grid-cols-5">
        {{-- Acción principal: finalizar (en proceso) o el resultado (finalizado). --}}
        <section class="p-5 sm:p-6 lg:col-span-3">
            @if ($detalle->estaEnProceso() && $puedeFinalizar)
                <h2 class="text-base font-semibold text-text">Finalizar producción</h2>
                <p class="mt-1 text-sm text-text-muted">Registra cuánto se obtuvo realmente.</p>

                <form
                    method="POST"
                    action="{{ route($prefijo.'.produccion.finalizar', $lote->id()) }}"
                    class="mt-5 space-y-6"
                    data-produccion-finalizar
                    data-confirm="¿Finalizar la producción {{ $lote->codigo() }}? Lo obtenido se sumará al stock de Productos y el lote ya no se podrá modificar."
                >
                    @csrf

                    @foreach ($detalle->productos as $producto)
                        @php($campo = 'obtenido.'.$producto['detalleId'])
                        <div
                            class="space-y-4 {{ ! $loop->first ? 'border-t border-text-muted/10 pt-6' : '' }}"
                            data-producto-final
                            data-planificado="{{ $producto['cantidadPlanificada'] }}"
                            data-stock="{{ $producto['stockActual'] }}"
                            data-unidad="{{ $producto['unidadMedida'] }}"
                            data-etiqueta="{{ $producto['etiqueta'] }}"
                        >
                            <p class="text-sm font-semibold text-text">{{ $producto['etiqueta'] }}</p>

                            {{-- Planificado | obtenido | merma, en una sola fila. --}}
                            <div class="grid gap-4 sm:grid-cols-3 sm:items-start">
                                <div>
                                    <p class="{{ $rotulo }}">Planificado</p>
                                    <p class="mt-2 text-lg font-semibold text-text">{{ $cantidad($producto['cantidadPlanificada'], $producto['unidadMedida']) }}</p>
                                </div>
                                <div>
                                    <label for="obtenido_{{ $producto['detalleId'] }}" class="block {{ $rotulo }}">Cantidad obtenida</label>
                                    <input
                                        id="obtenido_{{ $producto['detalleId'] }}"
                                        name="obtenido[{{ $producto['detalleId'] }}]"
                                        type="number"
                                        step="any"
                                        min="0"
                                        inputmode="decimal"
                                        required
                                        value="{{ old($campo) }}"
                                        placeholder="Ej. {{ $fmt($producto['cantidadPlanificada']) }}"
                                        data-obtenido
                                        class="mt-1 w-full rounded-lg border {{ $errors->has($campo) ? 'border-danger' : 'border-text-muted/25' }} bg-surface px-3 py-2 text-lg font-semibold text-text shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                                    />
                                    <x-forms.error :name="$campo" />
                                </div>
                                <div aria-live="polite">
                                    <p class="{{ $rotulo }}" data-diferencia-etiqueta>Merma</p>
                                    <p class="mt-2 text-lg font-semibold text-text-muted" data-diferencia>—</p>
                                </div>
                            </div>

                            {{-- Vista previa: el stock real recién cambia al confirmar "Finalizar producción". --}}
                            <div class="rounded-xl border border-text-muted/15">
                                <p class="border-b border-text-muted/10 px-4 py-2 {{ $rotulo }}">Impacto en inventario</p>
                                <dl class="grid grid-cols-3 divide-x divide-text-muted/10 text-center">
                                    <div class="px-2 py-3">
                                        <dt class="text-xs text-text-muted">Stock actual</dt>
                                        <dd class="mt-1 text-base font-semibold text-text">{{ $cantidad($producto['stockActual'], $producto['unidadMedida']) }}</dd>
                                    </div>
                                    <div class="px-2 py-3">
                                        <dt class="text-xs text-text-muted">Producción obtenida</dt>
                                        <dd class="mt-1 text-base font-semibold text-success" data-incremento>—</dd>
                                    </div>
                                    <div class="bg-success-soft/60 px-2 py-3">
                                        <dt class="text-xs text-text-muted">Stock resultante</dt>
                                        <dd class="mt-1 text-base font-bold text-text" data-resultante>—</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    @endforeach

                    <x-forms.textarea name="observacion" label="Observación (opcional)" :rows="2" placeholder="Se perdió una unidad durante el moldeado." />

                    <div class="flex flex-col gap-4 border-t border-text-muted/10 pt-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="space-y-1 text-sm">
                            <p class="flex items-center gap-1.5 text-success">
                                <x-ui.icon name="check-circle" class="h-4 w-4 shrink-0" />
                                Los insumos ya fueron descontados al iniciar el lote.
                            </p>
                            <p class="text-text-muted" data-resumen-final aria-live="polite">Escribe la cantidad obtenida para ver cuánto se agregará al stock de Productos.</p>
                        </div>
                        <x-ui.button type="submit" class="shrink-0" data-finalizar>Finalizar producción</x-ui.button>
                    </div>
                </form>
            @elseif ($detalle->estaEnProceso())
                <h2 class="text-base font-semibold text-text">Lote en proceso</h2>
                <p class="mt-1 text-sm text-text-muted">Falta registrar lo obtenido para finalizar este lote.</p>
            @else
                <h2 class="flex items-center gap-2 text-base font-semibold text-text">
                    Producción finalizada <x-ui.icon name="check-circle" class="h-5 w-5 text-success" />
                </h2>
                <p class="mt-1 text-sm text-text-muted">Lo obtenido ya está en el stock de Productos. Este registro no se puede modificar.</p>

                <div class="mt-5 space-y-5">
                    @foreach ($detalle->productos as $producto)
                        @php($obtenido = $producto['cantidadObtenida'] ?? 0.0)
                        <div class="{{ ! $loop->first ? 'border-t border-text-muted/10 pt-5' : '' }}">
                            <p class="text-sm font-semibold text-text">{{ $producto['etiqueta'] }}</p>
                            <dl class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-4">
                                <div>
                                    <dt class="{{ $rotulo }}">Planificado</dt>
                                    <dd class="mt-1 text-base font-semibold text-text">{{ $cantidad($producto['cantidadPlanificada'], $producto['unidadMedida']) }}</dd>
                                </div>
                                <div>
                                    <dt class="{{ $rotulo }}">Obtenido</dt>
                                    <dd class="mt-1 text-base font-semibold text-text">{{ $cantidad($obtenido, $producto['unidadMedida']) }}</dd>
                                </div>
                                <div>
                                    @if ($producto['excedente'] > 0)
                                        <dt class="{{ $rotulo }}">Excedente</dt>
                                        <dd class="mt-1 text-base font-semibold text-text">{{ $cantidad($producto['excedente'], $producto['unidadMedida']) }}</dd>
                                    @else
                                        <dt class="{{ $rotulo }}">Merma</dt>
                                        <dd class="mt-1 text-base font-semibold {{ ($producto['merma'] ?? 0) > 0 ? 'text-danger' : 'text-text' }}">{{ $cantidad($producto['merma'] ?? 0, $producto['unidadMedida']) }}</dd>
                                    @endif
                                </div>
                                <div>
                                    <dt class="{{ $rotulo }}">Stock incorporado</dt>
                                    <dd class="mt-1 text-base font-semibold text-success">+{{ $cantidad($obtenido, $producto['unidadMedida']) }}</dd>
                                </div>
                            </dl>
                        </div>
                    @endforeach

                    @if ($lote->observacionCierre())
                        <p class="border-t border-text-muted/10 pt-4 text-sm text-text-muted"><span class="font-medium text-text">Observación al finalizar:</span> {{ $lote->observacionCierre() }}</p>
                    @endif
                </div>
            @endif
        </section>

        {{-- Lo que ya se descontó al iniciar (fotografía histórica del lote). --}}
        <section class="border-t border-text-muted/10 p-5 sm:p-6 lg:col-span-2 lg:border-l lg:border-t-0">
            <h2 class="{{ $rotulo }}">Insumos consumidos</h2>

            @if (count($detalle->insumosUtilizados) === 0)
                <p class="mt-3 text-sm text-text-muted">Este lote no registró consumo de insumos.</p>
            @else
                <ul class="mt-3 divide-y divide-text-muted/10">
                    @foreach ($detalle->insumosUtilizados as $insumo)
                        <li class="flex items-baseline justify-between gap-3 py-2.5">
                            <span class="inline-flex items-center gap-1.5 text-sm text-text">
                                @if ($insumo['esLeche'])
                                    <x-ui.icon name="droplet" class="h-4 w-4 text-info" />
                                @endif
                                {{ $insumo['insumoNombre'] }}
                            </span>
                            <span class="whitespace-nowrap text-sm font-semibold text-text">{{ $fmt($insumo['cantidadUsada']) }} {{ $insumo['unidadBase'] }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 flex items-center gap-1.5 text-xs text-success">
                    <x-ui.icon name="check-circle" class="h-4 w-4 shrink-0" />
                    Estos insumos ya fueron descontados al iniciar el lote.
                </p>
            @endif
        </section>
    </div>
</article>
