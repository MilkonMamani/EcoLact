<?php

namespace App\Application\Zona;

use App\Domain\Zona\ZonaRepository;
use DomainException;

/**
 * Elimina una zona solo si no deja datos huérfanos ni historial roto: no
 * debe tener rutas, proveedores/acopiadores asignados, historial de
 * asignaciones, entregas ni comunicados asociados. Si los tiene, la
 * eliminación se rechaza y se indica qué la bloquea (la alternativa segura
 * es marcar la zona como INACTIVA desde "Editar zona").
 */
final class EliminarZona
{
    public function __construct(
        private readonly ZonaRepository $zonas,
    ) {}

    public function execute(int $zonaId): void
    {
        $zona = $this->zonas->buscarPorId($zonaId);

        if ($zona === null) {
            throw new DomainException('La zona indicada no existe.');
        }

        $bloqueos = array_filter($this->zonas->contarDependencias($zonaId));

        if ($bloqueos !== []) {
            $detalle = implode(', ', array_map(
                fn (string $etiqueta, int $cantidad): string => "{$cantidad} {$etiqueta}",
                array_keys($bloqueos),
                $bloqueos,
            ));

            throw new DomainException("No se puede eliminar la zona {$zona->nombre()} porque tiene {$detalle}. Puedes marcarla como inactiva en su lugar.");
        }

        $this->zonas->eliminar($zonaId);
    }
}
