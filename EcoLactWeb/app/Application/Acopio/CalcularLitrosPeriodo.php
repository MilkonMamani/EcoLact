<?php

namespace App\Application\Acopio;

use App\Domain\Entrega\EntregaRepository;
use App\Domain\Shared\Enums\EstadoEntrega;
use DateTimeImmutable;

/**
 * Suma los litros entregados por un proveedor dentro de un periodo de
 * fechas (por ejemplo, el periodo semanal jueves-miércoles de EcoLact).
 * Las entregas ANULADAs no se contabilizan.
 */
final class CalcularLitrosPeriodo
{
    public function __construct(
        private readonly EntregaRepository $entregas,
    ) {}

    public function execute(int $proveedorId, DateTimeImmutable $fechaInicio, DateTimeImmutable $fechaFin): float
    {
        $litros = 0.0;

        foreach ($this->entregas->listarPorProveedor($proveedorId) as $entrega) {
            if ($entrega->estado() === EstadoEntrega::ANULADA) {
                continue;
            }

            if ($entrega->fechaHora() < $fechaInicio || $entrega->fechaHora() > $fechaFin) {
                continue;
            }

            $litros += $entrega->litros();
        }

        return round($litros, 2);
    }
}
