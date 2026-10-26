<?php

namespace App\Application\Produccion;

use App\Application\Caja\RegistrarMovimientoCaja;
use App\Application\Shared\TransactionManager;
use App\Domain\Produccion\CompraInsumo;
use App\Domain\Produccion\CompraInsumoDetalle;
use App\Domain\Produccion\CompraInsumoRepository;
use App\Domain\Produccion\InsumoMovimiento;
use App\Domain\Shared\Enums\MotivoMovimientoInsumo;
use DateTimeImmutable;
use DomainException;

/**
 * CONFIRMAR COMPRA: el único momento en que una compra aumenta el stock. Lo
 * llama la persona después de revisar (boleta escaneada o registro manual);
 * el OCR nunca llega hasta aquí por su cuenta.
 *
 * Todo ocurre en UNA transacción (todo o nada):
 *   1. recalcula la compra con CalcularCompraInsumos (no confía en la pantalla),
 *   2. crea la compra con quien la confirma (`registradoPorUserId`),
 *   3. por cada línea registra un movimiento INGRESO_COMPRA con origen
 *      COMPRA_INSUMO por RegistrarMovimientoInsumo (la única puerta del stock),
 *   4. guarda el detalle con la fotografía de la presentación y su movimiento,
 *   5. si la compra tiene total, registra su SALIDA de caja (INSUMOS). El
 *      total es el indicado o, si no se indicó y todas las líneas tienen
 *      precio unitario, la suma de cantidad × precio.
 */
final class RegistrarCompraInsumos
{
    public function __construct(
        private readonly CalcularCompraInsumos $calcular,
        private readonly CompraInsumoRepository $compras,
        private readonly RegistrarMovimientoInsumo $registrarMovimiento,
        private readonly TransactionManager $transacciones,
        private readonly RegistrarMovimientoCaja $caja,
    ) {}

    /**
     * @param  list<array{insumo_id: int|string, presentacion_id: int|string, cantidad: float|int|string, precio_unitario?: float|int|string|null}>  $lineas
     * @param  array{proveedor_comercial?: ?string, ruc?: ?string, numero_comprobante?: ?string, fecha?: ?string, total?: float|string|null, observacion?: ?string, imagen_boleta?: ?string}  $datos
     *
     * @throws DomainException si alguna línea no es válida (no se registra nada).
     */
    public function execute(array $lineas, array $datos, int $registradoPorUserId): CompraInsumo
    {
        $texto = static fn (?string $valor): ?string => $valor !== null && trim($valor) !== '' ? trim($valor) : null;

        return $this->transacciones->run(function () use ($lineas, $datos, $registradoPorUserId, $texto): CompraInsumo {
            $calculo = $this->calcular->execute($lineas);
            $imagen = $texto($datos['imagen_boleta'] ?? null);
            $total = isset($datos['total']) && $datos['total'] !== '' ? round((float) $datos['total'], 2) : $calculo['total_precios'];

            $compra = $this->compras->guardar(new CompraInsumo(
                id: null,
                origen: $imagen !== null ? CompraInsumo::ORIGEN_BOLETA : CompraInsumo::ORIGEN_MANUAL,
                fecha: isset($datos['fecha']) && $datos['fecha'] !== '' ? new DateTimeImmutable($datos['fecha']) : new DateTimeImmutable,
                registradoPorUserId: $registradoPorUserId,
                proveedorComercial: $texto($datos['proveedor_comercial'] ?? null),
                ruc: $texto($datos['ruc'] ?? null),
                numeroComprobante: ($numero = $texto($datos['numero_comprobante'] ?? null)) !== null ? mb_strtoupper($numero) : null,
                total: $total,
                imagenBoleta: $imagen,
                observacion: $texto($datos['observacion'] ?? null),
            ));

            $compraId = $compra->id() ?? throw new DomainException('No se pudo registrar la compra.');

            foreach ($calculo['lineas'] as $linea) {
                $detalle = new CompraInsumoDetalle(
                    id: null,
                    compraInsumoId: $compraId,
                    insumoId: $linea['insumo_id'],
                    presentacionInsumoId: $linea['presentacion_id'],
                    presentacionNombre: $linea['presentacion_nombre'],
                    presentacionCantidad: $linea['presentacion_cantidad'],
                    presentacionUnidad: $linea['presentacion_unidad'],
                    cantidadPresentaciones: $linea['cantidad'],
                    cantidadBase: $linea['total'],
                    unidadBase: $linea['unidad_base'],
                    precioUnitario: $linea['precio_unitario'],
                );

                $movimiento = $this->registrarMovimiento->execute(
                    insumoId: $linea['insumo_id'],
                    motivo: MotivoMovimientoInsumo::INGRESO_COMPRA,
                    direccion: MotivoMovimientoInsumo::ENTRADA,
                    cantidad: $linea['total'],
                    registradoPorUserId: $registradoPorUserId,
                    observacion: $compra->referencia().' · '.$detalle->descripcionPresentacion(),
                    origenTipo: InsumoMovimiento::ORIGEN_COMPRA_INSUMO,
                    origenId: $compraId,
                );

                $this->compras->guardarDetalle($detalle->conCompraYMovimiento($compraId, (int) $movimiento->id()));
            }

            $this->caja->porCompraInsumos($compra);

            return $compra;
        });
    }
}
