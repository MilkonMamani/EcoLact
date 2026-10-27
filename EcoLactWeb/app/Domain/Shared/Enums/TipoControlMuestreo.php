<?php

namespace App\Domain\Shared\Enums;

/** Motivo de una selección MANUAL: muestreo rutinario o control dirigido a un proveedor. */
enum TipoControlMuestreo: string
{
    case RUTINARIO = 'RUTINARIO';
    case DIRIGIDO = 'DIRIGIDO';
}
