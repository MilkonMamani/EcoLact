<?php

namespace App\Application\Ventas;

use App\Domain\Ventas\Comprador;
use App\Domain\Ventas\CompradorRepository;

/**
 * Registra un comprador en la agenda de clientes (para saber quién compró).
 * No se le asigna tipo de precio: el mayorista lo decide la cantidad.
 */
final class RegistrarComprador
{
    public function __construct(
        private readonly CompradorRepository $compradores,
    ) {}

    public function execute(
        string $nombre,
        ?string $documento = null,
        ?string $telefono = null,
    ): Comprador {
        $limpio = fn (?string $valor): ?string => $valor !== null && trim($valor) !== '' ? trim($valor) : null;

        return $this->compradores->guardar(new Comprador(
            id: null,
            nombre: trim($nombre),
            documento: $limpio($documento),
            telefono: $limpio($telefono),
            estado: 'ACTIVO',
        ));
    }
}
