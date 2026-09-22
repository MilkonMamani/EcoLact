<?php

namespace Tests\Unit\Domain\Calidad;

use App\Domain\Shared\Enums\EstadoAnalisis;
use PHPUnit\Framework\TestCase;

class EstadoAnalisisTest extends TestCase
{
    public function test_contiene_borrador_y_confirmado(): void
    {
        $valores = array_map(fn (EstadoAnalisis $caso) => $caso->value, EstadoAnalisis::cases());

        $this->assertCount(2, $valores);
        $this->assertContains('BORRADOR', $valores);
        $this->assertContains('CONFIRMADO', $valores);
    }
}
