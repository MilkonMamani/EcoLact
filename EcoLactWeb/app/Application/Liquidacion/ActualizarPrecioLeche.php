<?php

namespace App\Application\Liquidacion;

use App\Application\Shared\CampoActualizable;
use App\Domain\Pago\PrecioLeche;
use App\Domain\Pago\PrecioLecheRepository;
use DateTimeImmutable;
use DomainException;

/**
 * Actualiza un precio de leche existente (por ejemplo, para cerrar su
 * vigencia con una fecha_fin, reabrirla quitándosela, o desactivarlo). No
 * modifica fecha_inicio ni creado_por_user_id: son datos históricos del
 * registro original. No elimina ni reemplaza otros precios: cada fila
 * conserva su historial.
 *
 * fecha_fin es una columna nullable: "no enviarla" (conservar la actual) y
 * "enviarla explícitamente como null" (reabrir la vigencia) son cosas
 * distintas, por lo que se recibe envuelta en CampoActualizable.
 *
 * @param  CampoActualizable<DateTimeImmutable|null>|null  $fechaFin
 */
final class ActualizarPrecioLeche
{
    public function __construct(
        private readonly PrecioLecheRepository $preciosLeche,
    ) {}

    public function execute(
        int $precioLecheId,
        ?float $precioLitro = null,
        ?CampoActualizable $fechaFin = null,
        ?string $estado = null,
    ): PrecioLeche {
        $precioLeche = $this->preciosLeche->buscarPorId($precioLecheId);

        if ($precioLeche === null) {
            throw new DomainException('El precio de leche indicado no existe.');
        }

        $nuevaFechaFin = CampoActualizable::resolver($fechaFin, $precioLeche->fechaFin());

        if ($nuevaFechaFin !== null && $nuevaFechaFin < $precioLeche->fechaInicio()) {
            throw new DomainException('La fecha fin del precio no puede ser anterior a la fecha inicio.');
        }

        return $this->preciosLeche->actualizar(new PrecioLeche(
            id: $precioLeche->id(),
            precioLitro: $precioLitro ?? $precioLeche->precioLitro(),
            fechaInicio: $precioLeche->fechaInicio(),
            fechaFin: $nuevaFechaFin,
            estado: $estado ?? $precioLeche->estado(),
            creadoPorUserId: $precioLeche->creadoPorUserId(),
        ));
    }
}
