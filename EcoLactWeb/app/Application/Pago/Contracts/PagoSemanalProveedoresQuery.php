<?php

namespace App\Application\Pago\Contracts;

use App\Application\Pago\ComprobantePagoProveedor;
use App\Application\Pago\PagoSemanalFiltro;
use DateTimeImmutable;

/**
 * Lectura de la pantalla "Pago semanal a proveedores" y de los comprobantes
 * individuales. Solo consulta pagos que ya existen (los genera Configuración de pagos).
 */
interface PagoSemanalProveedoresQuery
{
    /**
     * `semanas` son las semanas reales (únicas, la más reciente primero); "Todos"
     * lo añade la pantalla. Con `todas` el listado abarca todo el historial y
     * `semana` describe esa opción. Las filas incluyen pendientes y pagados según el
     * filtro de estado; los KPIs solo cuentan las pendientes de esas filas.
     *
     * @return array{
     *     semanas: array<int, array{inicio: string, etiqueta: string, rango: string, pendientes: int}>,
     *     semana: array{inicio: string, etiqueta: string, rango: string},
     *     todas: bool,
     *     pendientesOtrasSemanas: int,
     *     zonas: array<int, array{id: int, nombre: string}>,
     *     kpis: array{pendientes: int, litros: float, totalPendiente: float},
     *     filas: array<int, array<string, mixed>>
     * }
     */
    public function listado(PagoSemanalFiltro $filtro, DateTimeImmutable $hoy): array;

    /**
     * Historial de pagos de UN proveedor (detalle del proveedor → "Ver pagos"): la misma
     * fuente y las mismas cifras históricas que la pantalla de Pagar a proveedores. El
     * filtro usa `semana` (null = todas) y `estado`; la zona no interviene.
     *
     * @return array{
     *     semanas: array<int, array{inicio: string, etiqueta: string, rango: string, pendientes: int}>,
     *     filas: array<int, array<string, mixed>>,
     *     total: int,
     *     totalProveedor: int,
     *     pagina: int,
     *     paginas: int,
     *     desde: int,
     *     hasta: int
     * }
     */
    public function historialProveedor(int $proveedorId, PagoSemanalFiltro $filtro, int $pagina, int $porPagina): array;

    /**
     * Cuántos pagos tiene el proveedor y cuántos siguen pendientes.
     *
     * @return array{total: int, pendientes: int}
     */
    public function resumenProveedor(int $proveedorId): array;

    /**
     * Comprobantes en el orden pedido. Los pagos ya realizados devuelven la copia
     * guardada al confirmar; los pendientes se calculan al momento. Ignora los ids
     * que no sean pagos de proveedor.
     *
     * @param  int[]  $obligacionIds
     * @return ComprobantePagoProveedor[]
     */
    public function comprobantes(array $obligacionIds): array;
}
