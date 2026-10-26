<?php

namespace App\Application\Calidad;

use App\Domain\Calidad\ParametroCalidad;
use App\Domain\Calidad\ParametroCalidadRepository;
use DomainException;

/**
 * Permite al ADMINISTRADOR registrar un nuevo parámetro de calidad
 * (QUÉ se mide: código técnico, nombre, unidad y una descripción corta). Los límites de evaluación
 * (valor_minimo/valor_maximo) NO se configuran aquí: eso es ReglaCalidad.
 */
final class RegistrarParametroCalidad
{
    public function __construct(
        private readonly ParametroCalidadRepository $parametros,
    ) {}

    public function execute(
        string $codigo,
        string $nombre,
        ?string $unidad,
        string $estado,
        ?string $descripcion = null,
    ): ParametroCalidad {
        if ($this->parametros->buscarPorCodigo($codigo) !== null) {
            throw new DomainException("Ya existe un parámetro de calidad con el código {$codigo}.");
        }

        return $this->parametros->guardar(new ParametroCalidad(
            id: null,
            codigo: $codigo,
            nombre: $nombre,
            unidad: $unidad,
            estado: $estado,
            descripcion: $descripcion,
        ));
    }
}
