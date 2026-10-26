<?php

namespace App\Application\Produccion;

/**
 * Normaliza texto para COMPARAR palabras leídas por OCR con nombres
 * configurados: minúsculas, sin acentos ni signos y con espacios simples.
 * "AZÚCAR" y "azucar" son lo mismo; "SACOS" y "saco" también (singular()).
 */
final class TextoComparable
{
    public static function normalizar(string $texto): string
    {
        $texto = mb_strtolower($texto);
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u']);
        $texto = (string) preg_replace('/[^a-z0-9.,\/ -]+/', ' ', $texto);

        return trim((string) preg_replace('/\s+/', ' ', $texto));
    }

    /**
     * Palabras (solo letras) del texto ya en singular: "Sacos de Azúcar" -> [saco, de, azucar].
     *
     * @return list<string>
     */
    public static function palabras(string $texto): array
    {
        $limpio = (string) preg_replace('/[^a-z ]+/', ' ', self::normalizar($texto));

        return array_values(array_map(self::singular(...), array_filter(explode(' ', $limpio), static fn (string $p): bool => $p !== '')));
    }

    /**
     * Singular aproximado del español, suficiente para comparar: baldes -> balde,
     * sacos -> saco, bidones -> bidon, cajas -> caja. Palabras cortas no se tocan.
     */
    public static function singular(string $palabra): string
    {
        if (mb_strlen($palabra) <= 3) {
            return $palabra;
        }

        if (preg_match('/(on|or|al|el|ol|ul)es$/', $palabra)) {
            return substr($palabra, 0, -2);
        }

        return str_ends_with($palabra, 's') ? substr($palabra, 0, -1) : $palabra;
    }

    /**
     * Nombre comparable completo en singular: "Sacos grandes" -> "saco grande".
     */
    public static function clave(string $texto): string
    {
        return implode(' ', self::palabras($texto));
    }
}
