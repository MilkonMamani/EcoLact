<?php

namespace App\Domain\Produccion;

interface InsumoMovimientoRepository
{
    public function guardar(InsumoMovimiento $movimiento): InsumoMovimiento;

    /**
     * @return InsumoMovimiento[]
     */
    public function listarPorInsumo(int $insumoId): array;

    /**
     * Movimientos generados por un registro de origen (ej. ENTREGA #12), del
     * más antiguo al más reciente.
     *
     * @return InsumoMovimiento[]
     */
    public function listarPorOrigen(string $origenTipo, int $origenId): array;

    /**
     * @return InsumoMovimiento[]
     */
    public function listar(): array;

    /**
     * Fecha del movimiento más reciente de cada insumo, en una consulta.
     *
     * @return array<int, \DateTimeImmutable> insumo_id => fecha
     */
    public function ultimoMovimientoPorInsumo(): array;
}
