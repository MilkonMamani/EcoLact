<?php

namespace App\Domain\Compra;

use App\Domain\Shared\Enums\TipoDocumentoCompra;
use DateTimeImmutable;

/**
 * Un archivo que respalda una compra o uno de sus pagos. Hay UNA fila por
 * archivo y todos los módulos (Compras, Caja, Inventario) la referencian:
 * nunca se copia. `disco` + `ruta` dicen dónde está; los nuevos viven en el
 * disco privado y solo se sirven con autorización.
 */
final class ComprobanteCompra
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $compraInsumoId,
        private readonly TipoDocumentoCompra $tipo,
        private readonly string $disco,
        private readonly string $ruta,
        private readonly int $subidoPorUserId,
        private readonly ?string $nombreOriginal = null,
        private readonly ?string $mime = null,
        private readonly ?int $tamano = null,
        private readonly bool $procesadoOcr = false,
        private readonly ?DateTimeImmutable $subidoEn = null,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function compraInsumoId(): int
    {
        return $this->compraInsumoId;
    }

    public function tipo(): TipoDocumentoCompra
    {
        return $this->tipo;
    }

    public function disco(): string
    {
        return $this->disco;
    }

    public function ruta(): string
    {
        return $this->ruta;
    }

    public function subidoPorUserId(): int
    {
        return $this->subidoPorUserId;
    }

    public function nombreOriginal(): ?string
    {
        return $this->nombreOriginal;
    }

    public function mime(): ?string
    {
        return $this->mime;
    }

    public function tamano(): ?int
    {
        return $this->tamano;
    }

    public function procesadoOcr(): bool
    {
        return $this->procesadoOcr;
    }

    public function subidoEn(): ?DateTimeImmutable
    {
        return $this->subidoEn;
    }

    public function esImagen(): bool
    {
        return $this->mime !== null && str_starts_with($this->mime, 'image/');
    }

    public function esPdf(): bool
    {
        return $this->mime === 'application/pdf';
    }
}
