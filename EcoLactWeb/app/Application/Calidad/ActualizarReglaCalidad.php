<?php

namespace App\Application\Calidad;

use App\Application\Shared\CampoActualizable;
use App\Domain\Calidad\ReglaCalidad;
use App\Domain\Calidad\ReglaCalidadRepository;
use App\Domain\Shared\Enums\ResultadoCalidad;
use DateTimeImmutable;
use DomainException;

/**
 * Actualiza una regla de calidad existente. Solo modifica los campos que
 * el administrador envía; el resto conserva su valor actual. No inventa ni
 * recalcula límites: los valores siguen viniendo del administrador.
 *
 * valor_minimo, valor_maximo y fecha_fin son columnas genuinamente nullable
 * en el esquema (una regla puede no tener límite inferior/superior, o no
 * tener fecha de cierre). Para esos tres campos, "no enviar el parámetro"
 * (mantener el valor actual) y "enviarlo explícitamente como null" (limpiar
 * el campo, p. ej. reabrir la vigencia quitando la fecha_fin) son cosas
 * distintas que `?float $x = null` no puede diferenciar. Por eso se reciben
 * envueltos en CampoActualizable: si el parámetro completo es null significa
 * "no tocar"; si es un CampoActualizable, su valor interno (aunque sea null)
 * es el que se aplica.
 *
 * El parámetro evaluado (parametro_id) no se admite como editable: una
 * regla existe para un parámetro concreto; para evaluar otro parámetro
 * corresponde registrar una regla nueva.
 *
 * @param  CampoActualizable<float|null>|null  $valorMinimo
 * @param  CampoActualizable<float|null>|null  $valorMaximo
 * @param  CampoActualizable<DateTimeImmutable|null>|null  $fechaFin
 */
final class ActualizarReglaCalidad
{
    public function __construct(
        private readonly ReglaCalidadRepository $reglasCalidad,
    ) {}

    public function execute(
        int $reglaId,
        ?CampoActualizable $valorMinimo = null,
        ?CampoActualizable $valorMaximo = null,
        ?ResultadoCalidad $resultadoIncumplimiento = null,
        ?int $prioridad = null,
        ?DateTimeImmutable $fechaInicio = null,
        ?CampoActualizable $fechaFin = null,
        ?string $estado = null,
    ): ReglaCalidad {
        $regla = $this->reglasCalidad->buscarPorId($reglaId);

        if ($regla === null) {
            throw new DomainException('La regla de calidad indicada no existe.');
        }

        $nuevaFechaInicio = $fechaInicio ?? $regla->fechaInicio();
        $nuevaFechaFin = CampoActualizable::resolver($fechaFin, $regla->fechaFin());

        if ($nuevaFechaFin !== null && $nuevaFechaFin < $nuevaFechaInicio) {
            throw new DomainException('La fecha fin de la regla no puede ser anterior a la fecha inicio.');
        }

        return $this->reglasCalidad->actualizar(new ReglaCalidad(
            id: $regla->id(),
            parametroId: $regla->parametroId(),
            valorMinimo: CampoActualizable::resolver($valorMinimo, $regla->valorMinimo()),
            valorMaximo: CampoActualizable::resolver($valorMaximo, $regla->valorMaximo()),
            resultadoIncumplimiento: $resultadoIncumplimiento ?? $regla->resultadoIncumplimiento(),
            prioridad: $prioridad ?? $regla->prioridad(),
            fechaInicio: $nuevaFechaInicio,
            fechaFin: $nuevaFechaFin,
            estado: $estado ?? $regla->estado(),
        ));
    }
}
