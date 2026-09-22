<?php

namespace App\Application\Pago\Contracts;

use Symfony\Component\HttpFoundation\Response;

/**
 * Puerto de almacenamiento del comprobante de un pago. El archivo vive en un
 * disco PRIVADO (nunca en la base de datos ni en una URL pública) y en `pagos`
 * solo se guarda su ruta relativa.
 */
interface AlmacenComprobantePago
{
    /**
     * Guarda el archivo y devuelve su ruta relativa.
     */
    public function guardar(string $rutaTemporal, string $extension): string;

    /**
     * Elimina un archivo. No falla si ya no existe.
     */
    public function eliminar(?string $ruta): void;

    public function existe(string $ruta): bool;

    /**
     * Respuesta HTTP que entrega el archivo (en línea, para verlo en el navegador).
     */
    public function responder(string $ruta, string $nombreDescarga): Response;
}
