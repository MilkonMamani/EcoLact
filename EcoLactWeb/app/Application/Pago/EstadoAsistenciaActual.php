<?php

namespace App\Application\Pago;

use App\Application\Shared\Reloj;
use App\Domain\Auth\Repositories\RoleRepository;
use App\Domain\Auth\Repositories\UsuarioRepository;
use App\Domain\Pago\JornadaPersonalRepository;
use App\Domain\Shared\Enums\CondicionAsistencia;
use App\Domain\Shared\Enums\EstadoJornada;
use App\Domain\Trabajador\TrabajadorRepository;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * ÚNICA regla de "¿puede operar hoy?" para todo el personal (web, API, sync):
 *
 *   rol que marca asistencia y es personal remunerado (no ADMINISTRADOR, no PROVEEDOR)
 *   + ficha de trabajador vigente ese día
 *   + jornada PRESENTE ese día (la registra el ADMINISTRADOR escaneando su QR)
 *   = puede operar.
 *
 * El día se toma en la zona horaria del negocio. Para operaciones hechas sin
 * conexión se evalúa el día del EVENTO (no el de la sincronización): un acopio
 * de las 09:35 con jornada PRESENTE ese día es válido aunque llegue a las 11:00
 * o al día siguiente. Nunca vale la asistencia de otro día.
 *
 * Reutiliza trabajadores, trabajador_periodos_actividad, jornadas_personal y
 * roles.controla_asistencia: no hay otra tabla de asistencia.
 */
final class EstadoAsistenciaActual
{
    public function __construct(
        private readonly UsuarioRepository $usuarios,
        private readonly RoleRepository $roles,
        private readonly TrabajadorRepository $trabajadores,
        private readonly JornadaPersonalRepository $jornadas,
        private readonly Reloj $reloj,
    ) {}

    /** @param  DateTimeInterface|null  $momento  Momento del evento; por defecto ahora. */
    public function paraUsuario(int $userId, ?DateTimeInterface $momento = null): EstadoAsistencia
    {
        $fecha = $this->diaDelNegocio($momento);

        if (! $this->requiereAsistencia($userId)) {
            return new EstadoAsistencia($fecha, false, CondicionAsistencia::NO_REQUERIDA);
        }

        $trabajador = $this->trabajadores->buscarPorUserId($userId);

        if ($trabajador === null) {
            return new EstadoAsistencia($fecha, true, CondicionAsistencia::NO_REGISTRADO);
        }

        $jornada = $this->jornadas->buscarEnFecha($trabajador->id(), $fecha);

        if ($jornada !== null) {
            return new EstadoAsistencia(
                $fecha,
                true,
                $jornada->estado() === EstadoJornada::PRESENTE ? CondicionAsistencia::TIENE_ASISTENCIA : CondicionAsistencia::FALTA,
                $jornada->horaMarcacion(),
                $trabajador->id(),
            );
        }

        $condicion = $trabajador->estaVigenteEn($fecha) ? CondicionAsistencia::SIN_ASISTENCIA : CondicionAsistencia::FUERA_DE_PERIODO_ACTIVO;

        return new EstadoAsistencia($fecha, true, $condicion, null, $trabajador->id());
    }

    /** Personal que marca asistencia: rol con controla_asistencia y remunerado (excluye ADMINISTRADOR y PROVEEDOR). */
    private function requiereAsistencia(int $userId): bool
    {
        $usuario = $this->usuarios->buscarPorId($userId);
        $rol = $usuario === null ? null : $this->roles->buscarPorId($usuario->roleId());

        return $rol !== null && $rol->controlaAsistencia() && $rol->esRemunerable();
    }

    /** Día calendario del negocio (00:00), en la misma forma que las fechas de jornadas_personal. */
    private function diaDelNegocio(?DateTimeInterface $momento): DateTimeImmutable
    {
        $local = $momento === null
            ? $this->reloj->ahora()
            : DateTimeImmutable::createFromInterface($momento)->setTimezone(new DateTimeZone(config('app.zona_horaria_negocio')));

        return new DateTimeImmutable($local->format('Y-m-d'));
    }
}
