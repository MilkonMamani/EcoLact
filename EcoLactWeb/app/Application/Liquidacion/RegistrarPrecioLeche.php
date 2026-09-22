<?php

namespace App\Application\Liquidacion;

use App\Domain\Auth\Repositories\UsuarioRepository;
use App\Domain\Pago\PrecioLeche;
use App\Domain\Pago\PrecioLecheRepository;
use DateTimeImmutable;
use DomainException;

/**
 * Permite al ADMINISTRADOR configurar un precio base de leche vigente.
 *
 * El precio viene siempre del administrador (no se hardcodea aquí). No
 * elimina ni modifica precios anteriores: cada registro es una fila nueva,
 * preservando el historial completo de precios_leche.
 */
final class RegistrarPrecioLeche
{
    public function __construct(
        private readonly PrecioLecheRepository $preciosLeche,
        private readonly UsuarioRepository $usuarios,
    ) {}

    public function execute(
        float $precioLitro,
        DateTimeImmutable $fechaInicio,
        ?DateTimeImmutable $fechaFin,
        string $estado,
        int $creadoPorUserId,
    ): PrecioLeche {
        if ($this->usuarios->buscarPorId($creadoPorUserId) === null) {
            throw new DomainException('El usuario que registra el precio no existe.');
        }

        if ($fechaFin !== null && $fechaFin < $fechaInicio) {
            throw new DomainException('La fecha fin del precio no puede ser anterior a la fecha inicio.');
        }

        return $this->preciosLeche->guardar(new PrecioLeche(
            id: null,
            precioLitro: $precioLitro,
            fechaInicio: $fechaInicio,
            fechaFin: $fechaFin,
            estado: $estado,
            creadoPorUserId: $creadoPorUserId,
        ));
    }
}
