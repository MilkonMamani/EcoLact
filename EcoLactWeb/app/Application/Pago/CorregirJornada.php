<?php

namespace App\Application\Pago;

use App\Application\Shared\TransactionManager;
use App\Domain\Auditoria\AuditoriaRepository;
use App\Domain\Pago\JornadaPersonal;
use App\Domain\Pago\JornadaPersonalRepository;
use App\Domain\Pago\ObligacionPago;
use App\Domain\Pago\ObligacionPagoRepository;
use App\Domain\Shared\Enums\EstadoJornada;
use App\Domain\Shared\Enums\OrigenJornada;
use App\Domain\Trabajador\TrabajadorRepository;
use DomainException;

/**
 * Corrección administrativa de una jornada ya existente (FALTA<->PRESENTE),
 * con motivo obligatorio. Deja rastro en `auditorias` (nunca se pierde la
 * trazabilidad de un cambio manual). Solo se corrige la jornada de hoy
 * antes del cierre de las 07:00 (VentanaAsistencia): un día cerrado es solo
 * consulta. Tampoco permite corregir una jornada cuya obligación del mes ya
 * está PAGADA: el histórico pagado es inmutable.
 */
final class CorregirJornada
{
    public function __construct(
        private readonly JornadaPersonalRepository $jornadas,
        private readonly TrabajadorRepository $trabajadores,
        private readonly ObligacionPagoRepository $obligaciones,
        private readonly RecalcularProgresoObligacionPersonal $recalcular,
        private readonly AuditoriaRepository $auditorias,
        private readonly TransactionManager $transacciones,
        private readonly VentanaAsistencia $ventana,
    ) {}

    public function execute(int $jornadaId, EstadoJornada $nuevoEstado, string $motivo, int $adminUserId): JornadaPersonal
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new DomainException('El motivo de la corrección es obligatorio.');
        }

        return $this->transacciones->run(function () use ($jornadaId, $nuevoEstado, $motivo, $adminUserId): JornadaPersonal {
            $jornada = $this->jornadas->buscarPorId($jornadaId)
                ?? throw new DomainException('La jornada indicada no existe.');

            $this->ventana->exigirAbierta($jornada->fecha());

            if ($nuevoEstado === $jornada->estado()) {
                throw new DomainException('La jornada ya está en ese estado.');
            }

            $trabajador = $this->trabajadores->buscarPorId($jornada->trabajadorId());
            $obligacion = $trabajador !== null ? $this->obligacionDelMes($trabajador->userId(), $jornada) : null;

            if ($obligacion !== null && $obligacion->estaPagada()) {
                throw new DomainException('No se puede corregir una jornada de un periodo ya pagado.');
            }

            $estadoAnterior = $jornada->estado();

            $actualizada = $this->jornadas->actualizarEstado($jornadaId, $nuevoEstado, OrigenJornada::CORREGIDO, $motivo);

            $this->auditorias->registrar(
                userId: $adminUserId,
                accion: 'CORRECCION_ASISTENCIA',
                tablaAfectada: 'jornadas_personal',
                registroId: $jornadaId,
                datosAnteriores: ['estado' => $estadoAnterior->value, 'origen' => $jornada->origen()->value, 'trabajador_id' => $jornada->trabajadorId(), 'fecha' => $jornada->fecha()->format('Y-m-d')],
                datosNuevos: ['estado' => $nuevoEstado->value, 'origen' => OrigenJornada::CORREGIDO->value, 'trabajador_id' => $jornada->trabajadorId(), 'fecha' => $jornada->fecha()->format('Y-m-d')],
                motivo: $motivo,
            );

            if ($obligacion !== null && $obligacion->estaEnCurso()) {
                $this->recalcular->execute($obligacion);
            }

            return $actualizada;
        });
    }

    private function obligacionDelMes(int $userId, JornadaPersonal $jornada): ?ObligacionPago
    {
        $abierta = $this->obligaciones->buscarPersonalAbiertaPorUsuario($userId);

        if ($abierta !== null && $jornada->fecha() >= $abierta->periodoInicio() && $jornada->fecha() <= $abierta->periodoFin()) {
            return $abierta;
        }

        $reciente = $this->obligaciones->buscarPersonalMasRecientePorUsuario($userId);

        if ($reciente !== null && $jornada->fecha() >= $reciente->periodoInicio() && $jornada->fecha() <= $reciente->periodoFin()) {
            return $reciente;
        }

        return null;
    }
}
