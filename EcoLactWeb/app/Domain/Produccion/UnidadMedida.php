<?php

namespace App\Domain\Produccion;

use InvalidArgumentException;

/**
 * Unidades soportadas por inventario y recetas: L, ml, kg, g y unidad.
 *
 * Cada insumo tiene una UNIDAD BASE y todo su stock se guarda en ella. Las
 * recetas pueden expresarse en cualquier unidad COMPATIBLE (misma familia:
 * volumen, masa o conteo) y se convierten a la base con estos factores fijos.
 * Convertir entre familias (por ejemplo kg -> L) no está permitido: no existe
 * una conversión segura sin conocer la densidad.
 */
final class UnidadMedida
{
    /**
     * Factor hacia la unidad principal de su familia (L, kg, unidad).
     *
     * @var array<string, array{familia: string, factor: float, etiqueta: string}>
     */
    private const UNIDADES = [
        'L' => ['familia' => 'VOLUMEN', 'factor' => 1.0, 'etiqueta' => 'Litros (L)'],
        'ml' => ['familia' => 'VOLUMEN', 'factor' => 0.001, 'etiqueta' => 'Mililitros (ml)'],
        'kg' => ['familia' => 'MASA', 'factor' => 1.0, 'etiqueta' => 'Kilogramos (kg)'],
        'g' => ['familia' => 'MASA', 'factor' => 0.001, 'etiqueta' => 'Gramos (g)'],
        'unidad' => ['familia' => 'CONTEO', 'factor' => 1.0, 'etiqueta' => 'Unidades'],
    ];

    /**
     * @return list<string>
     */
    public static function codigos(): array
    {
        return array_keys(self::UNIDADES);
    }

    /**
     * @return array<string, string> código => etiqueta legible
     */
    public static function opciones(): array
    {
        return array_map(static fn (array $unidad): string => $unidad['etiqueta'], self::UNIDADES);
    }

    /**
     * Unidades en las que se puede expresar una cantidad de un insumo con la
     * unidad base indicada (la misma familia).
     *
     * @return array<string, string>
     */
    public static function opcionesCompatiblesCon(string $unidadBase): array
    {
        self::exigirValida($unidadBase);

        $familia = self::UNIDADES[$unidadBase]['familia'];

        return array_map(
            static fn (array $unidad): string => $unidad['etiqueta'],
            array_filter(self::UNIDADES, static fn (array $unidad): bool => $unidad['familia'] === $familia),
        );
    }

    public static function esValida(string $codigo): bool
    {
        return isset(self::UNIDADES[$codigo]);
    }

    public static function sonCompatibles(string $a, string $b): bool
    {
        return self::esValida($a) && self::esValida($b)
            && self::UNIDADES[$a]['familia'] === self::UNIDADES[$b]['familia'];
    }

    public static function etiqueta(string $codigo): string
    {
        return self::UNIDADES[$codigo]['etiqueta'] ?? $codigo;
    }

    /**
     * Convierte $cantidad expresada en $desde a la unidad $hasta.
     *
     * @throws InvalidArgumentException si alguna unidad no existe o no son de la misma familia.
     */
    public static function convertir(float $cantidad, string $desde, string $hasta): float
    {
        self::exigirValida($desde);
        self::exigirValida($hasta);

        if (! self::sonCompatibles($desde, $hasta)) {
            throw new InvalidArgumentException("No se puede convertir de {$desde} a {$hasta}: son magnitudes distintas.");
        }

        if ($desde === $hasta) {
            return $cantidad;
        }

        return round($cantidad * self::UNIDADES[$desde]['factor'] / self::UNIDADES[$hasta]['factor'], 6);
    }

    private static function exigirValida(string $codigo): void
    {
        if (! self::esValida($codigo)) {
            throw new InvalidArgumentException("La unidad de medida '{$codigo}' no es válida. Use: ".implode(', ', self::codigos()).'.');
        }
    }
}
