<?php

namespace App\Infrastructure\Ocr;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Única pieza que EJECUTA Tesseract (local, sin servicios en la nube). La usan
 * los servicios OCR de cada documento (ticket del analizador, boleta de compra
 * de insumos); cada uno decide cómo preparar la imagen y cómo interpretar el
 * texto. Se invoca con Process y argumentos en arreglo, nunca concatenando un
 * comando de shell; la ruta de la imagen siempre la genera el servidor.
 *
 * Configuración: config/services.php (tesseract.binario / tesseract.idioma,
 * desde TESSERACT_PATH y TESSERACT_LANG).
 */
final class TesseractOcr
{
    private const SEGUNDOS_MAXIMOS = 30;

    /** Por debajo de esta confianza el OSD no justifica girar la imagen. */
    private const CONFIANZA_MINIMA_ORIENTACION = 2.0;

    /**
     * Texto que lee Tesseract en la imagen con el modo de segmentación indicado.
     *
     * @throws RuntimeException si Tesseract no está disponible o falla.
     */
    public function leer(string $rutaImagen, string $modoSegmentacion): string
    {
        $resultado = Process::timeout(self::SEGUNDOS_MAXIMOS)->run([
            $this->binario(),
            $rutaImagen,
            'stdout',
            '-l',
            (string) config('services.tesseract.idioma'),
            '--psm',
            $modoSegmentacion,
        ]);

        if (! $resultado->successful()) {
            throw new RuntimeException('Tesseract falló: '.trim($resultado->errorOutput()));
        }

        return $resultado->output();
    }

    /**
     * Grados (horario) que hay que girar la imagen según el OSD de Tesseract.
     * Si el OSD no está disponible o no está seguro, no se gira.
     */
    public function detectarGiro(string $rutaImagen): int
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
