<?php

namespace App\Application\Calidad;

use App\Domain\Calidad\AnalisisCalidad;
use App\Domain\Calidad\AnalisisCalidadRepository;
use App\Domain\Entrega\EntregaRepository;

/**
 * Lista los análisis de calidad de las entregas de una zona (uso del
 * ACOPIADOR: seguimiento de calidad de lo recolectado en su zona). No hay
 * un método de repositorio "listar análisis por zona" porque
 * analisis_calidad no tiene zona_id propio (se relaciona vía entrega_id),
 * igual que ObtenerAnalisisProveedor resuelve los suyos entrega por
 * entrega.
 */
final class ObtenerAnalisisPorZona
{
    public function __construct(
        private readonly EntregaRepository $entregas,
        private readonly AnalisisCalidadRepository $analisisCalidad,
    ) {}

    /**
     * @return AnalisisCalidad[]
     */
    public function execute(int $zonaId): array
    {
        $analisis = [];

        foreach ($this->entregas->listarPorZona($zonaId) as $entrega) {
            $unAnalisis = $this->analisisCalidad->buscarPorEntregaId($entrega->id());

            if ($unAnalisis !== null) {
                $analisis[] = $unAnalisis;
            }
        }

        return $analisis;
    }
}
