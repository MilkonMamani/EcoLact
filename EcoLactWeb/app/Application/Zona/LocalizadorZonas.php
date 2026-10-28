<?php

namespace App\Application\Zona;

use App\Domain\Zona\Zona;
use App\Domain\Zona\ZonaRepository;

/**
 * Qué zona contiene un punto, según las delimitaciones guardadas (DelimitacionZona::contiene,
 * point-in-polygon en el servidor). Es la verificación que hacen "Mover ubicación" y el modal
 * "Asignar proveedor a zona y ruta" antes de guardar: lo que detectó el navegador solo orienta.
 */
final class LocalizadorZonas
{
    public function __construct(
        private readonly ZonaRepository $zonas,
    ) {}

    /**
     * Si dos delimitaciones se solapan y una de ellas es $preferidaId (p. ej. la zona actual del
     * proveedor), gana esa: un ajuste fino no lo cambia de zona. Null si ninguna lo contiene.
     */
    public function zonaEn(float $latitud, float $longitud, ?int $preferidaId = null): ?Zona
    {
        $contenedoras = array_keys(array_filter(
            $this->zonas->listarDelimitaciones(),
            fn ($delimitacion): bool => $delimitacion->contiene($latitud, $longitud),
        ));

        if ($contenedoras === []) {
            return null;
        }

        return $this->zonas->buscarPorId(in_array($preferidaId, $contenedoras, true) ? $preferidaId : $contenedoras[0]);
    }
}
