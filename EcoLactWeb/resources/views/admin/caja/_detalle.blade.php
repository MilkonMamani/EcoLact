{{--
    Detalle de un movimiento de caja (fragmento pedido por fetch o incluido con ?movimiento=ID).
    Solo lectura: lo que muestra es la fotografía guardada al confirmarse la operación de origen.
--}}
@php
    /** @var \App\Application\Caja\MovimientoCajaFila $movimiento */
    $iconos = ['VENTA' => 'shopping-cart', 'PROVEEDOR' => 'milk-jar', 'TRABAJADOR' => 'user', 'INSUMOS' => 'package', 'GASTOS' => 'file-text', 'SALDO_INICIAL' => 'wallet'];
    $enlaceOrigen = match (true) {
        $movimiento->referenciaTipo === \App\Domain\Caja\MovimientoCaja::REFERENCIA_VENTA => ['Ver venta', route('admin.ventas.show', $movimiento->referenciaId)],
        $movimiento->compraId !== null => ['Ver compra de origen', route('admin.compras.show', $movimiento->compraId)],
        default => null,
    };
@endphp

<div class="caja-detalle">
    <div class="caja-detalle-cabecera">
        <p class="caja-detalle-kicker">Detalle del movimiento</p>
        <button type="button" class="caja-cerrar" data-caja-cerrar aria-label="Cerrar detalle">
            <x-ui.icon name="x-mark" class="h-5 w-5" />
        </button>
    </div>

    <div class="caja-detalle-resumen">
        <span @class(['caja-categoria-icono is-grande', 'is-'.strtolower($movimiento->categoria->value)]) aria-hidden="true"><x-ui.icon :name="$iconos[$movimiento->categoria->value]" class="h-6 w-6" /></span>
        <h2 class="caja-detalle-titulo">{{ $movimiento->concepto }}</h2>
        <p @class(['caja-detalle-monto', 'is-entrada' => $movimiento->esEntrada(), 'is-salida' => ! $movimiento->esEntrada()])>{{ $movimiento->montoConSigno() }}</p>
    </div>

    <dl class="caja-detalle-datos">
        <div><dt>Tipo</dt><dd>{{ $movimiento->tipo->etiqueta() }}</dd></div>
        <div><dt>Categoría</dt><dd>{{ $movimiento->categoria->etiqueta() }}</dd></div>
        @foreach ($movimiento->detalle as $etiqueta => $valor)
            <div><dt>{{ $etiqueta }}</dt><dd>{{ $valor }}</dd></div>
        @endforeach
        <div><dt>Registrado por</dt><dd>{{ $movimiento->registradoPor }}</dd></div>
        <div><dt>Fecha</dt><dd>{{ $movimiento->fecha->format('d/m/Y') }} · {{ $movimiento->fecha->format('H:i') }}</dd></div>
    </dl>

    @if ($movimiento->comprobanteId || $enlaceOrigen)
        <div class="caja-detalle-acciones">
            @if ($movimiento->comprobanteId)
                <a href="{{ route('admin.compras.comprobantes.show', [$movimiento->compraId, $movimiento->comprobanteId]) }}" target="_blank" rel="noopener" class="fichas-btn is-outline is-sm">
                    <x-ui.icon name="file-text" class="h-4 w-4" /> Ver comprobante
                </a>
            @endif
            @if ($enlaceOrigen)
                <a href="{{ $enlaceOrigen[1] }}" class="fichas-btn is-outline is-sm">
                    <x-ui.icon name="external-link" class="h-4 w-4" /> {{ $enlaceOrigen[0] }}
                </a>
            @endif
        </div>
    @endif

    <p class="caja-detalle-nota">Generado automáticamente desde {{ match ($movimiento->categoria->value) {
        'VENTA' => 'Ventas',
        'INSUMOS', 'GASTOS' => 'Compras y gastos',
        'SALDO_INICIAL' => 'la apertura de caja',
        default => 'Pagos',
    } }}. No se edita desde Caja.</p>
</div>
