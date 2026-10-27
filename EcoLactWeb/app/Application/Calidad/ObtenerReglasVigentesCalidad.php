<?php

namespace App\Application\Calidad;

use App\Domain\Calidad\ParametroCalidadRepository;
use App\Domain\Calidad\ReglaCalidadRepository;
use DateTimeImmutable;

/**
 * Regla vigente de cada parámetro activo, para que el móvil PREVISUALICE el
 * resultado (incluso sin conexión). Es solo una vista previa: el resultado
 * que cuenta lo calcula FinalizarAnalisisCalidad con estas mismas reglas.
 */
final class ObtenerReglasVigentesCalidad
{
    public function __construct(
        private readonly ParametroCalidadRepository $parametros,
        private readonly ReglaCalidadRepository $reglas,
    ) {}

    /**
     * @return list<array{codigo: string, valor_minimo: ?float, valor_maximo: ?float, resultado_incumplimiento: string}>
     */
    public function execute(?DateTimeImmutable $fecha = null): array
    {
        $fecha ??= new DateTimeImmutable;
        $vigentes = [];

        foreach ($this->parametros->listarActivos() as $parametro) {
            $regla = $parametro->id() !== null ? $this->reglas->buscarVigentePorParametro($parametro->id(), $fecha) : null;

            if ($regla === null) {
                continue;
            }

            $vigentes[] = [
                'codigo' => $parametro->codigo(),
                'valor_minimo' => $regla->valorMinimo(),
                'valor_maximo' => $regla->valorMaximo(),
                'resultado_incumplimiento' => $regla->resultadoIncumplimiento()->value,
            ];
        }

        return $vigentes;
    }
}
