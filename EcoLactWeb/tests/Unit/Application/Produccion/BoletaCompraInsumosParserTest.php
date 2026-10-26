<?php

namespace Tests\Unit\Application\Produccion;

use App\Application\Produccion\BoletaCompraInsumosParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BoletaCompraInsumosParserTest extends TestCase
{
    private const PRESENTACIONES = ['Saco', 'Balde', 'Saco grande', 'Bidón'];

    private const INSUMOS = ['Sal', 'Cuajo', 'Azúcar', 'Cultivo láctico'];

    /**
     * @return list<array{texto: string, cantidad: float, presentacion: ?string, descripcion: string}>
     */
    private function lineas(string $texto): array
    {
        return (new BoletaCompraInsumosParser)->parsear($texto, self::PRESENTACIONES, self::INSUMOS)['lineas'];
    }

    public function test_lee_cantidad_presentacion_e_insumo_de_cada_linea(): void
    {
        $lineas = $this->lineas("2 SACOS SAL\n1 BALDE CUAJO");

        $this->assertSame([
            ['texto' => '2 SACOS SAL', 'cantidad' => 2.0, 'presentacion' => 'saco', 'descripcion' => 'sal'],
            ['texto' => '1 BALDE CUAJO', 'cantidad' => 1.0, 'presentacion' => 'balde', 'descripcion' => 'cuajo'],
        ], $lineas);
    }

    /**
     * @return array<string, array{string, float, string, string}>
     */
    public static function variaciones(): array
    {
        return [
            'presentación en singular' => ['2 SACO SAL', 2.0, 'saco', 'sal'],
            'con x' => ['2 x SACO SAL', 2.0, 'saco', 'sal'],
            'x pegada al número' => ['2X SACO SAL', 2.0, 'saco', 'sal'],
            'insumo primero' => ['SAL 2 SACOS', 2.0, 'saco', 'sal'],
            'con conector de' => ['1 BALDE DE CUAJO', 1.0, 'balde', 'cuajo'],
            'insumo y luego presentación' => ['CUAJO 1 BALDE', 1.0, 'balde', 'cuajo'],
            'acentos y plural' => ['3 sacos azúcar', 3.0, 'saco', 'azucar'],
            'plural en -es' => ['4 BIDONES CUAJO', 4.0, 'bidon', 'cuajo'],
            'con precios al final' => ['2 SACOS SAL 45.00 90.00', 2.0, 'saco', 'sal'],
            'con peso pegado' => ['2 SACOS SAL 25KG', 2.0, 'saco', 'sal'],
            'cantidad decimal con coma' => ['1,5 BALDES CUAJO', 1.5, 'balde', 'cuajo'],
        ];
    }

    #[DataProvider('variaciones')]
    public function test_acepta_variaciones_de_orden_conectores_y_formato(string $texto, float $cantidad, string $presentacion, string $descripcion): void
    {
        $lineas = $this->lineas($texto);

        $this->assertCount(1, $lineas);
        $this->assertSame($cantidad, $lineas[0]['cantidad']);
        $this->assertSame($presentacion, $lineas[0]['presentacion']);
        $this->assertSame($descripcion, $lineas[0]['descripcion']);
    }

    public function test_una_linea_sin_presentacion_cuenta_solo_si_nombra_un_insumo_conocido(): void
    {
        $lineas = $this->lineas("1 CUAJO\n3 SERVICIO DE TRANSPORTE");

        $this->assertCount(1, $lineas);
        $this->assertNull($lineas[0]['presentacion']);
        $this->assertSame('cuajo', $lineas[0]['descripcion']);
    }

    public function test_con_presentacion_conocida_conserva_el_texto_de_un_insumo_desconocido(): void
    {
        $lineas = $this->lineas('2 BALDES CONSERVANTE X');

        $this->assertSame('balde', $lineas[0]['presentacion']);
        $this->assertSame('conservante', $lineas[0]['descripcion']);
    }

    public function test_ignora_totales_impuestos_y_lineas_sin_cantidad(): void
    {
        $lineas = $this->lineas("OP. GRAVADA 76.27\nIGV 13.73\nTOTAL 90.00\nSAL DE MESA\n2 SACOS SAL");

        $this->assertCount(1, $lineas);
        $this->assertSame('2 SACOS SAL', $lineas[0]['texto']);
    }

    public function test_propone_los_datos_del_comprobante_si_los_encuentra(): void
    {
        $texto = <<<'TXT'
        DISTRIBUIDORA ANDINA S.A.C.
        RUC 20601234567
        BOLETA DE VENTA ELECTRONICA
        B001-000203
        FECHA: 26/10/2026
        2 SACOS SAL 45.00 90.00
        SUBTOTAL 76.27
        IMPORTE TOTAL S/ 90.00
        TXT;

        $cabecera = (new BoletaCompraInsumosParser)->parsear($texto, self::PRESENTACIONES, self::INSUMOS)['cabecera'];

        $this->assertSame([
            'proveedor' => 'DISTRIBUIDORA ANDINA S.A.C.',
            'ruc' => '20601234567',
            'numero_comprobante' => 'B001-000203',
            'fecha' => '2026-10-26',
            'total' => 90.0,
        ], $cabecera);
    }

    public function test_sin_datos_del_comprobante_no_inventa_nada(): void
    {
        $cabecera = (new BoletaCompraInsumosParser)->parsear("2 SACOS SAL\n1 BALDE CUAJO", self::PRESENTACIONES, self::INSUMOS)['cabecera'];

        $this->assertNull($cabecera['ruc']);
        $this->assertNull($cabecera['numero_comprobante']);
        $this->assertNull($cabecera['fecha']);
        $this->assertNull($cabecera['total']);
    }
}
