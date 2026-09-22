<?php

namespace App\Application\Recorrido;

final readonly class RecorridoVista
{
    /**
     * @param  ParadaVista[]  $paradas
     * @param  array{recolectados: int, pendientes: int, no_entrego: int, litros_validos: float}  $progreso
     */
    public function __construct(
        public int $id,
        public int $rutaId,
        public string $rutaNombre,
        public string $zonaNombre,
        public string $estado,
        public string $fechaOperativa,
        public array $paradas,
        public array $progreso,
    ) {}
}
