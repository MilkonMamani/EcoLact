<?php

namespace App\Application\Produccion;

use App\Domain\Auth\Repositories\UsuarioRepository;
use App\Domain\Produccion\CompraInsumo;
use App\Domain\Produccion\CompraInsumoDetalle;
use App\Domain\Produccion\CompraInsumoRepository;
use App\Domain\Produccion\InsumoRepository;

/**
 * Detalle de una compra de insumos para consultarla después: comprobante,
 * quién la confirmó y cada línea con su fotografía de presentación
 * ("Sal · 2 × Saco 25 kg · +50 000 g"). Es histórico: no se recalcula con
 * las presentaciones de hoy.
 *
 * @phpstan-type LineaDetalle array{insumo: string, presentacion: string, cantidad_base: float, unidad_base: string, precio_unitario: ?float, subtotal: ?float}
 */
final class ObtenerDetalleCompraInsumo
{
    public function __construct(
        private readonly CompraInsumoRepository $compras,
        private readonly InsumoRepository $insumos,
        private readonly UsuarioRepository $usuarios,
    ) {}

    /**
     * @return array{compra: CompraInsumo, registrado_por: string, lineas: list<LineaDetalle>}|null
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

        return [
            'compra' => $compra,
            'registrado_por' => $this->usuarios->buscarPorId($compra->registradoPorUserId())?->username() ?? '—',
            'lineas' => array_map(static fn (CompraInsumoDetalle $detalle): array => [
                'insumo' => $nombres[$detalle->insumoId()] ?? 'Insumo eliminado',
                'presentacion' => $detalle->descripcionPresentacion(),
                'cantidad_base' => $detalle->cantidadBase(),
                'unidad_base' => $detalle->unidadBase(),
                'precio_unitario' => $detalle->precioUnitario(),
                'subtotal' => $detalle->subtotal(),
            ], $this->compras->listarDetalles($compraId)),
        ];
    }
}
