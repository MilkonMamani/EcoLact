<?php

namespace App\Application\Pago;

use App\Application\Pago\Contracts\PersonalPagableQuery;
use App\Application\Trabajador\SincronizarTrabajadores;
use App\Domain\Auth\Repositories\RoleRepository;
use App\Domain\Pago\ConfiguracionPago;
use App\Domain\Pago\ConfiguracionPagoRepository;
use App\Domain\Pago\JornadaPersonal;
use App\Domain\Pago\ObligacionPago;
use App\Domain\Pago\ObligacionPagoRepository;
use App\Domain\Pago\PeriodoPago;
use App\Domain\Shared\Enums\EstadoObligacionPago;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Domain\Trabajador\Trabajador;
use App\Domain\Trabajador\TrabajadorRepository;
use DateTimeImmutable;

/**
 * Abre la obligación EN_CURSO del MES CALENDARIO actual de cada trabajador
 * activo (una por trabajador y mes; nunca abre meses pasados ni futuros: eso
 * es responsabilidad de CerrarNominaMensual al cerrar el mes anterior), o
 * recalcula el progreso de la que ya tiene abierta.
 *
 * Sincroniza `trabajadores` primero (SincronizarTrabajadores), así que
 * también sirve de red de seguridad diaria: un trabajador nuevo o uno que ya
 * no es pagable queda reflejado sin esperar al cierre de mes.
 *
 * Es idempotente: la clave única (beneficiario + periodo) impide duplicar.
 */
final class GenerarObligacionesPersonal
{
    public function __construct(
        private readonly SincronizarTrabajadores $sincronizarTrabajadores,
        private readonly ConfiguracionPagoRepository $configuraciones,
        private readonly PersonalPagableQuery $personal,
        private readonly TrabajadorRepository $trabajadores,
        private readonly ObligacionPagoRepository $obligaciones,
        private readonly RoleRepository $roles,
        private readonly RecalcularProgresoObligacionPersonal $recalcular,
    ) {}

    public function execute(DateTimeImmutable $hoy, ?int $actorUserId = null): ResultadoGeneracionPagos
    {
        $hoy = $hoy->setTime(0, 0);
        $resultado = new ResultadoGeneracionPagos;

        $this->sincronizarTrabajadores->execute();

        $periodo = PeriodoPago::mesQueContiene($hoy);

        foreach ($this->roles->listarRemunerables() as $rol) {
            $rolId = $rol->id();

            $configuraciones = array_values(array_filter(
                $this->configuraciones->listarHistorial(TipoBeneficiarioPago::PERSONAL, $rolId),
                fn (ConfiguracionPago $configuracion): bool => $configuracion->estado() === 'ACTIVO',
            ));

            if ($configuraciones === []) {
                continue;
            }

            foreach ($this->personal->activosPorRol($rolId) as $persona) {
                $trabajador = $this->trabajadores->buscarPorUserId($persona->userId);

                if ($trabajador === null || ! $trabajador->estaActivo() || ! $trabajador->estaVigenteEn($hoy)) {
                    continue;
                }

                $this->asegurarObligacionDelMes($rolId, $rol->nombre(), $trabajador, $configuraciones, $periodo, $hoy, $actorUserId, $resultado);
            }
        }

        return $resultado;
    }

    /**
     * @param  ConfiguracionPago[]  $configuraciones
     */
    private function asegurarObligacionDelMes(
        int $rolId,
        string $rolNombre,
        Trabajador $trabajador,
        array $configuraciones,
        PeriodoPago $periodo,
        DateTimeImmutable $hoy,
        ?int $actorUserId,
        ResultadoGeneracionPagos $resultado,
    ): void {
        $clave = ObligacionPago::clavePara(TipoBeneficiarioPago::PERSONAL, $trabajador->userId(), $periodo->inicio);

        if ($this->obligaciones->existePorClave($clave)) {
            $abierta = $this->obligaciones->buscarPersonalAbiertaPorUsuario($trabajador->userId());

            if ($abierta !== null && $abierta->estado() === EstadoObligacionPago::EN_CURSO) {
                $this->recalcular->execute($abierta);
            }

            $resultado->existentes++;

            return;
        }

        // Nunca dos EN_CURSO simultáneas: si el mes anterior todavía no se
        // cerró (CerrarNominaMensual), solo se recalcula esa, no se abre el
        // mes actual todavía. Evita duplicar periodos si el cierre mensual
        // falla o se retrasa y este generador sigue corriendo a diario.
        $masReciente = $this->obligaciones->buscarPersonalMasRecientePorUsuario($trabajador->userId());

        if ($masReciente !== null && $masReciente->estado() === EstadoObligacionPago::EN_CURSO) {
            $this->recalcular->execute($masReciente);
            $resultado->existentes++;

            return;
        }

        $configuracion = $this->configuracionVigente($configuraciones, $periodo->fechaDeReferencia($hoy));

        if ($configuracion === null) {
            return;
        }

        $creada = $this->obligaciones->guardarSiNoExiste(new ObligacionPago(
            id: null,
            tipoBeneficiario: TipoBeneficiarioPago::PERSONAL,
            proveedorId: null,
            userId: $trabajador->userId(),
            rolId: $rolId,
            beneficiarioNombre: $trabajador->nombreCompleto(),
            configuracionPagoId: $configuracion->id(),
            liquidacionId: null,
            periodoInicio: $periodo->inicio,
            periodoFin: $periodo->fin,
            periodicidad: $configuracion->periodicidad(),
            concepto: $configuracion->tipoCalculo()->etiqueta(),
            baseCalculo: [
                'tipo_calculo' => $configuracion->tipoCalculo()->value,
                'rol' => $rolNombre,
                'sueldo_base' => $configuracion->montoBase(),
                'vigente_desde' => $configuracion->fechaInicio()->format('Y-m-d'),
            ],
            montoCalculado: 0.0,
            estado: EstadoObligacionPago::EN_CURSO,
            creadoPorUserId: $actorUserId,
            diasTrabajados: 0,
            valorDia: round((float) $configuracion->montoBase() / JornadaPersonal::DIAS_BASE_PERIODO, 2),
            acumulado: 0.0,
            fechaPagoProgramada: $periodo->fechaPagoProgramada(),
        ));

        if ($creada === null) {
            $resultado->existentes++;

            return;
        }

        $this->recalcular->execute($creada);
        $resultado->creadas++;
    }

    /**
     * @param  ConfiguracionPago[]  $configuraciones
     */
    private function configuracionVigente(array $configuraciones, DateTimeImmutable $fecha): ?ConfiguracionPago
    {
        foreach ($configuraciones as $configuracion) {
            if ($configuracion->estaVigente($fecha)) {
                return $configuracion;
            }
        }

        return null;
    }
}
