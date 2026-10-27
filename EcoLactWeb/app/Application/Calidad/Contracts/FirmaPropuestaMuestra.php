<?php

namespace App\Application\Calidad\Contracts;

/**
 * Prueba de que una muestra ALEATORIA salió del sorteo del servidor: el
 * cliente (Web o móvil) no puede declarar "aleatoria" una entrega elegida a
 * mano. La firma ata la entrega al analista y vence tras una jornada, así una
 * confirmación hecha sin señal y sincronizada horas después sigue valiendo.
 */
interface FirmaPropuestaMuestra
{
    public function firmar(int $entregaId, int $analistaUserId): string;

    public function esValida(?string $token, int $entregaId, int $analistaUserId): bool;
}
