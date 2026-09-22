<?php

namespace App\Domain\Acopiador;

use DateTimeImmutable;

interface AcopiadorZonaHistorialRepository
{
    public function guardar(AcopiadorZonaHistorial $historial): AcopiadorZonaHistorial;

    public function buscarVigentePorAcopiador(int $acopiadorId): ?AcopiadorZonaHistorial;

    public function cerrarVigente(int $acopiadorId, DateTimeImmutable $fechaFin): void;

    /**
     * @return AcopiadorZonaHistorial[]
     */
    public function listarPorAcopiador(int $acopiadorId): array;
}
