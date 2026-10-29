<?php

namespace App\Application\Pago;

use App\Domain\Pago\JornadaPersonal;
use App\Domain\Pago\JornadaPersonalRepository;
use App\Domain\Trabajador\TrabajadorRepository;

/**
 * Asistencia vista por el propio trabajador (solo lectura). La asistencia
 * la registra el ADMINISTRADOR escaneando el QR del trabajador
 * (RegistrarAsistenciaQr); aquí solo se entrega ese QR para mostrarlo en el
 * teléfono y el historial del mes. El trabajador nunca marca su propia
 * asistencia ni elige la hora: eso protege las reglas de la nómina.
 */
final class ObtenerMiAsistencia
{
    public function __construct(
        private readonly TrabajadorRepository $trabajadores,
        private readonly JornadaPersonalRepository $jornadas,
        private readonly VentanaAsistencia $ventana,
    ) {}

    /** @return array<string, mixed> */
    public function execute(int $userId): array
    {
        $trabajador = $this->trabajadores->buscarPorUserId($userId);
        $hoy = $this->ventana->hoy();

        if ($trabajador === null) {
            return [
                'registrado' => false,
                'trabajador_id' => null,
                'user_id' => $userId,
                'nombres' => null,
                'apellidos' => null,
                'dni' => null,
                'qr_token' => null,
                'controla_asistencia' => false,
                'hora_cierre' => VentanaAsistencia::horaCierre(),
                'abierta_hoy' => false,
                'hoy' => $hoy->format('Y-m-d'),
                'jornadas' => [],
            ];
        }

        $jornadas = $this->jornadas->listarEnPeriodo($trabajador->id(), $hoy->modify('first day of this month'), $hoy);
        usort($jornadas, static fn (JornadaPersonal $a, JornadaPersonal $b): int => $b->fecha() <=> $a->fecha());

        return [
            'registrado' => true,
            // Identidad única del trabajador (la misma de pagos e historial laboral): la app la
            // guarda para mostrar "Mi QR" sin conexión.
            'trabajador_id' => $trabajador->id(),
            'user_id' => $userId,
            'nombres' => $trabajador->nombres(),
            'apellidos' => $trabajador->apellidos(),
            'dni' => $trabajador->dni(),
            'qr_token' => $trabajador->estaActivo() ? $trabajador->qrToken() : null,
            'controla_asistencia' => $this->trabajadores->controlaAsistencia($trabajador->id()),
            'hora_cierre' => VentanaAsistencia::horaCierre(),
            'abierta_hoy' => $this->ventana->estaAbierta($hoy),
            'hoy' => $hoy->format('Y-m-d'),
            'jornadas' => array_map(static fn (JornadaPersonal $j): array => [
                'fecha' => $j->fecha()->format('Y-m-d'),
                'estado' => $j->estado()->value,
                'origen' => $j->origen()->value,
                'hora_marcacion' => $j->horaMarcacion()?->format(DATE_ATOM),
            ], $jornadas),
        ];
    }
}
