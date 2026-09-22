<?php

namespace App\Application\Planta;

use App\Domain\Comunicacion\Notificacion;
use App\Domain\Produccion\Insumo;
use App\Domain\Produccion\LoteProduccion;

final readonly class DashboardPlantaData
{
    /**
     * @param  array{enCurso: int, lotesProducidos: int, litrosUtilizados: float, productosProducidos: float}  $kpis
     * @param  list<LoteProduccion>  $produccionActual
     * @param  list<LoteProduccion>  $produccionReciente
     * @param  list<Insumo>  $insumosBajoMinimo
     * @param  array{total: int, suficiente: int, bajo: int, sinStock: int}  $inventario
     * @param  list<array{producto: string, stock: float, unidad: string}>  $productosDisponibles
     * @param  list<Notificacion>  $notificacionesRecientes
     * @param  array<int, float>  $litrosPorLote  leche consumida por lote (id de lote => litros)
     */
    public function __construct(
        public array $kpis,
        public array $produccionActual,
        public array $produccionReciente,
        public array $insumosBajoMinimo,
        public array $inventario,
        public array $productosDisponibles,
        public array $notificacionesRecientes,
        public array $litrosPorLote = [],
    ) {}
}
