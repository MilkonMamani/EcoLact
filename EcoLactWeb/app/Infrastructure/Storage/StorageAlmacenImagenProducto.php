<?php

namespace App\Infrastructure\Storage;

use App\Application\Produccion\Contracts\AlmacenImagenProducto;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Guarda las imágenes de productos con el sistema Storage de Laravel, en
 * storage/app/public/products (enlazado a public/storage con `storage:link`).
 */
final class StorageAlmacenImagenProducto implements AlmacenImagenProducto
{
    private const DISCO = 'public';

    private const CARPETA = 'products';

    public function guardar(string $rutaTemporal, string $extension, string $nombreProducto): string
    {
        $nombre = Str::slug($nombreProducto) ?: 'producto';
        $archivo = $nombre.'-'.Str::lower(Str::random(8)).'.'.strtolower($extension);

        $ruta = Storage::disk(self::DISCO)->putFileAs(self::CARPETA, new File($rutaTemporal), $archivo);

        if ($ruta === false) {
            throw new \RuntimeException('No se pudo guardar la imagen del producto.');
        }

        return $ruta;
    }

    public function eliminar(?string $ruta): void
    {
        if ($ruta !== null && $ruta !== '') {
            Storage::disk(self::DISCO)->delete($ruta);
        }
    }

    public function url(?string $ruta): ?string
    {
        return $ruta ? asset('storage/'.ltrim($ruta, '/')) : null;
    }
}
