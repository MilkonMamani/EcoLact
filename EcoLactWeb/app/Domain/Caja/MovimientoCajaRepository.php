<?php

namespace App\Domain\Caja;

interface MovimientoCajaRepository
{
    /**
     * Guarda el movimiento si su operación de origen todavía no tiene uno; si
     * ya lo tiene, devuelve el existente sin crear otro (idempotente).
     */
    public function guardarSiNoExiste(MovimientoCaja $movimiento): MovimientoCaja;

    public function buscarPorReferencia(string $referenciaTipo, int $referenciaId): ?MovimientoCaja;
}
