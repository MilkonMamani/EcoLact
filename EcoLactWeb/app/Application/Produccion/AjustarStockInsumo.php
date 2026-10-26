<?php

namespace App\Application\Produccion;

use App\Application\Shared\TransactionManager;
use App\Domain\Produccion\InsumoMovimiento;
use App\Domain\Produccion\InsumoRepository;
use App\Domain\Shared\Enums\MotivoAjusteInsumo;
use DomainException;

/**
 * "Ajustar stock" de la pantalla Insumos: traduce el motivo elegido (merma,
 * pérdida, corrección o inventario físico) al stock real que debe quedar y lo
 * registra con RegistrarAjusteInsumo (que bloquea el insumo y genera el
 * AJUSTE_ENTRADA/AJUSTE_SALIDA por la diferencia). Las cantidades van en la
 * unidad base del insumo. Nunca sirve para compras.
 */
final class AjustarStockInsumo
{
    public const SUMA = 'SUMA';

    public const RESTA = 'RESTA';

    public function __construct(
        private readonly InsumoRepository $insumos,
        private readonly RegistrarAjusteInsumo $registrarAjuste,
        private readonly TransactionManager $transacciones,
    ) {}

    /**
     * @param  float  $cantidad  cantidad a sumar/restar o, en INVENTARIO_FISICO, el stock contado.
     * @param  ?string  $direccion  SUMA o RESTA; solo la usa CORRECCION.
     */
    public function execute(
        int $insumoId,
        MotivoAjusteInsumo $motivo,
        float $cantidad,
        int $registradoPorUserId,
        ?string $direccion = null,
        ?string $observacion = null,
    ): InsumoMovimiento {
        if ($cantidad < 0.0 || ($cantidad === 0.0 && $motivo !== MotivoAjusteInsumo::INVENTARIO_FISICO)) {
            throw new DomainException('La cantidad debe ser mayor que cero.');
        }

        if ($motivo === MotivoAjusteInsumo::CORRECCION && ! in_array($direccion, [self::SUMA, self::RESTA], true)) {
            throw new DomainException('Indique si la corrección suma o resta stock.');
        }

        return $this->transacciones->run(function () use ($insumoId, $motivo, $cantidad, $registradoPorUserId, $direccion, $observacion): InsumoMovimiento {
            $insumo = $this->insumos->bloquearPorId($insumoId)
                ?? throw new DomainException('El insumo indicado no existe.');

            $resta = $motivo->soloResta() || ($motivo === MotivoAjusteInsumo::CORRECCION && $direccion === self::RESTA);

            $nuevoStock = match (true) {
                $motivo === MotivoAjusteInsumo::INVENTARIO_FISICO => $cantidad,
                $resta => $insumo->stockActual() - $cantidad,
                default => $insumo->stockActual() + $cantidad,
            };

            if ($nuevoStock < 0.0) {
                throw new DomainException(sprintf(
                    'No hay suficiente %s. Disponible: %s %s, a restar: %s %s.',
                    $insumo->nombre(), self::numero($insumo->stockActual()), $insumo->unidadBase(), self::numero($cantidad), $insumo->unidadBase(),
                ));
            }

            return $this->registrarAjuste->execute(
                insumoId: $insumoId,
                nuevoStock: round($nuevoStock, 4),
                motivo: $motivo->etiqueta(),
                registradoPorUserId: $registradoPorUserId,
                observacion: $observacion !== null && trim($observacion) !== '' ? trim($observacion) : null,
            );
        });
    }

    private static function numero(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 4, '.', ''), '0'), '.');
    }
}
