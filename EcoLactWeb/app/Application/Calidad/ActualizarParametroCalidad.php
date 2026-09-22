<?php

namespace App\Application\Calidad;

use App\Application\Shared\CampoActualizable;
use App\Domain\Calidad\ParametroCalidad;
use App\Domain\Calidad\ParametroCalidadRepository;
use App\Domain\Calidad\ReglaCalidadRepository;
use DomainException;

/**
 * Actualiza nombre/unidad/estado de un parámetro de calidad existente.
 *
 * El código técnico (codigo) NUNCA se admite como editable en este flujo:
 * es el identificador estable que da significado histórico a los análisis
 * y reglas ya registrados (GRASA no puede convertirse después en PROTEINA).
 *
 * Si se intenta dejar el parámetro INACTIVO mientras tiene reglas de
 * calidad en estado ACTIVO asociadas, se rechaza: primero deben
 * desactivarse esas reglas, para no dejar una configuración incoherente
 * (una regla evaluando un parámetro que ya no se mide).
 *
 * @param  CampoActualizable<string|null>|null  $unidad
 */
final class ActualizarParametroCalidad
{
    public function __construct(
        private readonly ParametroCalidadRepository $parametros,
        private readonly ReglaCalidadRepository $reglasCalidad,
    ) {}

    public function execute(
        int $parametroId,
        ?string $nombre = null,
        ?CampoActualizable $unidad = null,
        ?string $estado = null,
    ): ParametroCalidad {
        $parametro = $this->parametros->buscarPorId($parametroId);

        if ($parametro === null) {
            throw new DomainException('El parámetro de calidad indicado no existe.');
        }

        $nuevoEstado = $estado ?? $parametro->estado();

        if ($nuevoEstado === 'INACTIVO') {
            $reglasActivas = array_filter(
                $this->reglasCalidad->listarPorParametro($parametroId),
                fn ($regla): bool => $regla->estado() === 'ACTIVO',
            );

            if ($reglasActivas !== []) {
                throw new DomainException(
                    'No se puede desactivar el parámetro: tiene reglas de calidad activas asociadas. Desactívelas primero.'
                );
            }
        }

        return $this->parametros->actualizar(new ParametroCalidad(
            id: $parametro->id(),
            codigo: $parametro->codigo(),
            nombre: $nombre ?? $parametro->nombre(),
            unidad: CampoActualizable::resolver($unidad, $parametro->unidad()),
            estado: $nuevoEstado,
        ));
    }
}
