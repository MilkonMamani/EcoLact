<?php

namespace App\Application\Dashboard;

final readonly class DashboardAdministradorData
{
    /**
     * Los importes se suman como DECIMAL en SQL y se convierten una sola vez
     * para presentación, siguiendo la estrategia existente del proyecto.
     *
     * @param  array{totalAcopiado: float, proveedoresAtendidos: int, proveedoresActivos: int, porcentajeAtendidos: float, calidadAceptada: float, analisisConfirmados: int, entregasObservadas: int, totalValorizado: float, produccion: int, produccionPorEstado: array<string, int>}  $kpis
     * @param  list<array{fecha: string, etiqueta: string, litros: float, entregas: int}>  $litrosPorDia
     * @param  list<array{resultado: string, cantidad: int, porcentaje: float, litros: float}>  $distribucionCalidad
     * @param  list<array{id: int, nombre: string, proveedores: int, litros: float, confirmados: int, calidad: float}>  $rendimientoZonas
     * @param  list<array{id: int, fecha: string, proveedorNombre: string, zonaNombre: string, litros: float, calidad: ?string, estado: string}>  $entregasRecientes
     * @param  list<array{tipo: string, id: int, titulo: string, detalle: string, fecha: string}>  $actividadReciente
     * @param  array{id: int, codigo: string, estado: string, fecha: string, litros: float, productos: list<array{nombre: string, unidad: string, cantidad: float}>}|null  $produccionReciente
     * @param  array{publicados: int, destinatarios: int, leidos: int, tasaLectura: ?float, recientes: list<array{id: int, titulo: string, fecha: string, alcance: string}>}  $comunicaciones
     */
    public function __construct(
        public DashboardPeriodo $periodo,
        public array $kpis,
        public array $litrosPorDia,
        public array $distribucionCalidad,
        public array $rendimientoZonas,
        public array $entregasRecientes,
        public array $actividadReciente,
        public ?array $produccionReciente,
        public array $comunicaciones,
    ) {}
}
