<?php

namespace App\Application\Zona;

use App\Domain\Zona\DelimitacionZona;
use App\Domain\Zona\ZonaRepository;
use DomainException;
use InvalidArgumentException;

/**
 * Guarda el color de la zona en el mapa y su delimitación operativa (el
 * polígono que el administrador dibuja en "Editar zona"). Una geometría
 * null o vacía quita la delimitación; una geometría inválida (menos de 3
 * puntos, lados que se cruzan, JSON roto) se rechaza sin tocar la zona.
 */
final class GuardarDelimitacionZona
{
    public function __construct(private readonly ZonaRepository $zonas) {}

    public function execute(int $zonaId, string $colorMapa, ?string $poligonoGeoJson): void
    {
        if ($this->zonas->buscarPorId($zonaId) === null) {
            throw new DomainException('La zona indicada no existe.');
        }

        if (preg_match(ColorZona::PATRON, $colorMapa) !== 1) {
            throw new DomainException('El color del mapa debe tener el formato #RRGGBB.');
        }

        try {
            $delimitacion = $poligonoGeoJson !== null && trim($poligonoGeoJson) !== ''
                ? DelimitacionZona::desdeGeoJson($poligonoGeoJson)
                : null;
        } catch (InvalidArgumentException $error) {
            throw new DomainException($error->getMessage());
        }

        $this->zonas->guardarDelimitacion($zonaId, strtolower($colorMapa), $delimitacion);
    }
}
