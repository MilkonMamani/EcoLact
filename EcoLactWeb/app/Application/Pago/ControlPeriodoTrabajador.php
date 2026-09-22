<?php

namespace App\Application\Pago;

/**
 * Vista reusable del "Control del periodo" de un trabajador: la misma
 * implementación alimenta las páginas de perfil (acopiador, calidad,
 * encargado de planta, ventas, administrador) y el panel de Pagar a
 * trabajadores.
 */
final readonly class ControlPeriodoTrabajador
{
    public function __construct(
        public int $userId,
        public ?int $obligacionId,
        public string $estado,
        public string $estadoEtiqueta,
        public int $diasTrabajados,
        public int $diasBase,
        public int $diasFaltantes,
        public float $sueldoMensual,
        public float $valorDia,
        public float $acumulado,
        public string $periodoEtiqueta,
        public string $periodoRango,
        public string $periodoInicioIso,
        public string $periodoFinIso,
    ) {}

    public function estaEnCurso(): bool
    {
        return $this->estado === 'EN_CURSO';
    }

    public function estaPagado(): bool
    {
        return $this->estado === 'PAGADO';
    }
}
