<?php

namespace App\Application\Pago;

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
 * - Proveedor: por litros, semanal. El precio base por litro (y, si se activan,
 *   los ajustes por calidad) se guardan con vigencia histórica en Precio de
 *   leche / Reglas de pago por calidad, que siguen siendo la única fuente.
 * - Personal: sueldo fijo mensual para un rol de la tabla roles marcado como
 *   remunerable (nunca PROVEEDOR).
 *
 * Dos configuraciones ACTIVAS de la misma clave (proveedor, o un mismo rol) no
 * pueden cruzar sus vigencias: para cambiar un valor ya vigente se usa
 * ActualizarConfiguracionPago, que abre una nueva vigencia.
 */
final class RegistrarConfiguracionPago
{
    public function __construct(
        private readonly ConfiguracionPagoRepository $configuraciones,
        private readonly RoleRepository $roles,
        private readonly UsuarioRepository $usuarios,
        private readonly AplicarPrecioYAjustesProveedor $precioYAjustes,
        private readonly TransactionManager $transacciones,
    ) {}

    /**
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

        $rolNombre = null;

        if ($tipo === TipoBeneficiarioPago::PERSONAL) {
            $rol = $rolId !== null ? $this->roles->buscarPorId($rolId) : null;

            if ($rol === null) {
                throw new DomainException('Selecciona el rol del sistema.');
            }

            if (! $rol->esRemunerable()) {
                throw new DomainException('El rol '.$rol->nombre().' no es remunerable, por lo que no puede tener sueldo.');
            }

            $rolNombre = $rol->nombre();
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

        return $this->transacciones->run(function () use ($configuracion, $tipo, $rolId, $rolNombre, $precioBaseLitro, $ajustes, $creadoPorUserId): ConfiguracionPago {
            $solapadas = $this->configuraciones->listarSolapadas($tipo, $rolId, $configuracion->fechaInicio(), null);

            if ($solapadas !== []) {
                $destino = $tipo === TipoBeneficiarioPago::PROVEEDOR ? 'los proveedores' : ($rolNombre ?? 'este rol');

                throw new DomainException(sprintf(
                    'Ya existe una configuración de pago para %s con vigencia desde el %s. Edítala para cambiar el valor con una nueva vigencia.',
                    $destino,
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
