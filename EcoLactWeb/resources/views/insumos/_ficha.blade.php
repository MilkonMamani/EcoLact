{{--
    Ficha de un insumo: datos, stock y últimos movimientos.
    Variables: $insumo (Insumo), $movimientos (InsumoMovimientoModel[]), $volver (URL), $historialUrl (URL|null).
--}}
@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
@endphp

<x-ui.button :href="$volver" variant="ghost" size="sm" as="a">&larr; Insumos</x-ui.button>

<x-ui.card :title="$insumo->nombre()" subtitle="Detalle e inventario del insumo.">
    <x-slot:actions>
        <x-ui.badge :status="$insumo->estado()" />
    </x-slot:actions>

    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <dt class="text-xs uppercase tracking-wide text-text-muted">Tipo</dt>
            <dd class="mt-1 text-sm text-text">{{ $insumo->tipo()->etiqueta() }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-text-muted">Unidad base</dt>
            <dd class="mt-1 text-sm text-text">{{ \App\Domain\Produccion\UnidadMedida::etiqueta($insumo->unidadBase()) }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-text-muted">Stock actual</dt>
            <dd class="mt-1 text-sm font-semibold text-text">{{ $fmt($insumo->stockActual()) }} {{ $insumo->unidadBase() }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-text-muted">Stock mínimo</dt>
            <dd class="mt-1 text-sm text-text">{{ $fmt($insumo->stockMinimo()) }} {{ $insumo->unidadBase() }}</dd>
        </div>
    </dl>

    @if ($insumo->descripcion())
        <p class="mt-4 text-sm text-text-muted">{{ $insumo->descripcion() }}</p>
    @endif

    @if ($insumo->esLeche())
        <p class="mt-4 rounded-lg bg-info/10 p-3 text-sm text-info">La leche es un insumo del sistema: su stock aumenta automáticamente con cada entrega de los proveedores y baja al iniciar una producción.</p>
    @endif

    @if ($insumo->stockMinimo() > 0 && $insumo->estaBajoMinimo())
        <p class="mt-4 rounded-lg bg-warning/10 p-3 text-sm text-warning">⚠ Este insumo está por debajo de su stock mínimo.</p>
    @endif
</x-ui.card>

{{ $slot ?? '' }}

<x-ui.card title="Últimos movimientos">
    @if ($historialUrl)
        <x-slot:actions>
            <x-ui.button :href="$historialUrl" variant="ghost" size="sm" as="a">Ver historial completo →</x-ui.button>
        </x-slot:actions>
    @endif

    @if (count($movimientos) === 0)
        <x-ui.empty-state title="Sin movimientos registrados" :icon="false" />
    @else
        <x-tables.table>
            <thead>
                <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                    <th class="py-2 pr-4">Fecha</th>
                    <th class="py-2 pr-4">Motivo</th>
                    <th class="py-2 pr-4">Cantidad</th>
                    <th class="py-2 pr-4">Origen</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($movimientos as $movimiento)
                    @php($entrada = $movimiento->tipo === 'ENTRADA')
                    <tr class="border-b border-text-muted/5 last:border-0">
                        <td class="py-2 pr-4 text-text">{{ $movimiento->fecha->format('d/m/Y H:i') }}</td>
                        <td class="py-2 pr-4 text-text-muted">{{ \App\Domain\Shared\Enums\MotivoMovimientoInsumo::tryFrom($movimiento->motivo)?->etiqueta() ?? $movimiento->motivo }}</td>
                        <td class="py-2 pr-4 font-semibold {{ $entrada ? 'text-success' : 'text-danger' }}">{{ $entrada ? '+' : '−' }}{{ $fmt($movimiento->cantidad) }} {{ $insumo->unidadBase() }}</td>
                        <td class="py-2 pr-4 text-text-muted">
                            @if ($movimiento->origen_tipo === 'ENTREGA')
                                Entrega #{{ $movimiento->origen_id }}
                            @elseif ($movimiento->lote_produccion_id)
                                Lote #{{ $movimiento->lote_produccion_id }}
                            @else
                                {{ $movimiento->observacion ?? '—' }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-tables.table>
    @endif
</x-ui.card>
