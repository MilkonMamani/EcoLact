<?php

namespace App\Domain\Pago;

interface PagoRealizadoRepository
{
    public function guardar(PagoRealizado $pago): PagoRealizado;

    public function buscarPorObligacionId(int $obligacionId): ?PagoRealizado;
}
