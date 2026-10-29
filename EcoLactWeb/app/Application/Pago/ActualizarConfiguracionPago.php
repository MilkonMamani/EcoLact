<?php

namespace App\Application\Pago;

use App\Application\Shared\TransactionManager;
use App\Domain\Auditoria\AuditoriaRepository;
use App\Domain\Auth\Repositories\RoleRepository;
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
 *
 * Solo se cambian el sueldo de un ROL o la configuración de proveedores: las
 * filas individuales por trabajador son historial y no se editan. Todo cambio
 * de sueldo queda en auditorías con el valor anterior y el nuevo.
 */
final class ActualizarConfiguracionPago
{
    public function __construct(
        private readonly ConfiguracionPagoRepository $configuraciones,
        private readonly AplicarPrecioYAjustesProveedor $precioYAjustes,
        private readonly RoleRepository $roles,
        private readonly AuditoriaRepository $auditorias,
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

            if ($actual->userId() !== null) {
                throw new DomainException('Ese sueldo individual es historial del modelo anterior: el sueldo ahora se configura por rol.');
            }

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

            $solapadas = $this->configuraciones->listarSolapadas($actual->tipoBeneficiario(), $actual->rolId(), $fechaInicio, $actual->fechaFin(), $configuracionId);

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
                $guardada = $this->configuraciones->actualizar($nueva);
            } else {
                $this->configuraciones->actualizar($actual->conVigenciaHasta($fechaInicio->modify('-1 day')));
                $guardada = $this->configuraciones->guardar($nueva);
            }

            if (! $esProveedor) {
                $this->auditar($actual, $guardada, $nuevaVigencia, $actorUserId);
            }

            return $guardada;
        });
    }

    private function auditar(ConfiguracionPago $anterior, ConfiguracionPago $guardada, bool $nuevaVigencia, int $actorUserId): void
    {
        $rol = $this->roles->buscarPorId((int) $anterior->rolId())
            ?? throw new DomainException('El rol de la configuración ya no existe.');

        $this->auditorias->registrar(
            userId: $actorUserId,
            accion: $nuevaVigencia ? 'NUEVA_VIGENCIA_SUELDO_ROL' : 'CORREGIR_SUELDO_ROL',
            tablaAfectada: 'configuraciones_pago',
            registroId: $guardada->id(),
            datosAnteriores: RegistrarConfiguracionPago::datosAuditoria($rol, $anterior) + ['configuracion_id' => $anterior->id()],
            datosNuevos: RegistrarConfiguracionPago::datosAuditoria($rol, $guardada),
            motivo: $guardada->observacion(),
        );
    }
}
