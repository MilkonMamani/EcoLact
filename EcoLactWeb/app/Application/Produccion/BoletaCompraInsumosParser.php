<?php

namespace App\Application\Produccion;

use DateTimeImmutable;

/**
 * Interpreta el texto OCR de una boleta de compra de insumos. Solo LEE texto:
 * no conoce stock, ids ni equivalencias (eso lo resuelve ProcesarBoletaCompra
 * con las presentaciones configuradas).
 *
 * Una línea de compra es una línea con CANTIDAD y además una presentación
 * conocida ("2 SACOS SAL") o el nombre de un insumo conocido ("1 CUAJO"). Se
 * aceptan variaciones de orden y conectores, porque la posición de las
 * palabras cambia entre boletas:
 *
 *   2 SACOS SAL · 2 SACO SAL · 2 x SACO SAL · 2X SACO SAL · SAL 2 SACOS ·
 *   1 BALDE DE CUAJO · CUAJO 1 BALDE
 *
 * La cantidad es el número pegado a la presentación (antes o después); los
 * números sueltos restantes (precios, "25KG") no forman parte del nombre.
 * Además propone los datos del comprobante (RUC, fecha, número, total,
 * razón social) si los encuentra; ninguno es obligatorio.
 *
 * @phpstan-type LineaBoleta array{texto: string, cantidad: float, presentacion: ?string, descripcion: string}
 * @phpstan-type Cabecera array{proveedor: ?string, ruc: ?string, numero_comprobante: ?string, fecha: ?string, total: ?float}
 */
final class BoletaCompraInsumosParser
{
    /** Palabras que unen cantidad, presentación e insumo y no son parte del nombre. */
    private const CONECTORES = ['x', 'de', 'del', 'la', 'el', 'los', 'las', 'por', 'con', 'en'];

    /** Unidades y monedas que suelen acompañar precios o pesos en la línea. */
    private const RUIDO = ['kg', 'kgs', 'kilo', 'kilos', 'g', 'gr', 'grs', 'l', 'lt', 'lts', 'litro', 'ml', 'und', 'unid', 'un', 's', 'pen', 'sol', 'soles'];

    /** Líneas de totales e impuestos: nunca son una compra aunque tengan números. */
    private const PALABRAS_RESUMEN = ['total', 'subtotal', 'igv', 'gravada', 'gravado', 'exonerada', 'importe', 'vuelto', 'efectivo', 'son', 'ruc', 'fecha', 'boleta', 'factura', 'cambio', 'pago', 'descuento'];

    /**
     * @param  list<string>  $presentaciones  nombres de presentaciones configuradas ("Saco", "Balde", "Saco grande")
     * @param  list<string>  $insumos  nombres de los insumos comprables ("Sal", "Cuajo", "Azúcar")
     * @return array{lineas: list<LineaBoleta>, cabecera: Cabecera}
     */
    public function parsear(string $texto, array $presentaciones, array $insumos): array
    {
        $vocabularioPresentaciones = $this->vocabulario($presentaciones);
        $vocabularioInsumos = $this->vocabulario($insumos);

        $lineas = [];

        foreach (preg_split('/\R/u', $texto) ?: [] as $linea) {
            $detectada = $this->parsearLinea($linea, $vocabularioPresentaciones, $vocabularioInsumos);

            if ($detectada !== null) {
                $lineas[] = $detectada;
            }
        }

        return ['lineas' => $lineas, 'cabecera' => $this->cabecera($texto)];
    }

    /**
     * @param  array<string, true>  $presentaciones
     * @param  array<string, true>  $insumos
     * @return LineaBoleta|null
     */
    private function parsearLinea(string $linea, array $presentaciones, array $insumos): ?array
    {
        $original = trim($linea);
        $tokens = $this->tokens($original);

        if (count($tokens) < 2) {
            return null;
        }

        $indicePresentacion = null;
        foreach ($tokens as $i => $token) {
            if (isset($presentaciones[TextoComparable::singular($token)])) {
                $indicePresentacion = $i;
                break;
            }
        }

        if ($indicePresentacion === null && array_intersect(array_map(TextoComparable::singular(...), $tokens), self::PALABRAS_RESUMEN) !== []) {
            return null;
        }

        $indiceCantidad = $indicePresentacion !== null
            ? $this->cantidadJuntoA($tokens, $indicePresentacion)
            : ($this->esNumero($tokens[0]) ? 0 : null);

        if ($indiceCantidad === null) {
            return null;
        }

        $cantidad = $this->numero($tokens[$indiceCantidad]);

        if ($cantidad <= 0.0) {
            return null;
        }

        $palabras = [];
        foreach ($tokens as $i => $token) {
            if ($i === $indiceCantidad || $i === $indicePresentacion || $this->esNumero($token)) {
                continue;
            }

            $singular = TextoComparable::singular($token);

            if (in_array($token, self::CONECTORES, true) || in_array($token, self::RUIDO, true) || ! preg_match('/[a-z]/', $token)) {
                continue;
            }

            $palabras[] = $singular;
        }

        // Sin presentación, la línea solo cuenta si nombra un insumo conocido ("1 CUAJO").
        if ($indicePresentacion === null && array_filter($palabras, static fn (string $p): bool => isset($insumos[$p])) === []) {
            return null;
        }

        if ($palabras === []) {
            return null;
        }

        return [
            'texto' => $original,
            'cantidad' => round($cantidad, 2),
            'presentacion' => $indicePresentacion !== null ? TextoComparable::singular($tokens[$indicePresentacion]) : null,
            'descripcion' => implode(' ', $palabras),
        ];
    }

