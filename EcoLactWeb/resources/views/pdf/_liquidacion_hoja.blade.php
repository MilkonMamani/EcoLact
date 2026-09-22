{{--
    Hoja de liquidación individual (sobre de pago), en HTML/CSS compatible
    con dompdf (sin flexbox/grid, todo con tablas). Recibe:
    - $liquidacion: App\Domain\Pago\Liquidacion
    - $detalle: App\Application\Liquidacion\LiquidacionDetalleData
    - $periodo: App\Infrastructure\Persistence\Models\PeriodoLiquidacionModel|null
    - $ultimaHoja: bool (para no insertar salto de página después de la última)
--}}
<div style="{{ ($ultimaHoja ?? true) ? '' : 'page-break-after: always;' }} padding: 24px; font-family: DejaVu Sans, sans-serif; color: #1a1a1a;">
    <table style="width: 100%; border-collapse: collapse; border-bottom: 1px solid #ccc; padding-bottom: 8px;">
        <tr>
            <td style="text-align: left; vertical-align: top;">
                <div style="font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; color: #666;">EcoLact &middot; Liquidación de pago semanal</div>
                <div style="font-size: 16px; font-weight: bold;">Liquidación #{{ $liquidacion->id() }}</div>
            </td>
            <td style="text-align: right; vertical-align: top; font-size: 9px; color: #666;">
                <div>Emitido: {{ now()->format('d/m/Y H:i') }}</div>
                @if ($liquidacion->tuvoIncidencia())
                    <div style="margin-top: 2px; font-weight: bold; color: #b45309;">Con incidencia de calidad</div>
                @endif
            </td>
        </tr>
    </table>

    <table style="width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 11px;">
        <tr>
            <td colspan="2" style="padding-bottom: 6px;">
                <div style="font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; color: #666;">Proveedor</div>
                <div style="font-size: 13px; font-weight: bold;">{{ $detalle->proveedorNombre }} <span style="font-weight: normal; color: #666;">&mdash; DNI {{ $detalle->proveedorDni }}</span></div>
            </td>
        </tr>
        <tr>
            <td style="width: 50%; padding: 4px 8px 4px 0;">
                <div style="font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; color: #666;">Periodo</div>
                <div>
                    @if ($periodo)
                        {{ $periodo->fecha_inicio->format('d/m/Y') }} (jueves) &ndash; {{ $periodo->fecha_fin->format('d/m/Y') }} (miércoles)
                    @else
                        &mdash;
                    @endif
                </div>
            </td>
            <td style="width: 50%; padding: 4px 0 4px 8px;">
                <div style="font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; color: #666;">Fecha de pago</div>
                <div>Viernes</div>
            </td>
        </tr>
        <tr>
            <td style="padding: 4px 8px 4px 0;">
                <div style="font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; color: #666;">Litros entregados</div>
                <div>{{ number_format($liquidacion->totalLitrosEntregados(), 2) }} L</div>
            </td>
            <td style="padding: 4px 0 4px 8px;">
                <div style="font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; color: #666;">Litros pagables</div>
                <div>{{ number_format($liquidacion->totalLitrosPagables(), 2) }} L</div>
            </td>
        </tr>
        <tr>
            <td style="padding: 4px 8px 4px 0;">
                <div style="font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; color: #666;">Precio por litro aplicado</div>
                <div>S/ {{ number_format($liquidacion->precioLitroAplicado(), 4) }}</div>
            </td>
            <td style="padding: 4px 0 4px 8px;">
                <div style="font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; color: #666;">Incidencia de calidad</div>
                <div>{{ $liquidacion->tuvoIncidencia() ? 'Sí (tarifa reducida)' : 'No' }}</div>
            </td>
        </tr>
    </table>

    <table style="width: 100%; border-collapse: collapse; margin-top: 10px; border: 1px solid #ddd;">
        <tr>
            <td style="padding: 8px 10px; font-size: 11px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="color: #666;">Subtotal leche (litros pagables &times; precio)</td>
                        <td style="text-align: right;">S/ {{ number_format($liquidacion->subtotal(), 2) }}</td>
                    </tr>
                    <tr>
                        <td style="color: #666;">Ajustes (compras a cuenta, sanciones, etc.)</td>
                        <td style="text-align: right;">S/ {{ number_format($liquidacion->totalAjustes(), 2) }}</td>
                    </tr>
                </table>
                <table style="width: 100%; border-collapse: collapse; margin-top: 8px; background-color: #eef6f1;">
                    <tr>
                        <td style="padding: 8px 10px; font-weight: bold; font-size: 12px;">TOTAL A PAGAR EN EFECTIVO</td>
                        <td style="padding: 8px 10px; text-align: right; font-weight: bold; font-size: 18px;">S/ {{ number_format($liquidacion->totalPagar(), 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div style="margin-top: 12px;">
        <div style="font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; color: #666; margin-bottom: 4px;">Entregas del periodo</div>
        @if (count($detalle->entregas) === 0)
            <div style="font-size: 9px; color: #666;">Sin entregas registradas en el periodo.</div>
        @else
            <table style="width: 100%; border-collapse: collapse; font-size: 9px;">
                <thead>
                    <tr style="border-bottom: 1px solid #ccc; text-align: left; text-transform: uppercase; color: #666;">
                        <th style="padding: 3px 6px 3px 0;">Fecha</th>
                        <th style="padding: 3px 6px;">Litros</th>
                        <th style="padding: 3px 6px;">Resultado</th>
                        <th style="padding: 3px 6px;">Pagables</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($detalle->entregas as $fila)
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 3px 6px 3px 0;">{{ $fila['fecha'] }}</td>
                            <td style="padding: 3px 6px;">{{ number_format($fila['litros'], 2) }} L</td>
                            <td style="padding: 3px 6px;">{{ $fila['resultado'] ?? 'No analizada' }}</td>
                            <td style="padding: 3px 6px;">{{ number_format($fila['pagables'], 2) }} L</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if (count($detalle->ajustes) > 0)
        <div style="margin-top: 10px;">
            <div style="font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; color: #666; margin-bottom: 4px;">Ajustes descontados</div>
            <table style="width: 100%; border-collapse: collapse; font-size: 9px;">
                <thead>
                    <tr style="border-bottom: 1px solid #ccc; text-align: left; text-transform: uppercase; color: #666;">
                        <th style="padding: 3px 6px 3px 0;">Descripción</th>
                        <th style="padding: 3px 6px;">Tipo</th>
                        <th style="padding: 3px 6px; text-align: right;">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($detalle->ajustes as $ajuste)
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 3px 6px 3px 0;">{{ $ajuste->descripcion() }}</td>
                            <td style="padding: 3px 6px; color: #666;">{{ $ajuste->tipo() }}</td>
                            <td style="padding: 3px 6px; text-align: right;">S/ {{ number_format($ajuste->monto(), 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div style="margin-top: 10px; font-size: 9px; color: #666;">El pago es siempre en efectivo, entregado en sobre cerrado.</div>

    <table style="width: 100%; border-collapse: collapse; margin-top: 30px; font-size: 11px;">
        <tr>
            <td style="width: 50%; padding-right: 16px; border-top: 1px solid #1a1a1a; padding-top: 4px;">Firma del proveedor</td>
            <td style="width: 50%; padding-left: 16px; border-top: 1px solid #1a1a1a; padding-top: 4px;">DNI y fecha de recepción</td>
        </tr>
    </table>
</div>
