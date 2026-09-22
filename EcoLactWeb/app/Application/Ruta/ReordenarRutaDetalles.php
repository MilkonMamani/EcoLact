<?php

namespace App\Application\Ruta;

use App\Domain\Ruta\RutaDetalle;
use App\Domain\Ruta\RutaDetalleRepository;
use App\Domain\Ruta\RutaRepository;
use DomainException;

/**
 * Reordena las paradas vigentes de una ruta. Recibe el nuevo orden como una
 * lista de IDs de ruta_detalle en el orden deseado (posición 0 = primera
 * parada) y delega el detalle transaccional (evitar el conflicto temporal
 * del índice único orden_visita) al Repository.
 */
final class ReordenarRutaDetalles
{
    public function __construct(
        private readonly RutaRepository $rutas,
        private readonly RutaDetalleRepository $rutaDetalles,
    ) {}

    /**
     * @param  int[]  $ordenDeDetalleIds
     */
    public function execute(int $rutaId, array $ordenDeDetalleIds): void
    {
        if ($this->rutas->buscarPorId($rutaId) === null) {
            throw new DomainException('La ruta indicada no existe.');
        }

        $detallesVigentes = $this->rutaDetalles->listarPorRuta($rutaId);
        $idsVigentes = array_map(fn (RutaDetalle $d): int => $d->id(), $detallesVigentes);

        sort($idsVigentes);
        $idsRecibidos = $ordenDeDetalleIds;
        sort($idsRecibidos);

        if ($idsVigentes !== $idsRecibidos) {
            throw new DomainException('El nuevo orden debe incluir exactamente a todos los proveedores vigentes de la ruta, sin repetir ni omitir ninguno.');
        }

        $ordenPorDetalleId = [];
        foreach ($ordenDeDetalleIds as $posicion => $detalleId) {
            $ordenPorDetalleId[$detalleId] = $posicion + 1;
        }

        $this->rutaDetalles->reordenar($rutaId, $ordenPorDetalleId);
    }
}
