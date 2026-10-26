<?php

namespace App\Infrastructure\Ocr;

use GdImage;
use RuntimeException;

/**
 * Prepara la foto del ticket para Tesseract usando solo GD (sin dependencias
 * extra). Ajustado con fotos reales del analizador sobre una mesa de madera:
 * Tesseract sobre la foto cruda no lee casi nada.
 *
 * 1. Aplica la orientación EXIF (las fotos de celular la traen así).
 * 2. Recorta al ticket: la región clara más grande de la foto.
 * 3. Escala el recorte a un tamaño fijo (letras de ~40 px de alto).
 * 4. Escala de grises + normaliza la iluminación (divide por el fondo
 *    desenfocado, lo que elimina sombras) + sube el contraste.
 *    Se entrega en grises, no binarizada: la foto suele tener poca
 *    resolución y un umbral propio rompe los trazos finos de la impresora.
 * 5. Endereza la inclinación (perfil de proyección de las líneas de texto).
 * 6. Genera variantes (escala, grises/binarizada) para leer varias veces.
 */
final class PreprocesadorImagenTicket
{
    private const LADO_TRABAJO = 2400;

    private const LADO_RECORTE = 1600;

    private const LADO_MINIATURA = 320;

    private const CONTRASTE = 2.0;

    private const ANGULO_MAXIMO = 15.0;

    private const PASO_ANGULO = 0.5;

    /**
     * Genera en $rutaDestino (PNG) el ticket recortado y normalizado, sin enderezar.
     */
    public function preparar(string $rutaOriginal, string $rutaDestino): void
    {
        $imagen = $this->aplicarOrientacionExif($this->cargar($rutaOriginal), $rutaOriginal);
        $imagen = $this->escalar($imagen, self::LADO_TRABAJO, soloReducir: true);
        $imagen = $this->escalar($this->recortarTicket($imagen), self::LADO_RECORTE, soloReducir: false);

        $this->guardar($this->normalizarIluminacion($imagen), $rutaDestino);
    }

    /**
     * Gira la imagen en sentido horario (convención del "Rotate" de Tesseract OSD).
     */
    public function rotar(string $ruta, int $gradosHorario): void
    {
        $imagen = $this->cargar($ruta);
        $girada = imagerotate($imagen, (360 - $gradosHorario) % 360, $this->blanco($imagen));

        $this->guardar($girada ?: $imagen, $ruta);
    }

    /**
     * Corrige la inclinación de las líneas de texto (hasta ±15°).
     */
    public function enderezar(string $ruta): void
    {
        $imagen = $this->cargar($ruta);
        $angulo = $this->estimarInclinacion($imagen);

        if ($angulo !== 0.0) {
            $enderezada = imagerotate($imagen, $angulo, $this->blanco($imagen));
            $this->guardar($enderezada ?: $imagen, $ruta);
        }
    }

    /**
     * Copia de la imagen ya preparada, escalada a $lado px (lado mayor) y,
     * si se pide, binarizada con umbral de Otsu. Cada variante le da a
     * Tesseract una oportunidad distinta de leer los valores dudosos.
     */
    public function variante(string $rutaBase, string $rutaDestino, int $lado, bool $binarizar): void
    {
        $imagen = $this->escalar($this->cargar($rutaBase), $lado, soloReducir: false);

        $this->guardar($binarizar ? $this->binarizar($imagen) : $imagen, $rutaDestino);
    }

    private function binarizar(GdImage $imagen): GdImage
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $histograma = array_fill(0, 256, 0);

        for ($y = 0; $y < $alto; $y++) {
            for ($x = 0; $x < $ancho; $x++) {
                $histograma[imagecolorat($imagen, $x, $y) & 0xFF]++;
            }
        }

        $umbral = $this->otsu($histograma, $ancho * $alto);
        $salida = imagecreatetruecolor($ancho, $alto);
        $negro = (int) imagecolorallocate($salida, 0, 0, 0);
        $blanco = $this->blanco($salida);

        for ($y = 0; $y < $alto; $y++) {
            for ($x = 0; $x < $ancho; $x++) {
                imagesetpixel($salida, $x, $y, (imagecolorat($imagen, $x, $y) & 0xFF) < $umbral ? $negro : $blanco);
            }
        }

