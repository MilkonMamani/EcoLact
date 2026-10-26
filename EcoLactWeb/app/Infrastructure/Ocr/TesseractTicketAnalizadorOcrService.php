<?php

namespace App\Infrastructure\Ocr;

use App\Application\Calidad\Contracts\TicketAnalizadorOcrService;
use App\Application\Calidad\TicketIlegibleException;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * OCR local con el ejecutable de Tesseract (sin servicios en la nube). Se
 * invoca con Process y argumentos en arreglo, nunca concatenando un comando
 * de shell; la ruta de la imagen la genera el servidor, no el request.
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

    private const SEGUNDOS_MAXIMOS = 30;

    /** Por debajo de esta confianza el OSD no justifica girar la imagen. */
    private const CONFIANZA_MINIMA_ORIENTACION = 2.0;

    public function __construct(
        private readonly PreprocesadorImagenTicket $preprocesador,
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

            $giro = $this->detectarGiro($rutaBase);
            if ($giro !== 0) {
                $this->preprocesador->rotar($rutaBase, $giro);
            }

            $this->preprocesador->enderezar($rutaBase);

            $lecturas = [];
            foreach (self::VARIANTES as $variante) {
                $this->preprocesador->variante($rutaBase, $rutaVariante, $variante['lado'], $variante['binarizar']);
                $lecturas[] = trim($this->tesseract($rutaVariante));
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

    private function tesseract(string $rutaImagen): string
    {
        $resultado = Process::timeout(self::SEGUNDOS_MAXIMOS)->run([
            $this->binario(),
            $rutaImagen,
            'stdout',
            '-l',
            (string) config('services.tesseract.idioma'),
            '--psm',
            self::MODO_SEGMENTACION,
        ]);

        if (! $resultado->successful()) {
            throw new TicketIlegibleException('Tesseract falló: '.trim($resultado->errorOutput()));
        }

        return $resultado->output();
    }

    /**
     * Grados (horario) que hay que girar la imagen según el OSD de Tesseract.
     * Si el OSD no está disponible o no está seguro, no se gira.
     */
    private function detectarGiro(string $rutaImagen): int
    {
        $resultado = Process::timeout(self::SEGUNDOS_MAXIMOS)->run([
            $this->binario(),
            $rutaImagen,
            'stdout',
            '--psm',
            '0',
        ]);

        $salida = $resultado->output()."\n".$resultado->errorOutput();

        if (! preg_match('/Rotate:\s*(\d+)/', $salida, $giro)
            || ! preg_match('/Orientation confidence:\s*([\d.]+)/', $salida, $confianza)) {
            return 0;
        }

        $grados = (int) $giro[1];

        return in_array($grados, [90, 180, 270], true) && (float) $confianza[1] >= self::CONFIANZA_MINIMA_ORIENTACION
            ? $grados
            : 0;
    }

    private function binario(): string
    {
        return (string) config('services.tesseract.binario');
    }
}
