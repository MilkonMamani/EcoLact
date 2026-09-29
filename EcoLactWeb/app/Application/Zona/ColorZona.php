<?php

namespace App\Application\Zona;

/**
 * Color con el que una zona se pinta en los mapas de Zonas. Sale de
 * zonas.color_mapa; una zona que todavía no lo tiene (p. ej. recién creada)
 * toma un color estable de la paleta según su id, para que el mapa general
 * nunca pinte dos zonas con el mismo color por omisión.
 */
final class ColorZona
{
    public const PATRON = '/^#[0-9a-fA-F]{6}$/';

    /** @var list<string> */
    private const PALETA = ['#16a34a', '#d99a00', '#2563eb', '#dc3f4f', '#7c3aed', '#0d9488', '#ea580c', '#db2777'];

    public static function resolver(?string $colorMapa, int $zonaId): string
    {
        if ($colorMapa !== null && preg_match(self::PATRON, $colorMapa) === 1) {
            return strtolower($colorMapa);
        }

        return self::PALETA[($zonaId - 1) % count(self::PALETA)];
    }
}
