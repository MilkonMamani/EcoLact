<?php

namespace App\Domain\Shared\Enums;

enum TipoInsumo: string
{
    case MATERIA_PRIMA = 'MATERIA_PRIMA';
    case INSUMO = 'INSUMO';
    case ENVASE = 'ENVASE';
    case OTRO = 'OTRO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::MATERIA_PRIMA => 'Materia prima',
            self::INSUMO => 'Insumo',
            self::ENVASE => 'Envase',
            self::OTRO => 'Otro',
        };
    }

    /**
     * @return array<string, string> valor => etiqueta
     */
    public static function opciones(): array
    {
        $opciones = [];

        foreach (self::cases() as $caso) {
            $opciones[$caso->value] = $caso->etiqueta();
        }

        return $opciones;
    }
}
