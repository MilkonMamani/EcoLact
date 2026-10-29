<?php

namespace App\Application\Pago;

use App\Application\Shared\TransactionManager;
use App\Domain\Auditoria\AuditoriaRepository;
use App\Domain\Auth\Repositories\RoleRepository;
use App\Domain\Auth\Repositories\UsuarioRepository;
use App\Domain\Auth\Role;
use App\Domain\Pago\ConfiguracionPago;
use App\Domain\Pago\ConfiguracionPagoRepository;
use App\Domain\Shared\Enums\PeriodicidadPago;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Domain\Shared\Enums\TipoCalculoPago;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * Guarda una regla de pago vigente. NO registra dinero ni crea pagos: solo
 * define cuánto y cómo se pagará.
 *
 * - Proveedor: por litros, semanal, una sola configuración general. El precio
 *   base por litro y el descuento por calidad OBSERVADA se guardan con vigencia
 *   histórica en Precio de leche / Reglas de pago por calidad, que siguen siendo
 *   la única fuente.
 * - Personal: sueldo fijo mensual de un ROL remunerable (nunca PROVEEDOR). Todos
 *   los trabajadores activos del rol, también los que se registren después,
 *   heredan ese sueldo: no hay sueldos individuales. Queda en auditorías.
 *
 * Dos configuraciones ACTIVAS de la misma clave (el proveedor, o un mismo rol)
 * no pueden cruzar sus vigencias: para cambiar un valor ya vigente se usa
 * ActualizarConfiguracionPago, que abre una nueva vigencia.
 */
final class RegistrarConfiguracionPago
{
    public const ACCION_AUDITORIA = 'CONFIGURAR_SUELDO_ROL';

    public function __construct(
        private readonly ConfiguracionPagoRepository $configuraciones,
        private readonly RoleRepository $roles,
        private readonly UsuarioRepository $usuarios,
        private readonly AplicarPrecioYAjustesProveedor $precioYAjustes,
        private readonly AuditoriaRepository $auditorias,
        private readonly TransactionManager $transacciones,
    ) {}

    /**
     * @param  int|null  $rolId  rol laboral (solo personal)
     * @param  array<string, array{tipo_ajuste?: string|null, valor_ajuste?: float|string|null}>|null  $ajustes
     */
    public function execute(
        TipoBeneficiarioPago $tipo,
        ?int $rolId,
        ?float $montoBase,
        bool $aplicaCalidad,
        DateTimeImmutable $fechaInicio,
        ?string $observacion,
        int $creadoPorUserId,
        ?float $precioBaseLitro = null,
        ?array $ajustes = null,
    ): ConfiguracionPago {
        if ($this->usuarios->buscarPorId($creadoPorUserId) === null) {
            throw new DomainException('El usuario que registra la configuración no existe.');
        }

        $rol = null;

        if ($tipo === TipoBeneficiarioPago::PERSONAL) {
            $rol = $rolId !== null ? $this->roles->buscarPorId($rolId) : null;

            if ($rol === null || ! $rol->esRemunerable()) {
                throw new DomainException('Selecciona un rol laboral remunerable: los proveedores cobran por litros entregados, no tienen sueldo.');
            }
        } else {
            $rolId = null;

            if ($precioBaseLitro === null || $precioBaseLitro <= 0.0) {
                throw new DomainException('El precio base por litro debe ser mayor a cero.');
            }
        }

        try {
            $configuracion = new ConfiguracionPago(
                id: null,
                tipoBeneficiario: $tipo,
                rolId: $rolId,
                tipoCalculo: $tipo === TipoBeneficiarioPago::PROVEEDOR ? TipoCalculoPago::POR_LITROS : TipoCalculoPago::SUELDO_FIJO,
                montoBase: $tipo === TipoBeneficiarioPago::PROVEEDOR ? null : $montoBase,
                periodicidad: $tipo === TipoBeneficiarioPago::PROVEEDOR ? PeriodicidadPago::SEMANAL : PeriodicidadPago::MENSUAL,
                aplicaCalidad: $tipo === TipoBeneficiarioPago::PROVEEDOR && $aplicaCalidad,
                fechaInicio: $fechaInicio->setTime(0, 0),
                fechaFin: null,
                estado: 'ACTIVO',
                observacion: $this->limpiar($observacion),
                creadoPorUserId: $creadoPorUserId,
            );
        } catch (InvalidArgumentException $e) {
            throw new DomainException($e->getMessage());
        }

        return $this->transacciones->run(function () use ($configuracion, $tipo, $rol, $precioBaseLitro, $ajustes, $creadoPorUserId): ConfiguracionPago {
            $solapadas = $this->configuraciones->listarSolapadas($tipo, $configuracion->rolId(), $configuracion->fechaInicio(), null);

            if ($solapadas !== []) {
                throw new DomainException(sprintf(
                    'Ya existe una configuración de pago para %s con vigencia desde el %s. Edítala para cambiar el valor con una nueva vigencia.',
                    $rol !== null ? 'el rol '.$rol->etiqueta() : 'los proveedores',
                    $solapadas[0]->fechaInicio()->format('d/m/Y'),
                ));
            }

            if ($tipo === TipoBeneficiarioPago::PROVEEDOR) {
                $this->precioYAjustes->execute($precioBaseLitro, $configuracion->aplicaCalidad(), $ajustes, $configuracion->fechaInicio(), $creadoPorUserId);
            }

            $guardada = $this->configuraciones->guardar($configuracion);

            if ($rol !== null) {
                $this->auditorias->registrar(
                    userId: $creadoPorUserId,
                    accion: self::ACCION_AUDITORIA,
                    tablaAfectada: 'configuraciones_pago',
                    registroId: $guardada->id(),
                    datosAnteriores: null,
                    datosNuevos: self::datosAuditoria($rol, $guardada),
                    motivo: $guardada->observacion(),
                );
            }

            return $guardada;
        });
    }

    /**
     * Lo que queda en auditorías de un sueldo por rol.
     *
     * @return array{rol: string, sueldo_mensual: float|null, vigente_desde: string, vigente_hasta: string|null}
     */
    public static function datosAuditoria(Role $rol, ConfiguracionPago $configuracion): array
    {
        return [
            'rol' => $rol->nombre(),
            'sueldo_mensual' => $configuracion->montoBase(),
            'vigente_desde' => $configuracion->fechaInicio()->format('Y-m-d'),
            'vigente_hasta' => $configuracion->fechaFin()?->format('Y-m-d'),
        ];
    }

    private function limpiar(?string $observacion): ?string
    {
        $observacion = $observacion !== null ? trim($observacion) : null;

        return $observacion === '' ? null : $observacion;
    }
}
