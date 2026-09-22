<?php

namespace App\Domain\Pago;

use App\Domain\Shared\Enums\ResultadoCalidad;
use DateTimeImmutable;

interface ReglaPagoCalidadRepository
{
    public function guardar(ReglaPagoCalidad $regla): ReglaPagoCalidad;

    public function actualizar(ReglaPagoCalidad $regla): ReglaPagoCalidad;

    public function buscarPorId(int $id): ?ReglaPagoCalidad;

    public function buscarVigentePorResultado(ResultadoCalidad $resultado, DateTimeImmutable $fecha): ?ReglaPagoCalidad;

    /**
     * @return ReglaPagoCalidad[]
     */
    public function listar(): array;
}
