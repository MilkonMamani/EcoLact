<?php

namespace App\Application\Calidad;

use App\Domain\Calidad\AnalisisCalidadDetalleRepository;
use App\Domain\Calidad\AnalisisCalidadRepository;
use App\Domain\Calidad\ReglaCalidadRepository;
use App\Domain\Shared\Enums\ResultadoCalidad;
use DomainException;

/**
 * Compara los valores registrados de un análisis contra las reglas de
 * calidad vigentes (configuradas por el ADMINISTRADOR) y determina el
 * resultado. No persiste nada: es un cálculo puro de lectura.
 *
 * Si no existen reglas vigentes suficientes para evaluar todos los valores
 * registrados, no se inventa un resultado: se lanza una excepción de
 * dominio indicando qué falta configurar.
 */
final class CalcularResultadoCalidad
{
    public function __construct(
        private readonly AnalisisCalidadRepository $analisisCalidad,
        private readonly AnalisisCalidadDetalleRepository $detalles,
        private readonly ReglaCalidadRepository $reglasCalidad,
    ) {}

    public function execute(int $analisisId): ResultadoCalidad
    {
        $analisis = $this->analisisCalidad->buscarPorId($analisisId);

        if ($analisis === null) {
            throw new DomainException('El análisis de calidad indicado no existe.');
        }

        $detalles = $this->detalles->listarPorAnalisis($analisisId);

        if ($detalles === []) {
            throw new DomainException('No hay valores registrados para calcular el resultado del análisis.');
        }

        $incumplimientos = [];

        foreach ($detalles as $detalle) {
            $regla = $this->reglasCalidad->buscarVigentePorParametro($detalle->parametroId(), $analisis->fechaAnalisis());

            if ($regla === null) {
                throw new DomainException(
                    "No existe una regla de calidad vigente configurada para el parámetro con id {$detalle->parametroId()}."
                );
            }

            if (! $regla->cumple($detalle->valor())) {
                $incumplimientos[] = $regla->resultadoIncumplimiento();
            }
        }

        if (in_array(ResultadoCalidad::RECHAZADA, $incumplimientos, true)) {
            return ResultadoCalidad::RECHAZADA;
        }

        if (in_array(ResultadoCalidad::OBSERVADA, $incumplimientos, true)) {
            return ResultadoCalidad::OBSERVADA;
        }

        return ResultadoCalidad::ACEPTADA;
    }
}
