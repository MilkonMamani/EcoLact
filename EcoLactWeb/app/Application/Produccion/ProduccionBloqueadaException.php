<?php

namespace App\Application\Produccion;

use DomainException;

/**
 * La producción no puede iniciarse: falta receta o no alcanza algún insumo.
 * Trae todos los motivos, escritos para una persona no técnica.
 */
final class ProduccionBloqueadaException extends DomainException
{
    /**
     * @param  list<string>  $motivos
     */
    public function __construct(public readonly array $motivos)
    {
        parent::__construct(implode(' ', $motivos));
    }
}
