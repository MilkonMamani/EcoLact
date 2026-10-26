<?php

namespace App\Infrastructure\Ocr;

use App\Application\Produccion\BoletaIlegibleException;
use App\Application\Produccion\Contracts\BoletaCompraOcrService;
use Throwable;

/**
 * OCR local de la boleta de compra de insumos. Reutiliza la preparación de
 * imagen del ticket (recorte del papel, iluminación, enderezado) y la
 * ejecución compartida de Tesseract (TesseractOcr). La boleta es una columna
 * de renglones de ancho variable: se lee con --psm 4 y --psm 6 sobre la imagen
 * en grises, y una vez binarizada. Los PNG temporales se eliminan siempre.
 */
final class TesseractBoletaCompraOcrService implements BoletaCompraOcrService
{
    /** @var list<array{lado: int, binarizar: bool, psm: string}> */
    private const VARIANTES = [
        ['lado' => 1600, 'binarizar' => false, 'psm' => '4'],
        ['lado' => 1600, 'binarizar' => false, 'psm' => '6'],
        ['lado' => 1600, 'binarizar' => true, 'psm' => '4'],
    ];

    public function __construct(
        private readonly PreprocesadorImagenTicket $preprocesador,
        private readonly TesseractOcr $tesseract,
    ) {}

    public function leerLecturas(string $rutaImagen): array
    {
        if (! is_file($rutaImagen)) {
            throw new BoletaIlegibleException('La imagen de la boleta no existe.');
        }

        // En Windows max_execution_time cuenta tiempo real, incluido el de Tesseract.
        set_time_limit(120);

        // Carpeta propia (no tempnam): Tesseract tomaría un hermano vacío como archivo de zonas.
        $carpeta = sys_get_temp_dir().DIRECTORY_SEPARATOR.'boleta-ocr-'.bin2hex(random_bytes(8));

        if (! @mkdir($carpeta, 0700)) {
            throw new BoletaIlegibleException('No se pudo preparar la imagen de la boleta.');
        }

        $rutaBase = $carpeta.DIRECTORY_SEPARATOR.'boleta.png';
        $rutaVariante = $carpeta.DIRECTORY_SEPARATOR.'variante.png';

        try {
            $this->preprocesador->preparar($rutaImagen, $rutaBase);

            $giro = $this->tesseract->detectarGiro($rutaBase);
            if ($giro !== 0) {
                $this->preprocesador->rotar($rutaBase, $giro);
            }

            $this->preprocesador->enderezar($rutaBase);

            $lecturas = [];
            foreach (self::VARIANTES as $variante) {
                $this->preprocesador->variante($rutaBase, $rutaVariante, $variante['lado'], $variante['binarizar']);
                $lecturas[] = trim($this->tesseract->leer($rutaVariante, $variante['psm']));
            }

            return $lecturas;
        } catch (Throwable $excepcion) {
            throw new BoletaIlegibleException('No se pudo leer la boleta: '.$excepcion->getMessage(), previous: $excepcion);
        } finally {
            @unlink($rutaBase);
            @unlink($rutaVariante);
            @rmdir($carpeta);
        }
    }
}