        return $salida;
    }

    private function cargar(string $ruta): GdImage
    {
        $contenido = @file_get_contents($ruta);
        $imagen = $contenido !== false ? @imagecreatefromstring($contenido) : false;

        if ($imagen === false) {
            throw new RuntimeException('La imagen del ticket no se pudo abrir.');
        }

        return $imagen;
    }

    private function guardar(GdImage $imagen, string $ruta): void
    {
        if (! imagepng($imagen, $ruta)) {
            throw new RuntimeException('No se pudo generar la imagen temporal del ticket.');
        }
    }

    private function blanco(GdImage $imagen): int
    {
        return (int) imagecolorallocate($imagen, 255, 255, 255);
    }

    private function aplicarOrientacionExif(GdImage $imagen, string $ruta): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $imagen;
        }

        $exif = @exif_read_data($ruta);
        $grados = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => 270,
            8 => 90,
            default => 0,
        };

        return $grados === 0 ? $imagen : (imagerotate($imagen, $grados, 0) ?: $imagen);
    }

    private function escalar(GdImage $imagen, int $lado, bool $soloReducir): GdImage
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $factor = $lado / max($ancho, $alto);

        if ($soloReducir && $factor >= 1) {
            return $imagen;
        }

        $nuevoAncho = max(1, (int) round($ancho * $factor));
        $nuevoAlto = max(1, (int) round($alto * $factor));
        $escalada = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagecopyresampled($escalada, $imagen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        return $escalada;
    }

    /**
     * El ticket es la región clara conexa más grande (umbral de Otsu sobre
     * una miniatura en grises). Si no hay una región clara significativa se
     * conserva la foto completa.
     */
    private function recortarTicket(GdImage $imagen): GdImage
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $miniatura = $this->escalar($imagen, self::LADO_MINIATURA, soloReducir: false);
        imagefilter($miniatura, IMG_FILTER_GRAYSCALE);

        $mAncho = imagesx($miniatura);
        $mAlto = imagesy($miniatura);
        $brillo = [];
        $histograma = array_fill(0, 256, 0);

        for ($y = 0; $y < $mAlto; $y++) {
            for ($x = 0; $x < $mAncho; $x++) {
                $valor = imagecolorat($miniatura, $x, $y) & 0xFF;
                $brillo[$y * $mAncho + $x] = $valor;
                $histograma[$valor]++;
            }
        }

        $umbral = $this->otsu($histograma, $mAncho * $mAlto);
        $region = $this->regionClaraMasGrande($brillo, $umbral, $mAncho, $mAlto);

        if ($region === null || $region['pixeles'] < 0.05 * $mAncho * $mAlto) {
            return $imagen;
        }

        $escala = $ancho / $mAncho;
        $margen = (int) round(0.02 * max($ancho, $alto));
        $x0 = max(0, (int) floor($region['x0'] * $escala) - $margen);
        $y0 = max(0, (int) floor($region['y0'] * $escala) - $margen);
        $x1 = min($ancho, (int) ceil(($region['x1'] + 1) * $escala) + $margen);
        $y1 = min($alto, (int) ceil(($region['y1'] + 1) * $escala) + $margen);

        return imagecrop($imagen, ['x' => $x0, 'y' => $y0, 'width' => $x1 - $x0, 'height' => $y1 - $y0]) ?: $imagen;
    }

    /**
     * @param  array<int, int>  $brillo
     * @return array{pixeles: int, x0: int, y0: int, x1: int, y1: int}|null
     */
    private function regionClaraMasGrande(array $brillo, int $umbral, int $ancho, int $alto): ?array
    {
        $visitado = [];
        $mejor = null;
        $total = $ancho * $alto;

        for ($inicio = 0; $inicio < $total; $inicio++) {
            if (isset($visitado[$inicio]) || $brillo[$inicio] <= $umbral) {
                continue;
            }

            $pila = [$inicio];
            $visitado[$inicio] = true;
            $region = ['pixeles' => 0, 'x0' => $ancho, 'y0' => $alto, 'x1' => 0, 'y1' => 0];

            while ($pila !== []) {
                $punto = array_pop($pila);
                $x = $punto % $ancho;
                $y = intdiv($punto, $ancho);
                $region['pixeles']++;
                $region['x0'] = min($region['x0'], $x);
                $region['x1'] = max($region['x1'], $x);
                $region['y0'] = min($region['y0'], $y);
                $region['y1'] = max($region['y1'], $y);

                $vecinos = [
                    $x > 0 ? $punto - 1 : -1,
                    $x < $ancho - 1 ? $punto + 1 : -1,
                    $punto - $ancho,
                    $punto + $ancho,
                ];

                foreach ($vecinos as $vecino) {
                    if ($vecino < 0 || $vecino >= $total || isset($visitado[$vecino]) || $brillo[$vecino] <= $umbral) {
                        continue;
                    }

                    $visitado[$vecino] = true;
                    $pila[] = $vecino;
                }
            }

            if ($mejor === null || $region['pixeles'] > $mejor['pixeles']) {
                $mejor = $region;
            }
        }

        return $mejor;
    }

    private function normalizarIluminacion(GdImage $imagen): GdImage
    {
        imagefilter($imagen, IMG_FILTER_GRAYSCALE);
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);

        // Fondo: reducir mucho y volver a ampliar equivale a un desenfoque fuerte.
        $reducida = $this->escalar($imagen, max(1, intdiv(max($ancho, $alto), 24)), soloReducir: false);
        $fondo = imagecreatetruecolor($ancho, $alto);
        imagecopyresampled($fondo, $reducida, 0, 0, 0, 0, $ancho, $alto, imagesx($reducida), imagesy($reducida));

        $salida = imagecreatetruecolor($ancho, $alto);
        $grises = [];
        for ($i = 0; $i < 256; $i++) {
            $grises[$i] = (int) imagecolorallocate($salida, $i, $i, $i);
        }

        for ($y = 0; $y < $alto; $y++) {
            for ($x = 0; $x < $ancho; $x++) {
                $pixel = imagecolorat($imagen, $x, $y) & 0xFF;
                $base = imagecolorat($fondo, $x, $y) & 0xFF;
                $valor = $base > 0 ? min(255, intdiv($pixel * 255, $base)) : 255;
                $valor = (int) max(0, 255 - (255 - $valor) * self::CONTRASTE);
                imagesetpixel($salida, $x, $y, $grises[$valor]);
            }
        }

        return $salida;
    }

    /**
     * Prueba ángulos entre ±15° y elige el que concentra los píxeles oscuros
     * en menos filas (líneas de texto horizontales).
     */
    private function estimarInclinacion(GdImage $imagen): float
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $histograma = array_fill(0, 256, 0);

        for ($y = 0; $y < $alto; $y += 2) {
            for ($x = 0; $x < $ancho; $x += 2) {
                $histograma[imagecolorat($imagen, $x, $y) & 0xFF]++;
            }
        }

        $umbral = $this->otsu($histograma, array_sum($histograma));
        $oscuros = [];

        for ($y = 0; $y < $alto; $y += 2) {
            for ($x = 0; $x < $ancho; $x += 2) {
                if ((imagecolorat($imagen, $x, $y) & 0xFF) < $umbral) {
                    $oscuros[] = [$x, $y];
                }
            }
        }

        $mejorAngulo = 0.0;
        $mejorPuntaje = -1;

        for ($angulo = -self::ANGULO_MAXIMO; $angulo <= self::ANGULO_MAXIMO; $angulo += self::PASO_ANGULO) {
            $radianes = deg2rad($angulo);
            $seno = sin($radianes);
            $coseno = cos($radianes);
            $filas = [];

            foreach ($oscuros as [$x, $y]) {
                $fila = (int) (($y * $coseno - $x * $seno) / 2);
                $filas[$fila] = ($filas[$fila] ?? 0) + 1;
            }

            $puntaje = 0;
            foreach ($filas as $cantidad) {
                $puntaje += $cantidad * $cantidad;
            }

            if ($puntaje > $mejorPuntaje) {
                $mejorPuntaje = $puntaje;
                $mejorAngulo = $angulo;
            }
        }

        return (float) $mejorAngulo;
    }

    /**
     * @param  array<int, int>  $histograma
     */
    private function otsu(array $histograma, int $total): int
    {
        $suma = 0;
        foreach ($histograma as $nivel => $cantidad) {
            $suma += $nivel * $cantidad;
        }

        $sumaFondo = 0;
        $pesoFondo = 0;
        $maximo = 0.0;
        $umbral = 128;

        foreach ($histograma as $nivel => $cantidad) {
            $pesoFondo += $cantidad;
            $pesoFrente = $total - $pesoFondo;

            if ($pesoFondo === 0) {
                continue;
            }

            if ($pesoFrente === 0) {
                break;
            }

            $sumaFondo += $nivel * $cantidad;
            $diferencia = $sumaFondo / $pesoFondo - ($suma - $sumaFondo) / $pesoFrente;
            $varianza = $pesoFondo * $pesoFrente * $diferencia * $diferencia;

            if ($varianza > $maximo) {
                $maximo = $varianza;
                $umbral = $nivel;
            }
        }

        return $umbral;
    }
}
