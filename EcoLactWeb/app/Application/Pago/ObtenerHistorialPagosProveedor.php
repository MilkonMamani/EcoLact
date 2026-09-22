<?php

namespace App\Application\Pago;

use App\Application\Pago\Contracts\PagoSemanalProveedoresQuery;

/**
 * Historial de pagos de un proveedor para el modal "Ver pagos" de su detalle.
 * Consulta los mismos registros y cifras históricas que Pagar a proveedores.
 */
final class ObtenerHistorialPagosProveedor
{
    public const POR_PAGINA = 8;

    public function __construct(private readonly PagoSemanalProveedoresQuery $consulta) {}

    /**
     * @param  string|null  $semana  fecha de inicio de la semana (Y-m-d) o "todos"; sin valor, todas
     * @param  string|null  $estado  todos | pendientes | pagados
     * @return array<string, mixed> ver PagoSemanalProveedoresQuery::historialProveedor()
     */
    public function execute(int $proveedorId, ?string $semana = null, ?string $estado = null, int $pagina = 1): array
    {
        $filtro = PagoSemanalFiltro::resolver($semana, null, $estado);

        return $this->consulta->historialProveedor($proveedorId, $filtro, $pagina, self::POR_PAGINA) + [
            'estado' => $filtro->estado,
            'semana' => $filtro->semana,
        ];
    }
}
