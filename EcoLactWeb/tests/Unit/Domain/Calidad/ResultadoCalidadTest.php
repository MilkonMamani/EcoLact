<?php

namespace Tests\Unit\Domain\Calidad;

use App\Domain\Shared\Enums\ResultadoCalidad;
use PHPUnit\Framework\TestCase;

class ResultadoCalidadTest extends TestCase
{
    public function test_contiene_exactamente_los_tres_resultados_definidos(): void
    {
        $valores = array_map(fn (ResultadoCalidad $caso) => $caso->value, ResultadoCalidad::cases());

        $this->assertCount(3, $valores);
        $this->assertEqualsCanonicalizing(['ACEPTADA', 'OBSERVADA', 'RECHAZADA'], $valores);
    }
}
