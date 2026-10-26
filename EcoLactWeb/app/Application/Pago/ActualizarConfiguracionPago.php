<?php

namespace App\Application\Pago;

use App\Application\Shared\TransactionManager;
use App\Domain\Pago\ConfiguracionPago;
use App\Domain\Pago\ConfiguracionPagoRepository;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * Cambia una configuración de pago SIN reescribir el pasado.
 *
 * - Con una fecha de inicio POSTERIOR a la vigente se cierra la vigencia actual
 *   el día anterior y se abre una NUEVA con los valores nuevos: el historial
 *   queda completo y los pagos ya generados conservan el monto de su periodo.
 * - Con la MISMA fecha (o anterior) solo se corrige el mismo registro, y solo si
 *   todavía no generó ningún pago (es una corrección, no un cambio de valor).
 */
final class ActualizarConfiguracionPago
{
    public function __construct(
        private readonly ConfiguracionPagoRepository $configuraciones,
        private readonly AplicarPrecioYAjustesProveedor $precioYAjustes,
        private readonly TransactionManager $transacciones,
    ) {}

    /**
     * @param  array<string, array{tipo_ajuste?: string|null, valor_ajuste?: float|string|null}>|null  $ajustes
     */
    public function execute(
        int $configuracionId,
        ?float $montoBase,
        bool $aplicaCalidad,
        DateTimeImmutable $fechaInicio,
        ?string $observacion,
        int $actorUserId,
        ?float $precioBaseLitro = null,
        ?array $ajustes = null,
    ): ConfiguracionPago {
        return $this->transacciones->run(function () use ($configuracionId, $montoBase, $aplicaCalidad, $fechaInicio, $observacion, $actorUserId, $precioBaseLitro, $ajustes): ConfiguracionPago {
            $actual = $this->configuraciones->buscarPorId($configuracionId)
                ?? throw new DomainException('La configuración de pago indicada no existe.');

            $esProveedor = $actual->tipoBeneficiario() === TipoBeneficiarioPago::PROVEEDOR;
            $fechaInicio = $fechaInicio->setTime(0, 0);
            $observacion = $observacion !== null && trim($observacion) !== '' ? trim($observacion) : null;
            $nuevaVigencia = $fechaInicio > $actual->fechaInicio();

            if (! $nuevaVigencia && $this->configuraciones->tieneObligaciones($configuracionId)) {
                throw new DomainException(sprintf(
                    'Esta configuración ya generó pagos desde el %s. Indica una fecha de inicio posterior para la nueva vigencia.',
                    $actual->fechaInicio()->format('d/m/Y'),
                ));
            }

            $solapadas = $actual->userId() !== null
                ? $this->configuraciones->listarSolapadasDeTrabajador($actual->userId(), $fechaInicio, $actual->fechaFin(), $configuracionId)
                : $this->configuraciones->listarSolapadas($actual->tipoBeneficiario(), $actual->rolId(), $fechaInicio, $actual->fechaFin(), $configuracionId);

            if ($solapadas !== []) {
                throw new DomainException(sprintf(
                    'La nueva vigencia se cruza con otra configuración que inicia el %s. Edita esa configuración o elige otra fecha.',
                    $solapadas[0]->fechaInicio()->format('d/m/Y'),
                ));
            }

            try {
                $nueva = new ConfiguracionPago(
                    id: $nuevaVigencia ? null : $actual->id(),
                    tipoBeneficiario: $actual->tipoBeneficiario(),
                    rolId: $actual->rolId(),
                    tipoCalculo: $actual->tipoCalculo(),
                    montoBase: $esProveedor ? null : $montoBase,
                    periodicidad: $actual->periodicidad(),
                    aplicaCalidad: $esProveedor && $aplicaCalidad,
                    fechaInicio: $fechaInicio,
                    fechaFin: $actual->fechaFin(),
                    estado: 'ACTIVO',
                    observacion: $observacion,
                    creadoPorUserId: $actorUserId,
                    userId: $actual->userId(),
                );
            } catch (InvalidArgumentException $e) {
                throw new DomainException($e->getMessage());
            }

            if ($esProveedor) {
                if ($precioBaseLitro === null || $precioBaseLitro <= 0.0) {
                    throw new DomainException('El precio base por litro debe ser mayor a cero.');
                }

                $this->precioYAjustes->execute($precioBaseLitro, $nueva->aplicaCalidad(), $ajustes, $fechaInicio, $actorUserId);
            }

            if (! $nuevaVigencia) {
                return $this->configuraciones->actualizar($nueva);
            }

            $this->configuraciones->actualizar($actual->conVigenciaHasta($fechaInicio->modify('-1 day')));

            return $this->configuraciones->guardar($nueva);
        });
    }
}
