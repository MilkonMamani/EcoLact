<?php

namespace App\Application\Calidad;

use App\Domain\Calidad\AnalisisCalidad;
use App\Domain\Calidad\AnalisisCalidadRepository;

final class ObtenerAnalisisPorProveedor
{
    public function __construct(
        private readonly AnalisisCalidadRepository $analisisCalidad,
    ) {}

    /**
     * @return AnalisisCalidad[]
     */
    public function execute(int $proveedorId): array
    {
        return $this->analisisCalidad->listarPorProveedorId($proveedorId);
    }
}
