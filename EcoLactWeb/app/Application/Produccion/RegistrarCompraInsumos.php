<?php

namespace App\Application\Produccion;

use App\Application\Compra\RegistrarCompra;
use App\Domain\Produccion\CompraInsumo;
use App\Domain\Shared\Enums\TratamientoCompra;
use DomainException;

/**
 * Compra de insumos tal como la envía la app del encargado de planta:
 * líneas "insumo + presentación + cantidad" que ya llegaron al almacén.
 *
 * No tiene lógica propia: es el flujo único de Compras y gastos
 * (RegistrarCompra) con sus valores de siempre:
 *   - todas las líneas son INVENTARIO y la compra se da por recibida, así
 *     el stock entra al confirmar (una vez, con RecibirCompra),
 *   - si la compra tiene total (el indicado o la suma de cantidad × precio),
 *     se paga al contado: un pago y su única salida de caja (INSUMOS);
 *     sin total no se inventa ningún pago.
 */
final class RegistrarCompraInsumos
{
    public function __construct(
        private readonly RegistrarCompra $registrarCompra,
    ) {}

    /**
     * @param  list<array{insumo_id: int|string, presentacion_id: int|string, cantidad: float|int|string, precio_unitario?: float|int|string|null}>  $lineas
     * @param  array{proveedor_comercial?: ?string, ruc?: ?string, numero_comprobante?: ?string, fecha?: ?string, total?: float|string|null, observacion?: ?string, imagen_boleta?: ?string}  $datos
     *
     * @throws DomainException si alguna línea no es válida (no se registra nada).
     */
    public function execute(array $lineas, array $datos, int $registradoPorUserId): CompraInsumo
    {
        return $this->registrarCompra->execute(
            array_map(fn (array $linea): array => ['tratamiento' => TratamientoCompra::INVENTARIO->value] + $linea, $lineas),
            [
                'proveedor_comercial' => $datos['proveedor_comercial'] ?? null,
                'ruc' => $datos['ruc'] ?? null,
                'numero_comprobante' => $datos['numero_comprobante'] ?? null,
                'fecha' => $datos['fecha'] ?? null,
                'total' => $datos['total'] ?? null,
                'observacion' => $datos['observacion'] ?? null,
                'imagen_boleta' => $datos['imagen_boleta'] ?? null,
                'recepcion' => 'RECIBIDA',
            ],
            $registradoPorUserId,
        );
    }
}
