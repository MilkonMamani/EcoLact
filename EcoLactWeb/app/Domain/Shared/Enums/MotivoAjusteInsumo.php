<?php

namespace App\Domain\Shared\Enums;

/**
 * Por qué se corrige a mano el stock de un insumo. Nunca es una compra: las
 * compras tienen su propio flujo (RegistrarCompraInsumos).
 *
 *   MERMA / PERDIDA     siempre restan la cantidad indicada,
 *   CORRECCION          suma o resta la cantidad indicada (un error de registro),
 *   INVENTARIO_FISICO   la cantidad es lo que se CONTÓ: el stock pasa a ser ese.
 */
enum MotivoAjusteInsumo: string
{
    case MERMA = 'MERMA';
    case PERDIDA = 'PERDIDA';
    case CORRECCION = 'CORRECCION';
    case INVENTARIO_FISICO = 'INVENTARIO_FISICO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::MERMA => 'Merma',
            self::PERDIDA => 'Pérdida',
            self::CORRECCION => 'Corrección',
            self::INVENTARIO_FISICO => 'Inventario físico',
        };
    }

    public function soloResta(): bool
    {
        return $this === self::MERMA || $this === self::PERDIDA;
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
