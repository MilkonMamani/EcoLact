<?php

namespace App\Domain\Comunicacion;

/**
 * Catálogo de valores válidos para los campos de texto libre de Comunicado
 * (tipo/alcance/estado no son columnas ENUM en la base de datos, así que la
 * validación se centraliza aquí para que CrearComunicado y ActualizarComunicado
 * no diverjan sobre qué valores aceptan).
 */
final class CatalogoComunicado
{
    public const TIPOS_VALIDOS = ['INFORMACION', 'AVISO', 'CAPACITACION'];

    public const ALCANCES_VALIDOS = ['INDIVIDUAL', 'ZONA', 'GENERAL'];

    public const ESTADOS_VALIDOS = ['BORRADOR', 'PUBLICADO'];
}
