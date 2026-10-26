<?php

namespace App\Domain\Shared\Enums;

enum ResultadoCalidad: string
{
    case ACEPTADA = 'ACEPTADA';
    case OBSERVADA = 'OBSERVADA';
    case RECHAZADA = 'RECHAZADA';

    /**
     * El peor resultado de un conjunto (RECHAZADA > OBSERVADA > ACEPTADA): la
     * liquidación semanal del proveedor se paga según el peor análisis de la
     * semana. Los nulos (entregas sin análisis confirmado) se ignoran; null si
     * no hay ningún resultado.
     *
     * @param  iterable<self|null>  $resultados
     */
    public static function peor(iterable $resultados): ?self
    {
        $peor = null;

        foreach ($resultados as $resultado) {
            if ($resultado !== null && ($peor === null || $resultado->gravedad() > $peor->gravedad())) {
                $peor = $resultado;
            }
        }

        return $peor;
    }

    private function gravedad(): int
    {
        return match ($this) {
            self::ACEPTADA => 0,
            self::OBSERVADA => 1,
            self::RECHAZADA => 2,
        };
    }
}
