<?php

namespace App\Application\Pago;

use App\Domain\Auth\Repositories\RoleRepository;
use App\Domain\Pago\ConfiguracionPago;
use App\Domain\Pago\ConfiguracionPagoRepository;
use App\Domain\Pago\JornadaPersonal;
use App\Domain\Pago\PrecioLeche;
use App\Domain\Pago\PrecioLecheRepository;
use App\Domain\Pago\ReglaPagoCalidad;
use App\Domain\Pago\ReglaPagoCalidadRepository;
use App\Domain\Shared\Enums\ResultadoCalidad;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use DateTimeImmutable;

/**
 * Datos de la pantalla Configuración de pagos: una fila para el proveedor y una
 * por cada rol REMUNERABLE de la tabla roles (un rol nuevo aparece solo), con su
 * vigencia actual (o la próxima programada) y el historial de vigencias.
 *
 * Cada fila: ['clave', 'rolId' (int|null), 'rolNombre' (string|null),
 * 'estado' (ACTIVA|PROGRAMADA|VENCIDA|SIN_CONFIGURAR), 'configuracion'
 * (ConfiguracionPago|null), 'historial' (ConfiguracionPago[] sin la mostrada)].
 */
final class ObtenerConfiguracionesPago
{
    public function __construct(
        private readonly ConfiguracionPagoRepository $configuraciones,
        private readonly RoleRepository $roles,
        private readonly PrecioLecheRepository $preciosLeche,
        private readonly ReglaPagoCalidadRepository $reglasPagoCalidad,
    ) {}

    /**
     * @return array{
     *     kpis: array{activas: int, proveedores: int, personal: int},
     *     proveedor: array<string, mixed>,
     *     personal: list<array<string, mixed>>,
     *     precioBaseVigente: ?float,
     *     preciosHistorial: PrecioLeche[],
     *     ajustesVigentes: array<string, ReglaPagoCalidad|null>,
     *     reglasActivas: int
     * }
     */
    public function execute(DateTimeImmutable $hoy): array
    {
        $proveedor = $this->fila('PROVEEDOR', null, null, $hoy);
        $personal = [];

        foreach ($this->roles->listarRemunerables() as $rol) {
            $personal[] = $this->fila('ROL:'.$rol->id(), $rol->id(), $rol->nombre(), $hoy);
        }

        $activasPersonal = count(array_filter($personal, fn (array $fila): bool => $fila['estado'] === 'ACTIVA'));
        $activasProveedor = $proveedor['estado'] === 'ACTIVA' ? 1 : 0;

        $ajustes = [];

        foreach (ResultadoCalidad::cases() as $resultado) {
            $ajustes[$resultado->value] = $this->reglasPagoCalidad->buscarVigentePorResultado($resultado, $hoy);
        }

        return [
            'kpis' => [
                'activas' => $activasProveedor + $activasPersonal,
                'proveedores' => $activasProveedor,
                'personal' => $activasPersonal,
            ],
            'proveedor' => $proveedor,
            'personal' => $personal,
            'precioBaseVigente' => $this->preciosLeche->buscarVigente($hoy)?->precioLitro(),
            'preciosHistorial' => array_values(array_filter($this->preciosLeche->listar(), fn (PrecioLeche $precio): bool => $precio->estado() === 'ACTIVO')),
            'ajustesVigentes' => $ajustes,
            'reglasActivas' => count(array_filter($ajustes)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fila(string $clave, ?int $rolId, ?string $rolNombre, DateTimeImmutable $hoy): array
    {
        $tipo = $rolNombre === null ? TipoBeneficiarioPago::PROVEEDOR : TipoBeneficiarioPago::PERSONAL;
        $historial = $this->configuraciones->listarHistorial($tipo, $rolId);

        $mostrada = null;
        $estado = 'SIN_CONFIGURAR';

        foreach ($historial as $configuracion) {
            if ($configuracion->estaVigente($hoy)) {
                $mostrada = $configuracion;
                $estado = 'ACTIVA';
                break;
            }
        }

        if ($mostrada === null) {
            $programadas = array_filter($historial, fn (ConfiguracionPago $c): bool => $c->estado() === 'ACTIVO' && $c->fechaInicio() > $hoy);

            if ($programadas !== []) {
                $mostrada = end($programadas);
                $estado = 'PROGRAMADA';
            } elseif ($historial !== []) {
                $mostrada = $historial[0];
                $estado = 'VENCIDA';
            }
        }

        return [
            'clave' => $clave,
            'rolId' => $rolId,
            'rolNombre' => $rolNombre,
            'estado' => $estado,
            'configuracion' => $mostrada,
            // Valor por día = sueldo mensual / 30, calculado aquí para que Blade nunca lo calcule; solo aplica a personal (SUELDO_FIJO).
            'valorDia' => ($mostrada !== null && $tipo === TipoBeneficiarioPago::PERSONAL)
                ? round((float) $mostrada->montoBase() / JornadaPersonal::DIAS_BASE_PERIODO, 2)
                : null,
            'historial' => array_values(array_filter(
                $historial,
                fn (ConfiguracionPago $c): bool => $mostrada === null || $c->id() !== $mostrada->id(),
            )),
        ];
    }
}
