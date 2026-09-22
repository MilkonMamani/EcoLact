{{--
    Hoja de liquidación individual (sobre de pago). Recibe:
    - $liquidacion: App\Domain\Pago\Liquidacion
    - $detalle: App\Application\Liquidacion\LiquidacionDetalleData
    - $periodo: App\Infrastructure\Persistence\Models\PeriodoLiquidacionModel|null
--}}
<div class="hoja-liquidacion mb-8 rounded-xl border border-text-muted/10 bg-surface p-6 print:mb-0 print:rounded-none print:border-0 print:p-8 print:shadow-none">
    <div class="flex items-start justify-between border-b border-text-muted/20 pb-3">
        <div>
            <p class="text-xs uppercase tracking-wide text-text-muted">EcoLact · Liquidación de pago semanal</p>
            <h1 class="text-lg font-semibold text-text">Liquidación #{{ $liquidacion->id() }}</h1>
        </div>
        <div class="text-right text-xs text-text-muted">
            <p>Emitido: {{ now()->format('d/m/Y H:i') }}</p>
            @if ($liquidacion->tuvoIncidencia())
                <p class="mt-1 font-medium text-warning">⚠ Con incidencia de calidad</p>
            @endif
        </div>
    </div>

    {{-- Mitad superior: resumen de la semana --}}
    <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
        <div class="col-span-2">
            <dt class="text-xs uppercase tracking-wide text-text-muted">Proveedor</dt>
            <dd class="text-base font-semibold text-text">{{ $detalle->proveedorNombre }} <span class="font-normal text-text-muted">— DNI {{ $detalle->proveedorDni }}</span></dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-text-muted">Periodo</dt>
            <dd class="text-text">
                @if ($periodo)
                    {{ $periodo->fecha_inicio->format('d/m/Y') }} (jueves) – {{ $periodo->fecha_fin->format('d/m/Y') }} (miércoles)
                @else
                    —
                @endif
            </dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-text-muted">Fecha de pago</dt>
            <dd class="text-text">Viernes</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-text-muted">Litros entregados</dt>
            <dd class="text-text">{{ number_format($liquidacion->totalLitrosEntregados(), 2) }} L</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-text-muted">Litros pagables</dt>
            <dd class="text-text">{{ number_format($liquidacion->totalLitrosPagables(), 2) }} L</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-text-muted">Precio por litro aplicado</dt>
            <dd class="text-text">S/ {{ number_format($liquidacion->precioLitroAplicado(), 4) }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-text-muted">Incidencia de calidad</dt>
            <dd class="text-text">{{ $liquidacion->tuvoIncidencia() ? 'Sí (tarifa reducida)' : 'No' }}</dd>
        </div>
    </dl>

    <div class="mt-4 rounded-lg border border-text-muted/15 p-3">
        <dl class="space-y-1.5 text-sm">
            <div class="flex justify-between"><dt class="text-text-muted">Subtotal leche (litros pagables × precio)</dt><dd class="text-text">S/ {{ number_format($liquidacion->subtotal(), 2) }}</dd></div>
            <div class="flex justify-between"><dt class="text-text-muted">Ajustes (compras a cuenta, sanciones, etc.)</dt><dd class="text-text">S/ {{ number_format($liquidacion->totalAjustes(), 2) }}</dd></div>
        </dl>
        <div class="mt-3 flex items-center justify-between rounded-lg bg-primary-soft px-4 py-3">
            <span class="text-sm font-semibold text-primary-dark">TOTAL A PAGAR EN EFECTIVO</span>
            <span class="text-2xl font-bold text-primary-dark">S/ {{ number_format($liquidacion->totalPagar(), 2) }}</span>
        </div>
    </div>

    {{-- Mitad inferior: detalle línea por línea --}}
    <div class="mt-4">
        <h2 class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-text-muted">Entregas del periodo</h2>
        @if (count($detalle->entregas) === 0)
            <p class="text-xs text-text-muted">Sin entregas registradas en el periodo.</p>
        @else
            <table class="w-full border-collapse text-xs">
                <thead>
                    <tr class="border-b border-text-muted/20 text-left uppercase tracking-wide text-text-muted">
                        <th class="py-1 pr-2">Fecha</th>
                        <th class="py-1 pr-2">Litros</th>
                        <th class="py-1 pr-2">Resultado</th>
                        <th class="py-1 pr-2">Pagables</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($detalle->entregas as $fila)
                        <tr class="border-b border-text-muted/10">
                            <td class="py-1 pr-2 text-text">{{ $fila['fecha'] }}</td>
                            <td class="py-1 pr-2 text-text">{{ number_format($fila['litros'], 2) }} L</td>
                            <td class="py-1 pr-2 text-text">{{ $fila['resultado'] ?? 'No analizada' }}</td>
                            <td class="py-1 pr-2 text-text">{{ number_format($fila['pagables'], 2) }} L</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if (count($detalle->ajustes) > 0)
        <div class="mt-3">
            <h2 class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-text-muted">Ajustes descontados</h2>
            <table class="w-full border-collapse text-xs">
                <thead>
                    <tr class="border-b border-text-muted/20 text-left uppercase tracking-wide text-text-muted">
                        <th class="py-1 pr-2">Descripción</th>
                        <th class="py-1 pr-2">Tipo</th>
                        <th class="py-1 pr-2 text-right">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($detalle->ajustes as $ajuste)
                        <tr class="border-b border-text-muted/10">
                            <td class="py-1 pr-2 text-text">{{ $ajuste->descripcion() }}</td>
                            <td class="py-1 pr-2 text-text-muted">{{ $ajuste->tipo() }}</td>
                            <td class="py-1 pr-2 text-right text-text">S/ {{ number_format($ajuste->monto(), 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <p class="mt-3 text-xs text-text-muted">El pago es siempre en efectivo, entregado en sobre cerrado.</p>

    {{-- Espacio de conformidad --}}
    <div class="mt-8 grid grid-cols-2 gap-8 text-sm">
        <div>
            <div class="border-t border-text pt-1">Firma del proveedor</div>
        </div>
        <div>
            <div class="border-t border-text pt-1">DNI y fecha de recepción</div>
        </div>
    </div>
</div>
