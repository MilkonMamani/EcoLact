<?php

namespace App\Domain\Auth;

use InvalidArgumentException;

final class Role
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $nombre,
        private readonly ?string $descripcion,
        private readonly bool $esRemunerable = false,
        private readonly bool $controlaAsistencia = true,
    ) {
        if (trim($nombre) === '') {
            throw new InvalidArgumentException('El nombre del rol es obligatorio.');
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

    public function esRemunerable(): bool
    {
        return $this->esRemunerable;
    }

    /** Si el personal de este rol marca asistencia (el ADMINISTRADOR no: sueldo fijo). */
    public function controlaAsistencia(): bool
    {
        return $this->controlaAsistencia;
    }

    /** Nombre legible del rol para mostrar al usuario (nunca el código técnico). */
    public function etiqueta(): string
    {
        return self::etiquetaDe($this->nombre);
    }

    public static function etiquetaDe(string $nombre): string
    {
        return match ($nombre) {
            'ADMINISTRADOR' => 'Administrador',
            'ACOPIADOR' => 'Acopiador',
            'CALIDAD' => 'Analista de calidad',
            'ENCARGADO_PLANTA' => 'Encargado de planta',
            'VENTAS' => 'Personal de ventas',
            'PROVEEDOR' => 'Proveedor',
            default => ucfirst(mb_strtolower(str_replace('_', ' ', $nombre))),
        };
    }
}
