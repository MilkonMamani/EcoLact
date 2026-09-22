<?php

namespace Tests\Unit\Domain\Calidad;

use App\Domain\Calidad\AnalisisCalidad;
use App\Domain\Shared\Enums\EstadoAnalisis;
use App\Domain\Shared\Enums\ResultadoCalidad;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

class AnalisisCalidadTest extends TestCase
{
    public function test_crea_un_analisis_en_borrador(): void
    {
        $analisis = new AnalisisCalidad(
            id: null,
            entregaId: 10,
            imagenPath: null,
            estado: EstadoAnalisis::BORRADOR,
            resultado: null,
            fechaAnalisis: new DateTimeImmutable('2026-09-09 09:00:00'),
            confirmadoPorUserId: null,
        );

        $this->assertSame(EstadoAnalisis::BORRADOR, $analisis->estado());
        $this->assertNull($analisis->resultado());
        $this->assertFalse($analisis->estaConfirmado());
    }

    public function test_confirmar_fija_el_resultado_y_el_estado(): void
    {
        $analisis = new AnalisisCalidad(
            id: null,
            entregaId: 10,
            imagenPath: null,
            estado: EstadoAnalisis::BORRADOR,
            resultado: null,
            fechaAnalisis: new DateTimeImmutable,
            confirmadoPorUserId: null,
        );

        $analisis->confirmar(ResultadoCalidad::ACEPTADA, 5);

        $this->assertTrue($analisis->estaConfirmado());
        $this->assertSame(ResultadoCalidad::ACEPTADA, $analisis->resultado());
        $this->assertSame(5, $analisis->confirmadoPorUserId());
    }

    public function test_un_analisis_confirmado_es_inmutable(): void
    {
        $analisis = new AnalisisCalidad(
            id: null,
            entregaId: 10,
            imagenPath: null,
            estado: EstadoAnalisis::BORRADOR,
            resultado: null,
            fechaAnalisis: new DateTimeImmutable,
            confirmadoPorUserId: null,
        );
        $analisis->confirmar(ResultadoCalidad::ACEPTADA, 5);

        $this->expectException(DomainException::class);

        $analisis->confirmar(ResultadoCalidad::RECHAZADA, 6);
    }
}
