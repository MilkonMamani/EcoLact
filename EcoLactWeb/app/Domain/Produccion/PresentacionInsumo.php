<?php

namespace App\Domain\Produccion;

use DomainException;
use InvalidArgumentException;

/**
 * Presentación comercial en que se compra un insumo: "Saco" de 25 kg de Sal,
 * "Balde" de 20 L de Cuajo. `cantidadBase` es lo que trae UNA presentación en
 * la unidad base del insumo (25 000 g, 20 000 ml) y sale siempre de
 * UnidadMedida, nunca de un factor escrito a mano.
 */
final class PresentacionInsumo
{
    public const ACTIVA = 'ACTIVA';

    public const INACTIVA = 'INACTIVA';

    public function __construct(
        private readonly ?int $id,
        private readonly int $insumoId,
        private readonly string $nombre,
        private readonly float $cantidad,
        private readonly string $unidad,
        private readonly float $cantidadBase,
        private readonly string $estado = self::ACTIVA,
    ) {
        if (trim($nombre) === '') {
            throw new InvalidArgumentException('La presentación necesita un nombre.');
        }

        if ($cantidad <= 0.0 || $cantidadBase <= 0.0) {
            throw new InvalidArgumentException('El contenido de la presentación debe ser mayor que cero.');
        }

        if (! in_array($estado, [self::ACTIVA, self::INACTIVA], true)) {
            throw new InvalidArgumentException('El estado de la presentación debe ser ACTIVA o INACTIVA.');
        }
    }

    /**
     * Arma la presentación de `$insumo` convirtiendo su contenido a la unidad
     * base con UnidadMedida (25 kg -> 25 000 g). La leche no se compra.
     *
     * @throws DomainException si el insumo es la leche o la unidad no es compatible con su unidad base.
     */
    public static function para(Insumo $insumo, string $nombre, float $cantidad, string $unidad, ?int $id = null, string $estado = self::ACTIVA): self
    {
        if ($insumo->esLeche()) {
            throw new DomainException('La leche no tiene presentaciones de compra: su stock aumenta solo desde Acopio.');
        }

        if (! UnidadMedida::sonCompatibles($unidad, $insumo->unidadBase())) {
            throw new DomainException("La unidad {$unidad} no es compatible con {$insumo->nombre()}, que se controla en {$insumo->unidadBase()}.");
        }

        if ($cantidad <= 0.0) {
            throw new DomainException('El contenido de la presentación debe ser mayor que cero.');
        }

        return new self(
            id: $id,
            insumoId: (int) $insumo->id(),
            nombre: trim($nombre),
            cantidad: round($cantidad, 4),
            unidad: $unidad,
            cantidadBase: round(UnidadMedida::convertir($cantidad, $unidad, $insumo->unidadBase()), 4),
            estado: $estado,
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function insumoId(): int
    {
        return $this->insumoId;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function cantidad(): float
    {
        return $this->cantidad;
    }

    public function unidad(): string
    {
        return $this->unidad;
    }

    public function cantidadBase(): float
    {
        return $this->cantidadBase;
    }

    public function estado(): string
    {
        return $this->estado;
    }

    public function estaActiva(): bool
    {
        return $this->estado === self::ACTIVA;
    }

    /**
     * "Saco 25 kg": el nombre con su contenido, para listas y selectores.
     */
    public function etiqueta(): string
    {
        return $this->nombre.' '.rtrim(rtrim(number_format($this->cantidad, 4, '.', ''), '0'), '.').' '.$this->unidad;
    }
}
