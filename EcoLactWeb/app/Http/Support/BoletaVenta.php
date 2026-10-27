<?php

namespace App\Http\Support;

use App\Domain\Produccion\ProductoRepository;
use App\Domain\Ventas\VentaProducto;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Boleta de venta (comprobante interno, sin datos tributarios). No tiene tabla
 * propia: se deriva de la fila de ventas_producto ya registrada, con el precio,
 * el importe, el tipo de cliente y la forma de pago guardados al vender (nunca
 * el precio vigente). Web y móvil descargan el MISMO PDF.
 */
final class BoletaVenta
{
    public const DOCUMENTO_PUBLICO = 'XXXXXXXX';

    /**
     * @return array{codigo: string, fecha: string, hora: string, cliente: string, etiquetaDocumento: string, documento: string, producto: string, cantidad: string, precioUnitario: float, total: float, formaPago: string, descuento: bool}
     */
    public static function datos(VentaProductoModel $venta): array
    {
        $venta->loadMissing(['proveedor', 'comprador']);
        $fecha = $venta->fecha->timezone(config('app.zona_horaria_negocio'));
        $descuento = $venta->forma_pago === 'DESCUENTO_PLANILLA';

        if ($venta->proveedor !== null) {
            $cliente = trim($venta->proveedor->nombres.' '.$venta->proveedor->apellidos);
            $documento = $venta->proveedor->dni;
            $etiqueta = 'DNI';
        } elseif ($venta->comprador !== null) {
            $cliente = $venta->comprador->nombre;
            $documento = $venta->comprador->documento ?: '—';
            $etiqueta = match (strlen((string) $venta->comprador->documento)) {
                8 => 'DNI',
                11 => 'RUC',
                default => 'Documento',
            };
        } else {
            $cliente = 'Público general';
            $documento = self::DOCUMENTO_PUBLICO;
            $etiqueta = 'Documento';
        }

        return [
            'codigo' => VentaProducto::codigo($venta->id),
            'fecha' => $fecha->format('d/m/Y'),
            'hora' => $fecha->format('H:i'),
            'cliente' => $cliente,
            'etiquetaDocumento' => $etiqueta,
            'documento' => $documento,
            'producto' => app(ProductoRepository::class)->buscarPorId($venta->producto_id)?->etiqueta() ?? '—',
            'cantidad' => PantallaVentas::cantidad((float) $venta->cantidad),
            'precioUnitario' => (float) $venta->precio_unitario,
            'total' => (float) $venta->importe_total,
            'formaPago' => $descuento ? 'Descuento del pago semanal' : 'Efectivo',
            'descuento' => $descuento,
        ];
    }

    public static function nombreArchivo(VentaProductoModel $venta): string
    {
        return 'EcoLact_Venta_'.VentaProducto::codigo($venta->id).'.pdf';
    }

    /**
     * Solo una venta REGISTRADA tiene boleta definitiva. Quien llama ya
     * autorizó con VentaProductoPolicy::view.
     */
    public static function pdf(VentaProductoModel $venta): Response
    {
        abort_unless($venta->estado === 'REGISTRADA', 404, 'La venta no tiene boleta disponible.');

        return Pdf::loadView('pdf.boleta-venta', ['boleta' => self::datos($venta)])
            ->setPaper('a5')
            ->download(self::nombreArchivo($venta));
    }
}
