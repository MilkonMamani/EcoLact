<?php

namespace Tests\Unit\Application\Shared;

use App\Application\Shared\CampoActualizable;
use PHPUnit\Framework\TestCase;

class CampoActualizableTest extends TestCase
{
    public function test_campo_no_presente_conserva_el_valor_actual(): void
    {
        $resuelto = CampoActualizable::resolver(null, 'valor-actual');

        $this->assertSame('valor-actual', $resuelto);
    }

    public function test_campo_presente_con_null_limpia_el_valor(): void
    {
        $campo = CampoActualizable::conValor(null);

        $resuelto = CampoActualizable::resolver($campo, 'valor-actual');

        $this->assertNull($resuelto);
    }

    public function test_campo_presente_con_valor_reemplaza_el_valor_actual(): void
    {
        $campo = CampoActualizable::conValor('valor-nuevo');

        $resuelto = CampoActualizable::resolver($campo, 'valor-actual');

        $this->assertSame('valor-nuevo', $resuelto);
    }
}
