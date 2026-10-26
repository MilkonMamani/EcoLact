<?php

namespace App\Application\Produccion\Contracts;

use App\Application\Produccion\BoletaIlegibleException;

/**
 * Puerto del OCR local de la boleta de compra de insumos. Solo LEE: recibe la
 * ruta absoluta de una imagen ya guardada por el servidor y devuelve el texto.
 * Interpretarlo es trabajo de BoletaCompraInsumosParser.
 */
interface BoletaCompraOcrService
{
    /**
     * Lecturas independientes de la misma boleta (variantes de la imagen), en
     * orden de preferencia; ProcesarBoletaCompra se queda con la que más
     * líneas de compra reconoce.
     *
     * @return list<string>
     *
     * @throws BoletaIlegibleException si el motor OCR no está disponible o falla.
     */
    public function leerLecturas(string $rutaImagen): array;
}
