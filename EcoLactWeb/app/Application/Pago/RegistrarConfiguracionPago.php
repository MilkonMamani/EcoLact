<?php

namespace App\Application\Pago;

use App\Application\Pago\Contracts\PersonalPagableQuery;
use App\Application\Shared\TransactionManager;
use App\Domain\Auth\Repositories\RoleRepository;
use App\Domain\Auth\Repositories\UsuarioRepository;
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
 * - Personal: sueldo fijo mensual de UN trabajador activo (con ficha activa en
 *   Trabajadores, o el usuario de un rol remunerable sin ficha, como el
 *   administrador). Su rol se toma del trabajador real, nunca del formulario.
 *
 * Dos configuraciones ACTIVAS de la misma clave (el proveedor, o un mismo
 * trabajador) no pueden cruzar sus vigencias: para cambiar un valor ya vigente
 * se usa ActualizarConfiguracionPago, que abre una nueva vigencia.
 */
final class RegistrarConfiguracionPago
{
    public function __construct(
        private readonly ConfiguracionPagoRepository $configuraciones,
        private readonly RoleRepository $roles,
        private readonly UsuarioRepository $usuarios,
        private readonly PersonalPagableQuery $personal,
        private readonly AplicarPrecioYAjustesProveedor $precioYAjustes,
        private readonly TransactionManager $transacciones,
    ) {}

    /**
     * @param  int|null  $userId  trabajador (solo personal)
     * @param  array<string, array{tipo_ajuste?: string|null, valor_ajuste?: float|string|null}>|null  $ajustes
     */
    public function execute(
        TipoBeneficiarioPago $tipo,
        ?int $userId,
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

        $rolId = null;
        $trabajador = null;

        if ($tipo === TipoBeneficiarioPago::PERSONAL) {
            $trabajador = $userId !== null ? $this->personal->trabajadorActivo($userId) : null;

            if ($trabajador === null) {
                throw new DomainException('Selecciona un trabajador activo con un rol remunerable.');
            }

            $rol = $this->roles->buscarPorId($trabajador->rolId);

            if ($rol === null || ! $rol->esRemunerable()) {
                throw new DomainException('El rol del trabajador no es remunerable, por lo que no puede tener sueldo.');
            }

            $rolId = $rol->id();
        } else {
            $userId = null;

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
                userId: $userId,
            );
        } catch (InvalidArgumentException $e) {
            throw new DomainException($e->getMessage());
        }

        return $this->transacciones->run(function () use ($configuracion, $tipo, $userId, $trabajador, $precioBaseLitro, $ajustes, $creadoPorUserId): ConfiguracionPago {
            $solapadas = $userId !== null
                ? $this->configuraciones->listarSolapadasDeTrabajador($userId, $configuracion->fechaInicio(), null)
                : $this->configuraciones->listarSolapadas($tipo, null, $configuracion->fechaInicio(), null);

            if ($solapadas !== []) {
                throw new DomainException(sprintf(
                    'Ya existe una configuración de pago para %s con vigencia desde el %s. Edítala para cambiar el valor con una nueva vigencia.',
                    $trabajador?->nombre ?? 'los proveedores',
                    $solapadas[0]->fechaInicio()->format('d/m/Y'),
                ));
            }

            if ($tipo === TipoBeneficiarioPago::PROVEEDOR) {
                $this->precioYAjustes->execute($precioBaseLitro, $configuracion->aplicaCalidad(), $ajustes, $configuracion->fechaInicio(), $creadoPorUserId);
            }

            return $this->configuraciones->guardar($configuracion);
        });
    }

    private function limpiar(?string $observacion): ?string
    {
        $observacion = $observacion !== null ? trim($observacion) : null;

        return $observacion === '' ? null : $observacion;
    }
}
