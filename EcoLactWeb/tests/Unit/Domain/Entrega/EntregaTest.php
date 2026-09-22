<?php

namespace Tests\Unit\Domain\Entrega;

use App\Domain\Entrega\Entrega;
use App\Domain\Shared\Enums\EstadoEntrega;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EntregaTest extends TestCase
{
    public function test_crea_una_entrega_valida(): void
    {
        $entrega = new Entrega(
            id: null,
            proveedorId: 1,
            acopiadorId: 2,
            zonaId: 3,
            registradoPorUserId: 4,
            fechaHora: new DateTimeImmutable('2026-09-09 08:00:00'),
            litros: 25.5,
            metodoIdentificacion: 'QR',
            estado: EstadoEntrega::PENDIENTE_ANALISIS,
        );

        $this->assertSame(1, $entrega->proveedorId());
        $this->assertSame(2, $entrega->acopiadorId());
        $this->assertSame(25.5, $entrega->litros());
        $this->assertSame(EstadoEntrega::PENDIENTE_ANALISIS, $entrega->estado());
    }

    public function test_no_acepta_litros_iguales_a_cero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Entrega(
            id: null,
            proveedorId: 1,
            acopiadorId: null,
            zonaId: 3,
            registradoPorUserId: 4,
            fechaHora: new DateTimeImmutable,
            litros: 0,
            metodoIdentificacion: 'MANUAL',
            estado: EstadoEntrega::PENDIENTE_ANALISIS,
        );
    }

    public function test_no_acepta_litros_negativos(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Entrega(
            id: null,
            proveedorId: 1,
            acopiadorId: null,
            zonaId: 3,
            registradoPorUserId: 4,
            fechaHora: new DateTimeImmutable,
            litros: -5.0,
            metodoIdentificacion: 'MANUAL',
            estado: EstadoEntrega::PENDIENTE_ANALISIS,
        );
    }
}
