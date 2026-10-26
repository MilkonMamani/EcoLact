<?php

namespace App\Application\Produccion;

use RuntimeException;

/**
 * El OCR no pudo leer la boleta (motor no disponible, imagen corrupta, tiempo
 * agotado). No bloquea la compra: se puede reintentar o registrarla a mano.
 */
final class BoletaIlegibleException extends RuntimeException {}
