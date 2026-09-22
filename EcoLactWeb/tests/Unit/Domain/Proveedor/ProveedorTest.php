<?php

namespace Tests\Unit\Domain\Proveedor;

use App\Domain\Proveedor\Proveedor;
use App\Domain\Shared\Enums\EstadoUsuario;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ProveedorTest extends TestCase
{
    public function test_crea_un_proveedor_valido(): void
    {
        $proveedor = new Proveedor(
            id: null,
            userId: 1,
            zonaId: 2,
            dni: '12345678',
            nombres: 'Juana',
            apellidos: 'Quispe Mamani',
            telefono: '71234567',
            qrToken: 'a1b2c3d4-0000-0000-0000-000000000000',
            estado: EstadoUsuario::ACTIVO,
            fechaRegistro: new DateTimeImmutable('2026-01-15'),
        );

        $this->assertSame('12345678', $proveedor->dni());
        $this->assertSame('Juana Quispe Mamani', $proveedor->nombreCompleto());
        $this->assertSame(EstadoUsuario::ACTIVO, $proveedor->estado());
    }

    public function test_no_acepta_dni_vacio(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Proveedor(
            id: null,
            userId: 1,
            zonaId: 2,
            dni: '',
            nombres: 'Juana',
            apellidos: 'Quispe Mamani',
            telefono: null,
            qrToken: 'a1b2c3d4-0000-0000-0000-000000000000',
            estado: EstadoUsuario::ACTIVO,
            fechaRegistro: new DateTimeImmutable('2026-01-15'),
        );
    }
}
