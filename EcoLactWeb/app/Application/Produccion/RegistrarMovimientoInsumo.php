<?php

namespace App\Application\Produccion;

use App\Application\Shared\TransactionManager;
use App\Domain\Produccion\InsumoMovimiento;
use App\Domain\Produccion\InsumoMovimientoRepository;
use App\Domain\Produccion\InsumoRepository;
use App\Domain\Produccion\UnidadMedida;
use App\Domain\Shared\Enums\MotivoMovimientoInsumo;
use DateTimeImmutable;
use DomainException;

/**
 * ÚNICA puerta de escritura del stock de insumos. Toda entrada o salida
 * (acopio de leche, compras, ajustes, consumo de producción, reversos) pasa
 * por aquí, así que la regla "nunca stock negativo" vive en un solo lugar.
 *
 * Dentro de una transacción: bloquea la fila del insumo (FOR UPDATE), calcula
 * el nuevo saldo, rechaza si quedaría negativo, guarda el movimiento y
 * actualiza el saldo cacheado. Dos usuarios que consuman a la vez el mismo
 * insumo se serializan en el bloqueo, y el segundo verá el saldo ya
 * descontado.
 *
 * La cantidad se expresa en `$unidad` (por defecto, la unidad base del
 * insumo) y siempre se guarda convertida a la unidad base.
 */
final class RegistrarMovimientoInsumo
{
    public function __construct(
        private readonly InsumoRepository $insumos,
        private readonly InsumoMovimientoRepository $movimientos,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(
        int $insumoId,
        MotivoMovimientoInsumo $motivo,
        string $direccion,
        float $cantidad,
        int $registradoPorUserId,
        ?string $observacion = null,
        ?string $origenTipo = null,
        ?int $origenId = null,
        ?int $loteProduccionId = null,
        ?DateTimeImmutable $fecha = null,
        ?string $unidad = null,
    ): InsumoMovimiento {
        if ($cantidad <= 0.0) {
            throw new DomainException('La cantidad debe ser mayor que cero.');
        }

        return $this->transacciones->run(function () use (
            $insumoId, $motivo, $direccion, $cantidad, $registradoPorUserId, $observacion,
            $origenTipo, $origenId, $loteProduccionId, $fecha, $unidad,
        ): InsumoMovimiento {
            $insumo = $this->insumos->bloquearPorId($insumoId)
                ?? throw new DomainException('El insumo indicado no existe.');

            $cantidadBase = $unidad === null || $unidad === $insumo->unidadBase()
                ? $cantidad
                : $this->convertir($cantidad, $unidad, $insumo->unidadBase(), $insumo->nombre());

            $cantidadBase = round($cantidadBase, 4);

            if ($cantidadBase <= 0.0) {
                throw new DomainException('La cantidad es demasiado pequeña para la unidad base del insumo.');
            }

            $nuevoSaldo = $direccion === MotivoMovimientoInsumo::ENTRADA
                ? $insumo->stockActual() + $cantidadBase
                : $insumo->stockActual() - $cantidadBase;

            if ($nuevoSaldo < -0.00005) {
                throw new DomainException(sprintf(
                    'No hay suficiente %s. Disponible: %s %s, necesario: %s %s.',
                    $insumo->nombre(),
                    self::formato($insumo->stockActual()),
                    $insumo->unidadBase(),
                    self::formato($cantidadBase),
                    $insumo->unidadBase(),
                ));
            }

            $movimiento = $this->movimientos->guardar(new InsumoMovimiento(
                id: null,
                insumoId: $insumoId,
                tipo: $direccion,
                motivo: $motivo->value,
                cantidad: $cantidadBase,
                loteProduccionId: $loteProduccionId,
                fecha: $fecha ?? new DateTimeImmutable,
                registradoPorUserId: $registradoPorUserId,
                observacion: $observacion,
                origenTipo: $origenTipo,
                origenId: $origenId,
            ));

            $this->insumos->actualizar($insumo->conStock(max($nuevoSaldo, 0.0)));

            return $movimiento;
        });
    }

    private function convertir(float $cantidad, string $desde, string $hasta, string $nombreInsumo): float
    {
        if (! UnidadMedida::sonCompatibles($desde, $hasta)) {
            throw new DomainException("La unidad {$desde} no es compatible con {$nombreInsumo}, que se controla en {$hasta}.");
        }

        return UnidadMedida::convertir($cantidad, $desde, $hasta);
    }

    public static function formato(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 4, '.', ','), '0'), '.');
    }
}
