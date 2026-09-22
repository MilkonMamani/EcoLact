<?php

namespace App\Application\Pago;

use App\Application\Pago\Contracts\PersonalPagableQuery;
use App\Domain\Auth\Repositories\RoleRepository;
use App\Domain\Pago\ConfiguracionPago;
use App\Domain\Pago\ConfiguracionPagoRepository;
use App\Domain\Pago\JornadaPersonal;
use App\Domain\Pago\ObligacionPago;
use App\Domain\Pago\ObligacionPagoRepository;
use App\Domain\Pago\PeriodoPago;
use App\Domain\Shared\Enums\EstadoObligacionPago;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use DateTimeImmutable;

/**
 * Abre la ventana de 30 días (el "periodo") de cada persona activa del
 * personal, o recalcula el progreso de la que ya tiene abierta.
 *
 * Los roles se leen de la tabla roles (es_remunerable): un rol nuevo
 * remunerable genera ventanas sin tocar código. Cada ventana nace EN_CURSO,
 * sin monto (0 días trabajados); se paga cuando se registran 30 jornadas
 * (ver RegistrarJornadaTrabajador/RecalcularProgresoObligacionPersonal), no
 * al abrirse. La ventana usa la configuración vigente al cierre del periodo
 * (o hoy, si el periodo está en curso). No se abren ventanas anteriores al
 * alta de la persona ni al inicio de la primera configuración, y quien está
 * inactivo no genera ventanas.
 *
 * Es idempotente: cada persona tiene como máximo una ventana abierta
 * (EN_CURSO o PENDIENTE) a la vez; la clave única (persona + periodo) impide
 * duplicar.
 */
final class GenerarObligacionesPersonal
{
    public function __construct(
        private readonly ConfiguracionPagoRepository $configuraciones,
        private readonly PersonalPagableQuery $personal,
        private readonly ObligacionPagoRepository $obligaciones,
        private readonly RoleRepository $roles,
        private readonly RecalcularProgresoObligacionPersonal $recalcular,
    ) {}

    public function execute(DateTimeImmutable $hoy, ?int $actorUserId = null): ResultadoGeneracionPagos
    {
        $hoy = $hoy->setTime(0, 0);
        $resultado = new ResultadoGeneracionPagos;

        foreach ($this->roles->listarRemunerables() as $rol) {
            $rolId = $rol->id();

            $configuraciones = array_values(array_filter(
                $this->configuraciones->listarHistorial(TipoBeneficiarioPago::PERSONAL, $rolId),
                fn (ConfiguracionPago $configuracion): bool => $configuracion->estado() === 'ACTIVO',
            ));

            if ($configuraciones === []) {
                continue;
            }

            $primeraVigencia = min(array_map(fn (ConfiguracionPago $c): DateTimeImmutable => $c->fechaInicio(), $configuraciones));

            foreach ($this->personal->activosPorRol($rolId) as $persona) {
                $this->asegurarVentanaAbierta($rol->nombre(), $persona, $configuraciones, $primeraVigencia, $hoy, $actorUserId, $resultado);
            }
        }

        return $resultado;
    }

    /**
     * @param  ConfiguracionPago[]  $configuraciones
     */
    private function asegurarVentanaAbierta(
        string $rol,
        PersonalPagable $persona,
        array $configuraciones,
        DateTimeImmutable $primeraVigencia,
        DateTimeImmutable $hoy,
        ?int $actorUserId,
        ResultadoGeneracionPagos $resultado,
    ): void {
        $ultima = $this->obligaciones->buscarPersonalMasRecientePorUsuario($persona->userId);

        // Ya hay una ventana abierta (EN_CURSO o PENDIENTE): nada que crear,
        // solo recalcular su progreso por si hay jornadas que aún no se reflejaron.
        if ($ultima !== null && ! $ultima->estaPagada()) {
            $this->recalcular->execute($ultima);
            $resultado->existentes++;

            return;
        }

        $inicioSiguiente = $ultima !== null
            ? $ultima->periodoFin()->modify('+1 day')
            : max($primeraVigencia, $persona->fechaAlta);

        $ventana = PeriodoPago::ventanaDe30Dias($inicioSiguiente);

        if (! $ventana->haComenzado($hoy)) {
            return;
        }

        $configuracion = $this->configuracionVigente($configuraciones, $ventana->fechaDeReferencia($hoy));

        if ($configuracion === null) {
            return;
        }

        $creada = $this->obligaciones->guardarSiNoExiste(new ObligacionPago(
            id: null,
            tipoBeneficiario: TipoBeneficiarioPago::PERSONAL,
            proveedorId: null,
            userId: $persona->userId,
            rolId: $persona->rolId,
            beneficiarioNombre: $persona->nombre,
            configuracionPagoId: $configuracion->id(),
            liquidacionId: null,
            periodoInicio: $ventana->inicio,
            periodoFin: $ventana->fin,
            periodicidad: $configuracion->periodicidad(),
            concepto: $configuracion->tipoCalculo()->etiqueta(),
            baseCalculo: [
                'tipo_calculo' => $configuracion->tipoCalculo()->value,
                'rol' => $rol,
                'sueldo_base' => $configuracion->montoBase(),
                'vigente_desde' => $configuracion->fechaInicio()->format('Y-m-d'),
            ],
            montoCalculado: 0.0,
            estado: EstadoObligacionPago::EN_CURSO,
            creadoPorUserId: $actorUserId,
            diasTrabajados: 0,
            valorDia: round((float) $configuracion->montoBase() / JornadaPersonal::DIAS_BASE_PERIODO, 2),
            acumulado: 0.0,
        ));

        if ($creada === null) {
            $resultado->existentes++;

            return;
        }

        // Por si ya hay jornadas registradas en el rango (no debería ocurrir en
        // uso normal, pero deja el estado consistente si así fuera).
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
