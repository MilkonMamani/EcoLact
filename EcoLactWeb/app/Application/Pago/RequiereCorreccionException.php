<?php

namespace App\Application\Pago;

use DomainException;

/** El trabajador ya tiene una FALTA registrada hoy: un escaneo QR no puede resolverlo solo, hace falta CorregirJornada. */
final class RequiereCorreccionException extends DomainException {}
