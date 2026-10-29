<?php

namespace App\Application\Compra;

use App\Domain\Auth\Repositories\UsuarioRepository;
use App\Domain\Compra\CategoriaCompra;
use App\Domain\Compra\CategoriaCompraRepository;
use App\Domain\Compra\ComprobanteCompra;
use App\Domain\Compra\ComprobanteCompraRepository;
use App\Domain\Compra\PagoCompra;
use App\Domain\Compra\PagoCompraRepository;
use App\Domain\Produccion\CompraInsumo;
use App\Domain\Produccion\CompraInsumoDetalle;
use App\Domain\Produccion\CompraInsumoRepository;
use App\Domain\Produccion\InsumoRepository;

/**
 * Ficha de una compra para consultarla: cabecera, líneas (con su insumo y su
 * movimiento de stock), pagos (con su movimiento de caja) y comprobantes. Todo
 * se enlaza por ids guardados, nunca por descripciones. Es histórico: no se
 * recalcula con las presentaciones de hoy.
 *
 * @phpstan-type LineaFicha array{tratamiento: string, descripcion: string, insumo_id: ?int, insumo: ?string, presentacion: string, cantidad_base: ?float, unidad_base: ?string, precio_unitario: ?float, subtotal: ?float, movimiento_id: ?int}
 */
final class ObtenerDetalleCompra
{
    public function __construct(
        private readonly CompraInsumoRepository $compras,
        private readonly InsumoRepository $insumos,
        private readonly CategoriaCompraRepository $categorias,
        private readonly PagoCompraRepository $pagos,
        private readonly ComprobanteCompraRepository $comprobantes,
        private readonly UsuarioRepository $usuarios,
    ) {}

    /**
     * @return array{compra: CompraInsumo, categoria: ?CategoriaCompra, registrado_por: string, lineas: list<LineaFicha>, pagos: list<array{pago: PagoCompra, registrado_por: string}>, comprobantes: list<ComprobanteCompra>}|null
     */
    public function execute(int $compraId): ?array
    {
        $compra = $this->compras->buscarPorId($compraId);

        if ($compra === null) {
            return null;
        }

        $nombres = [];
        foreach ($this->insumos->listar() as $insumo) {
            $nombres[(int) $insumo->id()] = $insumo->nombre();
        }

        $usuarios = [];
        $usuario = function (int $id) use (&$usuarios): string {
            return $usuarios[$id] ??= ($this->usuarios->buscarPorId($id)?->username() ?? '—');
        };

        $detalles = $this->compras->listarDetalles($compraId);

        return [
            'compra' => $compra,
            'categoria' => $compra->categoriaCompraId() !== null ? $this->categorias->buscarPorId($compra->categoriaCompraId()) : null,
            'registrado_por' => $usuario($compra->registradoPorUserId()),
            'lineas' => array_map(static fn (CompraInsumoDetalle $detalle): array => [
                'tratamiento' => $detalle->tratamiento()->value,
                'descripcion' => $detalle->insumoId() !== null ? ($nombres[$detalle->insumoId()] ?? 'Insumo eliminado') : (string) $detalle->descripcion(),
                'insumo_id' => $detalle->insumoId(),
                'insumo' => $detalle->insumoId() !== null ? ($nombres[$detalle->insumoId()] ?? 'Insumo eliminado') : null,
                'presentacion' => $detalle->descripcionPresentacion(),
                'cantidad_base' => $detalle->cantidadBase(),
                'unidad_base' => $detalle->unidadBase(),
                'precio_unitario' => $detalle->precioUnitario(),
                'subtotal' => $detalle->subtotal(),
                'movimiento_id' => $detalle->insumoMovimientoId(),
            ], $detalles),
            'pagos' => array_map(fn (PagoCompra $pago): array => ['pago' => $pago, 'registrado_por' => $usuario($pago->registradoPorUserId())], $this->pagos->listarPorCompra($compraId)),
            'comprobantes' => $this->comprobantes->listarPorCompra($compraId),
        ];
    }
}
