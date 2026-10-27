<?php

namespace App\Domain\Calidad;

use DomainException;

/** Otra persona ya seleccionó esta entrega para control de calidad: nunca se duplica la muestra. */
final class MuestraYaSeleccionadaException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Esta entrega ya fue seleccionada para análisis.');
    }
}
