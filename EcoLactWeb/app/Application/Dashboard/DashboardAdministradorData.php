<?php

namespace App\Application\Dashboard;

/**
 * Datos del panel administrativo. Todo bloque temporal usa el mismo
 * DashboardPeriodo; "existencias" es la única excepción deliberada: es un
 * saldo actual (no existe historial de saldos) y así se presenta, igual que
 * "asistenciaHoy", que siempre mide el día actual.
 *
 * Los importes se suman como DECIMAL en SQL y se redondean una sola vez.
 * Devengos, saldos y pagos programados salen de ObtenerResumenFinanciero.
 */
final readonly class DashboardAdministradorData
{
    /**
     * @param  array{litros: float, entregas: int, proveedores: int}  $acopio
     * @param  array{total: int, porEstado: array<string, int>}  $produccion
     * @param  array{importe: float, ventas: int, contado: float, descuentoPlanilla: float}  $ventas
     * @param  list<array{clave: string, etiqueta: string, titulo: string, futuro: bool, litros: float|null, entregas: int|null}>  $acopioPorIntervalo
     * @param  list<array{nombre: string, litros: float, otras: bool}>  $acopioPorZona
     * @param  array{analisisPorConfirmar: int, insumosBajoMinimo: int, insumosSinStock: int, lotesEnProceso: int}  $porAtender
     * @param  array{leche: array{cantidad: float, unidad: string}|null, productos: list<array{id: int, nombre: string, cantidad: float, unidad: string}>, productosConStock: int, insumosBajoMinimo: int, insumosSinStock: int}  $existencias
     * @param  list<array{id: int, codigo: string, estado: string, fecha: string, productos: list<string>}>  $lotesRecientes
     * @param  array{modo: string, personal: int, presentes: int, faltas: int, sinMarcar: int, trabajadores: int, dias: int}  $asistencia
     * @param  array{modo: string, personal: int, presentes: int, faltas: int, sinMarcar: int, trabajadores: int, dias: int}  $asistenciaHoy
     * @param  array{publicados: int, recientes: list<array{id: int, titulo: string, fecha: string, alcance: string}>}  $comunicados
     */
    public function __construct(
        public DashboardPeriodo $periodo,
        public array $acopio,
        public array $produccion,
        public array $ventas,
        public ResumenFinanciero $finanzas,
        public array $acopioPorIntervalo,
        public array $acopioPorZona,
        public array $porAtender,
        public array $existencias,
        public array $lotesRecientes,
        public array $asistencia,
        public array $asistenciaHoy,
        public array $comunicados,
    ) {}
}
