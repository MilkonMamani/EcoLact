<?php

namespace App\Application\Produccion\Contracts;

/**
 * Puerto de almacenamiento de la imagen de un producto. La imagen vive en UN
 * solo lugar (el disco público) y en la base de datos solo se guarda su ruta
 * relativa en `productos.imagen`; nunca el binario.
 */
interface AlmacenImagenProducto
{
    /**
     * Guarda la imagen y devuelve su ruta relativa (ej. products/queso-paria-1-kg-ab12cd34.webp).
     */
    public function guardar(string $rutaTemporal, string $extension, string $nombreProducto): string;

    /**
     * Elimina un archivo. No falla si ya no existe.
     */
    public function eliminar(?string $ruta): void;

    /**
     * URL pública de una ruta guardada, o null si no hay imagen.
     */
    public function url(?string $ruta): ?string;
}
