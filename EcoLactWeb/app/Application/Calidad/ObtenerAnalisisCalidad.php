<?php

namespace App\Application\Calidad;

use App\Domain\Calidad\AnalisisCalidad;
use App\Domain\Calidad\AnalisisCalidadRepository;

final class ObtenerAnalisisCalidad
{
    public function __construct(
        private readonly AnalisisCalidadRepository $analisisCalidad,
    ) {}

    public function porId(int $analisisId): ?AnalisisCalidad
    {
        return $this->analisisCalidad->buscarPorId($analisisId);
    }

    public function porEntrega(int $entregaId): ?AnalisisCalidad
    {
        return $this->analisisCalidad->buscarPorEntregaId($entregaId);
    }

    /**
     * @return AnalisisCalidad[]
     */
    public function listar(): array
    {
        return $this->analisisCalidad->listar();
    }
}
