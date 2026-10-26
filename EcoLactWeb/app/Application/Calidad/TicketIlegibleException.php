<?php

namespace App\Application\Calidad;

use RuntimeException;

/**
 * El OCR no pudo leer el ticket del analizador (motor no disponible, imagen
 * corrupta, tiempo agotado). No bloquea el análisis: el analista puede
 * volver a escanear o ingresar los valores manualmente.
 */
final class TicketIlegibleException extends RuntimeException {}
