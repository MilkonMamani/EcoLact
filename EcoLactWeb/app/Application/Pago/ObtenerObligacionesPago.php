<?php

namespace App\Application\Pago;

use App\Application\Pago\Contracts\ObligacionesPagoListadoQuery;
use DateTimeImmutable;

/** Datos de la pantalla Pagos: pendientes y pagados generados desde Configuración de pagos. */
final class ObtenerObligacionesPago
{
    public function __construct(private readonly ObligacionesPagoListadoQuery $listado) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(ObligacionesPagoFiltro $filtro, ?DateTimeImmutable $hoy = null): array
    {
        return $this->listado->obtener($filtro, $hoy ?? DateTimeImmutable::createFromInterface(now()));
    }
}
