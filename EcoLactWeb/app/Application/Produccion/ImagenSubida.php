<?php

namespace App\Application\Produccion;

/**
 * Imagen recibida del formulario, ya validada (tipo y tamaño) por el Form
 * Request: ruta temporal del archivo y su extensión real.
 */
final readonly class ImagenSubida
{
    public function __construct(
        public string $rutaTemporal,
        public string $extension,
    ) {}
}
