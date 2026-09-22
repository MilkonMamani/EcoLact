<?php

namespace App\Application\Comunicacion;

use App\Domain\Comunicacion\Repositories\ComunicadoRepository;

/**
 * Idempotente: si el usuario no es destinatario del comunicado, o ya
 * estaba marcado como leído, no hace nada.
 */
final class MarcarComunicadoComoLeido
{
    public function __construct(
        private readonly ComunicadoRepository $comunicados,
    ) {}

    public function execute(int $comunicadoId, int $userId): void
    {
        $lectura = $this->comunicados->buscarLecturaDestinatario($comunicadoId, $userId);

        if ($lectura === null || $lectura['leido']) {
            return;
        }

        $this->comunicados->marcarComoLeido($comunicadoId, $userId);
    }
}
