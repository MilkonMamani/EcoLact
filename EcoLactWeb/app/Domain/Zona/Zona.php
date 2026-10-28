<?php

namespace App\Domain\Zona;

use InvalidArgumentException;

final class Zona
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $nombre,
        private readonly ?string $descripcion,
        private readonly string $estado,
    ) {
        if (trim($nombre) === '') {
            throw new InvalidArgumentException('El nombre de la zona es obligatorio.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function descripcion(): ?string
    {
        return $this->descripcion;
    }

    public function estado(): string
    {
        return $this->estado;
    }

    public function estaActiva(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    /**
     * Clave para comparar nombres de zona: "Collana1", "Collana 1" y "COLLANA  1" son la misma
     * zona. Ignora mayúsculas y espacios repetidos y separa letras de números; no toca nada más
     * (tildes, guiones o palabras distintas siguen siendo nombres distintos).
     */
    public static function normalizarNombre(string $nombre): string
    {
        $texto = preg_replace('/(?<=\p{L})(?=\p{N})|(?<=\p{N})(?=\p{L})/u', ' ', mb_strtolower(trim($nombre)));

        return preg_replace('/\s+/u', ' ', (string) $texto) ?? '';
    }

    public function esEquivalenteA(string $nombre): bool
    {
        return self::normalizarNombre($this->nombre) === self::normalizarNombre($nombre);
    }
}
