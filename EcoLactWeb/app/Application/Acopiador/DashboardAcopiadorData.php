<?php

namespace App\Application\Acopiador;

use App\Domain\Comunicacion\Comunicado;
use App\Domain\Entrega\Entrega;

final readonly class DashboardAcopiadorData
{
    /**
     * @param  array{entregas: int, litros: float, proveedoresAtendidos: int, pendientesAnalisis: int}  $kpis
     * @param  list<Entrega>  $ultimasEntregas
     * @param  array<int, ?string>  $resultadosPorEntrega
     * @param  list<array{proveedorId: int, nombre: string, entregas: int}>  $proveedoresAtendidos
     * @param  list<Comunicado>  $comunicadosRecientes
     */
    public function __construct(
        public array $kpis,
        public array $ultimasEntregas,
        public array $resultadosPorEntrega,
        public array $proveedoresAtendidos,
        public array $comunicadosRecientes,
    ) {}
}