    /**
     * Índice del número de cantidad junto a la presentación: primero hacia
     * atrás ("2 x SACO"), luego hacia adelante ("SACO x 2").
     *
     * @param  list<string>  $tokens
     */
    private function cantidadJuntoA(array $tokens, int $indice): ?int
    {
        foreach ([-1, 1] as $paso) {
            for ($i = $indice + $paso; $i >= 0 && $i < count($tokens); $i += $paso) {
                if ($this->esNumero($tokens[$i])) {
                    return $i;
                }

                if (! in_array($tokens[$i], ['x', 'de'], true)) {
                    break;
                }
            }
        }

        return null;
    }

    /**
     * "2X SACO, SAL 25KG" -> [2, x, saco, sal, 25, kg].
     *
     * @return list<string>
     */
    private function tokens(string $linea): array
    {
        $texto = TextoComparable::normalizar($linea);
        $texto = (string) preg_replace('/(\d)([a-z×])/u', '$1 $2', $texto);
        $texto = (string) preg_replace('/([a-z])(\d)/u', '$1 $2', $texto);
        $texto = str_replace([',', '/', '×'], [' ', ' ', ' x '], (string) preg_replace('/(\d),(\d)/', '$1.$2', $texto));

        return array_values(array_filter(
            array_map(static fn (string $t): string => trim($t, '.-'), explode(' ', $texto)),
            static fn (string $t): bool => $t !== '',
        ));
    }

    private function esNumero(string $token): bool
    {
        return (bool) preg_match('/^\d+(\.\d+)?$/', $token);
    }

    private function numero(string $token): float
    {
        return (float) $token;
    }

    /**
     * Palabras comparables de cada nombre (todas, en singular): "Saco grande" aporta "saco" y "grande".
     *
     * @param  list<string>  $nombres
     * @return array<string, true>
     */
    private function vocabulario(array $nombres): array
    {
        $vocabulario = [];

        foreach ($nombres as $nombre) {
            $palabras = TextoComparable::palabras($nombre);

            // La primera palabra identifica la presentación/insumo ("saco" en "Saco grande").
            if ($palabras !== []) {
                $vocabulario[$palabras[0]] = true;
            }
        }

        return $vocabulario;
    }

    /**
     * @return Cabecera
     */
    private function cabecera(string $texto): array
    {
        $ruc = preg_match('/\b((?:10|15|17|20)\d{9})\b/', $texto, $m) ? $m[1] : null;

        $numero = preg_match('/\b([BFE][A-Z0-9]{3})\s*[-–]\s*(\d{1,8})\b/i', $texto, $m)
            ? strtoupper($m[1]).'-'.$m[2]
            : null;

        $fecha = null;
        if (preg_match('/\b(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})\b/', $texto, $m)) {
            $anio = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];
            if (checkdate((int) $m[2], (int) $m[1], $anio)) {
                $fecha = (new DateTimeImmutable)->setDate($anio, (int) $m[2], (int) $m[1])->format('Y-m-d');
            }
        }

        $total = null;
        foreach (preg_split('/\R/u', $texto) ?: [] as $linea) {
            $normal = TextoComparable::normalizar($linea);
            if (preg_match('/\btotal\b/', $normal) && ! str_contains($normal, 'subtotal')
                && preg_match_all('/(\d+(?:[.,]\d{1,2}))/', $normal, $montos) > 0) {
                $total = (float) str_replace(',', '.', end($montos[1]));
            }
        }

        return [
            'proveedor' => $this->razonSocial($texto),
            'ruc' => $ruc,
            'numero_comprobante' => $numero,
            'fecha' => $fecha,
            'total' => $total,
        ];
    }

    /**
     * Razón social: una línea con forma societaria (S.A.C., E.I.R.L., S.R.L.)
     * o, si no hay, la primera línea de solo letras del encabezado.
     */
    private function razonSocial(string $texto): ?string
    {
        $lineas = array_values(array_filter(array_map('trim', preg_split('/\R/u', $texto) ?: []), static fn (string $l): bool => $l !== ''));

        foreach ($lineas as $linea) {
            if (preg_match('/\b(S\.?\s?A\.?\s?C\.?|E\.?\s?I\.?\s?R\.?\s?L\.?|S\.?\s?R\.?\s?L\.?|S\.?\s?A\.?)\s*$/i', $linea)) {
                return mb_substr($linea, 0, 150);
            }
        }

        foreach (array_slice($lineas, 0, 4) as $linea) {
            if (preg_match('/^[\p{L}][\p{L}\s.&]{3,}$/u', $linea)) {
                return mb_substr($linea, 0, 150);
            }
        }

        return null;
    }
}
