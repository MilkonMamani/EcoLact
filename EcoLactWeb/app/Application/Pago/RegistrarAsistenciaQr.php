<?php

namespace App\Application\Pago;

use App\Application\Shared\TransactionManager;
use App\Domain\Auth\Repositories\UsuarioRepository;
use App\Domain\Pago\JornadaPersonal;
use App\Domain\Pago\JornadaPersonalRepository;
use App\Domain\Pago\ObligacionPagoRepository;
use App\Domain\Shared\Enums\EstadoJornada;
use App\Domain\Shared\Enums\OrigenJornada;
use App\Domain\Trabajador\TrabajadorRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Registra la asistencia de hoy a partir del QR del trabajador. Idempotente:
 * un segundo escaneo el mismo día NO duplica, solo confirma que ya estaba
 * registrada. Si el trabajador ya tiene una FALTA de hoy (p.ej. generada por
 * MarcarFaltasAutomaticas antes de que llegara a marcar), NO se cambia en
 * silencio: exige corrección administrativa explícita (CorregirJornada), con
 * motivo, para no perder trazabilidad.
 */
final class RegistrarAsistenciaQr
{
    public function __construct(
        private readonly TrabajadorRepository $trabajadores,
        private readonly JornadaPersonalRepository $jornadas,
        private readonly UsuarioRepository $usuarios,
        private readonly ObligacionPagoRepository $obligaciones,
        private readonly RecalcularProgresoObligacionPersonal $recalcular,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(string $qrToken, int $adminUserId, ?DateTimeImmutable $hoy = null): ResultadoAsistenciaQr
    {
        $hoy = ($hoy ?? DateTimeImmutable::createFromInterface(now()))->setTime(0, 0);

        $trabajador = $this->trabajadores->buscarPorQrToken($qrToken)
            ?? throw new DomainException('QR no reconocido.');

        if (! $trabajador->estaActivo()) {
            throw new DomainException('El trabajador no está activo.');
        }

        if ($trabajador->fechaIngreso()->setTime(0, 0) > $hoy) {
            throw new DomainException('El trabajador aún no ha ingresado.');
        }

        return $this->transacciones->run(function () use ($trabajador, $hoy, $adminUserId): ResultadoAsistenciaQr {
            $existente = $this->jornadas->buscarEnFecha($trabajador->id(), $hoy);

            if ($existente !== null) {
                if ($existente->estado() === EstadoJornada::FALTA) {
                    throw new RequiereCorreccionException(
                        'Este trabajador ya tiene una falta automática registrada para hoy. La asistencia requiere corrección administrativa.'
                    );
                }

                return new ResultadoAsistenciaQr($existente, duplicado: true);
            }

            try {
                $jornada = $this->jornadas->registrar(new JornadaPersonal(
                    id: null,
                    trabajadorId: $trabajador->id(),
                    rolId: $this->usuarios->buscarPorId($trabajador->userId())->roleId(),
                    fecha: $hoy,
                    estado: EstadoJornada::PRESENTE,
                    origen: OrigenJornada::QR,
                    horaMarcacion: DateTimeImmutable::createFromInterface(now()),
                    observacion: null,
                    registradoPorUserId: $adminUserId,
                ));
            } catch (UniqueConstraintViolationException|DomainException) {
                // Doble escaneo simultáneo: la fila ya la creó la otra petición; se relee y se trata como idempotente.
                $existente = $this->jornadas->buscarEnFecha($trabajador->id(), $hoy)
                    ?? throw new DomainException('No se pudo registrar la asistencia. Inténtalo de nuevo.');

                return new ResultadoAsistenciaQr($existente, duplicado: true);
            }

            $abierta = $this->obligaciones->buscarPersonalAbiertaPorUsuario($trabajador->userId());

            if ($abierta !== null && $abierta->estaEnCurso()) {
                $this->recalcular->execute($abierta);
            }

            return new ResultadoAsistenciaQr($jornada, duplicado: false);
        });
    }
}
