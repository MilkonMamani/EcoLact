<?php

namespace App\Infrastructure\Ocr;

use App\Application\Calidad\Contracts\TicketAnalizadorOcrService;
use App\Application\Calidad\TicketIlegibleException;
use Throwable;

/**
 * OCR local del ticket del analizador. La ejecución de Tesseract es la
 * compartida (TesseractOcr); aquí solo vive cómo se prepara y lee un ticket.
 *
 * Flujo: preprocesa la foto (PreprocesadorImagenTicket) a un PNG temporal,
 * detecta con OSD si el ticket está girado 90/180/270°, lo endereza y lo lee
 * una vez por cada variante de VARIANTES con --psm 4 (una columna de
 * líneas). Las variantes salieron de probar fotos reales del analizador:
 * ninguna lee bien todos los valores sola, pero votando entre las cuatro no
 * hubo valores erróneos. Los PNG temporales se eliminan siempre.
 */
final class TesseractTicketAnalizadorOcrService implements TicketAnalizadorOcrService
{
    /** @var list<array{lado: int, binarizar: bool}> En orden de preferencia (desempate del voto). */
    private const VARIANTES = [
        ['lado' => 900, 'binarizar' => false],
        ['lado' => 1200, 'binarizar' => false],
        ['lado' => 1200, 'binarizar' => true],
        ['lado' => 1600, 'binarizar' => true],
    ];

    private const MODO_SEGMENTACION = '4';

    public function __construct(
        private readonly PreprocesadorImagenTicket $preprocesador,
        private readonly TesseractOcr $tesseract,
    ) {}

    public function leerLecturas(string $rutaImagen): array
    {
        if (! is_file($rutaImagen)) {
            throw new TicketIlegibleException('La imagen del ticket no existe.');
        }

        // En Windows max_execution_time cuenta tiempo real, incluido el de Tesseract.
        set_time_limit(120);

        // Carpeta propia en vez de tempnam(): Tesseract toma cualquier archivo
        // hermano con el mismo nombre base (el vacío que deja tempnam) como
        // archivo de zonas UZN y entonces no lee nada.
        $carpeta = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ticket-ocr-'.bin2hex(random_bytes(8));

        if (! @mkdir($carpeta, 0700)) {
            throw new TicketIlegibleException('No se pudo preparar la imagen del ticket.');
        }

        $rutaBase = $carpeta.DIRECTORY_SEPARATOR.'ticket.png';
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
                $lecturas[] = trim($this->tesseract->leer($rutaVariante, self::MODO_SEGMENTACION));
            }

            return $lecturas;
        } catch (TicketIlegibleException $excepcion) {
            throw $excepcion;
        } catch (Throwable $excepcion) {
            throw new TicketIlegibleException('No se pudo leer el ticket: '.$excepcion->getMessage(), previous: $excepcion);
        } finally {
            @unlink($rutaBase);
            @unlink($rutaVariante);
            @rmdir($carpeta);
        }
    }
}
