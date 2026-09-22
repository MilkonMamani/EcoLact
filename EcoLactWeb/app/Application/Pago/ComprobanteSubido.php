<?php

namespace App\Application\Pago;

/** Archivo de comprobante recibido (ya validado por el FormRequest) antes de guardarse en Storage. */
final class ComprobanteSubido
{
    public function __construct(
        public readonly string $rutaTemporal,
        public readonly string $extension,
        public readonly string $nombreOriginal,
    ) {}
}
