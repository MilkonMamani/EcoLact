<?php

namespace App\Http\Support;

use App\Domain\Compra\ComprobanteCompra;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dónde y cómo se guardan los comprobantes de Compras y gastos.
 *
 *   - Los nuevos van al disco PRIVADO (`local`, storage/app/private): no
 *     tienen URL pública; solo los sirve CompraController::verComprobante
 *     después de autorizar.
 *   - Nombre aleatorio: nunca se sobrescribe ni se adivina un archivo.
 *   - Solo imágenes (jpg, png, webp) o PDF de hasta 8 MB, por extensión Y
 *     por tipo MIME real.
 *   - Las boletas históricas y las de la app de planta siguen en el disco
 *     `public` (CARPETA_BOLETAS_APP): se referencian, no se mueven.
 */
final class ComprobantesCompra
{
    public const DISCO = 'local';

    public const CARPETA = 'compras/comprobantes';

    /** Fotos que sube la app de planta al escanear (disco public, como siempre). */
    public const CARPETA_BOLETAS_APP = 'compras-insumo/boletas';

    public const MAX_KB = 8192;

    /**
     * @return list<string>
     */
    public static function reglasArchivo(): array
    {
        return ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'max:'.self::MAX_KB];
    }

    /**
     * @return array<string, string>
     */
    public static function mensajesArchivo(string $campo): array
    {
        return [
            "{$campo}.file" => 'El documento no se pudo subir.',
            "{$campo}.mimes" => 'El documento debe ser una imagen (JPG, PNG o WEBP) o un PDF.',
            "{$campo}.mimetypes" => 'El documento debe ser una imagen (JPG, PNG o WEBP) o un PDF.',
            "{$campo}.max" => 'El documento no puede pesar más de 8 MB.',
            "{$campo}.uploaded" => 'El documento no se pudo subir. Intenta con un archivo más liviano.',
        ];
    }

    /**
     * Guarda el archivo en el disco privado.
     *
     * @return array{disco: string, ruta: string, nombre_original: string, mime: ?string, tamano: int}
     */
    public static function guardar(UploadedFile $archivo, string $prefijo = 'doc'): array
    {
        $ruta = $archivo->storeAs(self::CARPETA, $prefijo.'-'.Str::lower(Str::random(32)).'.'.$archivo->extension(), self::DISCO);

        return [
            'disco' => self::DISCO,
            'ruta' => $ruta,
            'nombre_original' => mb_substr($archivo->getClientOriginalName(), 0, 150),
            'mime' => $archivo->getMimeType(),
            'tamano' => (int) $archivo->getSize(),
        ];
    }

    /**
     * Solo una foto que guardó "Escanear boleta" y que existe.
     */
    public static function esEscaneoValido(mixed $ruta): bool
    {
        $patron = '#^'.preg_quote(self::CARPETA, '#').'/escaneo-[a-z0-9]{32}\.(jpg|jpeg|png|webp)$#';

        return is_string($ruta) && preg_match($patron, $ruta) === 1 && Storage::disk(self::DISCO)->exists($ruta);
    }

    /**
     * El archivo para verlo en el navegador o descargarlo.
     */
    public static function respuesta(ComprobanteCompra $comprobante, bool $descargar): ?StreamedResponse
    {
        $disco = Storage::disk($comprobante->disco());

        if (! $disco->exists($comprobante->ruta())) {
            return null;
        }

        $nombre = $comprobante->nombreOriginal() ?? basename($comprobante->ruta());

        return $descargar
            ? $disco->download($comprobante->ruta(), $nombre)
            : $disco->response($comprobante->ruta(), $nombre, ['X-Content-Type-Options' => 'nosniff']);
    }
}
