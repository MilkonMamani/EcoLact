<?php

namespace App\Application\Comunicacion;

use App\Domain\Comunicacion\Comunicado;

/**
 * @phpstan-type DestinatarioFila array{userId: int, nombre: string, dni: ?string, leido: bool, fechaLectura: ?string}
 */
final readonly class ComunicadoDetalleData
{
    /**
     * @param  list<DestinatarioFila>  $destinatarios
     */
    public function __construct(
        public Comunicado $comunicado,
        public array $destinatarios,
        public int $leidos,
        public int $noLeidos,
    ) {}
}
