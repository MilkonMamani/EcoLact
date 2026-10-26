<?php

namespace App\Application\Produccion;

/**
 * Cuánto de un insumo necesita una producción y cuánto hay disponible, ambos
 * en la unidad base del insumo.
 */
final readonly class RequerimientoInsumo
{
    public function __construct(
        public int $insumoId,
        public string $nombre,
        public string $unidadBase,
        public float $necesario,
        public float $disponible,
        public bool $esLeche = false,
    ) {}

    public function suficiente(): bool
    {
        return $this->disponible + 0.00005 >= $this->necesario;
    }

    public function faltante(): float
    {
        return $this->suficiente() ? 0.0 : round($this->necesario - $this->disponible, 4);
    }

    /**
     * Mensaje para una persona no técnica.
     */
    public function mensajeFaltante(): string
    {
        return sprintf(
            'No hay suficiente %s. Se necesitan %s %s y hay %s %s disponibles.',
            $this->esLeche ? 'leche' : $this->nombre,
            RegistrarMovimientoInsumo::formato($this->necesario),
            $this->unidadBase,
            RegistrarMovimientoInsumo::formato($this->disponible),
            $this->unidadBase,
        );
    }

    /**
     * @return array{insumo_id: int, nombre: string, unidad: string, necesario: float, disponible: float, faltante: float, suficiente: bool, es_leche: bool}
     */
    public function toArray(): array
    {
        return [
            'insumo_id' => $this->insumoId,
            'nombre' => $this->nombre,
            'unidad' => $this->unidadBase,
            'necesario' => $this->necesario,
            'disponible' => $this->disponible,
            'faltante' => $this->faltante(),
            'suficiente' => $this->suficiente(),
            'es_leche' => $this->esLeche,
        ];
    }
}
