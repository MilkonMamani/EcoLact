<?php

namespace App\Infrastructure\Storage;

use App\Application\Pago\Contracts\AlmacenComprobantePago;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarda los comprobantes de pago con Storage de Laravel en el disco PRIVADO
 * (storage/app/private/comprobantes-pago/AAAA/MM). No se enlazan a
 * public/storage: solo se sirven mediante una ruta autenticada y autorizada.
 */
final class StorageAlmacenComprobantePago implements AlmacenComprobantePago
{
    private const DISCO = 'local';

    private const CARPETA = 'comprobantes-pago';

    public function guardar(string $rutaTemporal, string $extension): string
    {
        $carpeta = self::CARPETA.'/'.now()->format('Y/m');
        $archivo = Str::lower(Str::random(24)).'.'.strtolower($extension);

        $ruta = Storage::disk(self::DISCO)->putFileAs($carpeta, new File($rutaTemporal), $archivo);

        if ($ruta === false) {
            throw new RuntimeException('No se pudo guardar el comprobante del pago.');
        }

        return $ruta;
    }

    public function eliminar(?string $ruta): void
    {
        if ($ruta !== null && $ruta !== '') {
            Storage::disk(self::DISCO)->delete($ruta);
        }
    }

    public function existe(string $ruta): bool
    {
        return Storage::disk(self::DISCO)->exists($ruta);
    }

    public function responder(string $ruta, string $nombreDescarga): Response
    {
        return Storage::disk(self::DISCO)->response($ruta, $nombreDescarga);
    }
}
