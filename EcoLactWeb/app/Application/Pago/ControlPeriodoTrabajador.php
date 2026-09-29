<?php

namespace App\Application\Pago;

/**
 * Vista reusable del "Control del periodo" de un trabajador: la misma
 * implementación alimenta las páginas de perfil (acopiador, calidad,
 * encargado de planta, ventas, administrador) y el panel de Pagar a
 * trabajadores. Todos los montos vienen ya calculados por el backend
 * (ObtenerControlPeriodoTrabajador); esta clase es solo un contenedor de
 * datos para la vista, nunca recalcula nada.
 */
final readonly class ControlPeriodoTrabajador
{
    /** @param DiaCalendarioTrabajador[] $dias */
    public function __construct(
        public int $userId,
        public ?int $obligacionId,
        public string $estado,
        public string $estadoEtiqueta,
        public int $asistencias,
        public int $faltas,
        public float $sueldoMensual,
        public float $valorDia,
        public float $descuentoPorFaltas,
        public float $acumulado,
        public float $bonificacion,
        public float $descuento,
        public float $total,
        public bool $esMesCompleto,
        public ?string $fechaIngresoEtiqueta,
        public string $periodoEtiqueta,
        public string $periodoRango,
        public string $periodoInicioIso,
        public string $periodoFinIso,
        public string $mesValor,
        public string $mesAnteriorValor,
        public ?string $mesSiguienteValor,
        public array $dias,
        public ?string $qrToken,
        /** false para roles sin control de asistencia (ADMINISTRADOR): sueldo fijo, sin calendario ni faltas. */
        public bool $controlaAsistencia = true,
    ) {}

    public function estaEnCurso(): bool
    {
        return $this->estado === 'EN_CURSO';
    }

    public function estaPendiente(): bool
    {
        return $this->estado === 'PENDIENTE';
    }

    public function estaPagado(): bool
    {
        return $this->estado === 'PAGADO';
    }

    public function tieneObligacion(): bool
    {
        return $this->obligacionId !== null;
    }
}
