<?php

namespace Tests\Unit\Application\Calidad;

use App\Application\Calidad\TicketAnalizadorParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TicketAnalizadorParserTest extends TestCase
{
    public function test_lee_todos_los_parametros_de_un_ticket_limpio(): void
    {
        $texto = <<<'TXT'
        Analiz. de Leche  SmartScan AI
        SN: 48359  Mode: 1
        Temp. 15.9 C
        Grasa 4.5 %
        SNG 8.7 %
        Densidad 31.5
        Proteina 3.4 %
        Lactosa 4.7 %
        Sales 0.7 %
        Total solidos 13.2 %
        Agua añadida 0.0 %
        Punto cong. -0.551 C
        pH 11.0
        09:42   06/06/26
        TXT;

        $this->assertSame([
            'TEMPERATURA' => 15.9,
            'GRASA' => 4.5,
            'SNG' => 8.7,
            'DENSIDAD' => 31.5,
            'PROTEINA' => 3.4,
            'LACTOSA' => 4.7,
            'SALES' => 0.7,
            'TOTAL_SOLIDOS' => 13.2,
            'AGUA_ANADIDA' => 0.0,
            'PUNTO_CONGELACION' => -0.551,
            'PH' => 11.0,
        ], $this->ordenado((new TicketAnalizadorParser)->parsear($texto)));
    }

    /**
     * @return array<string, array{string, string, float}>
     */
    public static function variantes(): array
    {
        return [
            'coma decimal' => ['Grasa 4,5 %', 'GRASA', 4.5],
            'punto decimal' => ['Grasa 4.5 %', 'GRASA', 4.5],
            'dos puntos' => ['Grasa: 4.5', 'GRASA', 4.5],
            'puntos guía del ticket' => ['Grasa............. 4.5%', 'GRASA', 4.5],
            'proteína con tilde' => ['Proteína 3.4 %', 'PROTEINA', 3.4],
            'proteina sin tilde' => ['Proteina 3.4 %', 'PROTEINA', 3.4],
            'temp sin punto' => ['Temp 15.9 C', 'TEMPERATURA', 15.9],
            'temp con punto' => ['Temp. 15.9 C', 'TEMPERATURA', 15.9],
            'espacios múltiples' => ['Densidad      31.5', 'DENSIDAD', 31.5],
            'mayúsculas' => ['LACTOSA 4.7 %', 'LACTOSA', 4.7],
            'punto de congelación completo' => ['Punto congelación -0.551 C', 'PUNTO_CONGELACION', -0.551],
            'sólidos con tilde' => ['Total sólidos 13.2 %', 'TOTAL_SOLIDOS', 13.2],
            'agua anadida sin eñe' => ['Agua anadida 0.0 %', 'AGUA_ANADIDA', 0.0],
            'menos cero es cero' => ['Punto cong......-0.000 C', 'PUNTO_CONGELACION', 0.0],
            // Deformaciones reales de Tesseract con fotos del analizador.
            'OCR: Grasa como Giasa' => ['Giasa, eee eee 4.5% }', 'GRASA', 4.5],
            'OCR: SNG como ONG' => ['ONG ea ee en 8.7%', 'SNG', 8.7],
            'OCR: Sales como Sal@S' => ['Sal@Sic tin ceereces 0.7% Te', 'SALES', 0.7],
            'OCR: Punto como Punta' => ['Punta cong......-0.000 C t', 'PUNTO_CONGELACION', 0.0],
            'OCR: basura antes de la etiqueta' => ['4} Lactosa........... 4.7% bo', 'LACTOSA', 4.7],
            'OCR: cero leído como O' => ['Agua anadida...... O.0%', 'AGUA_ANADIDA', 0.0],
            'OCR: temperatura con basura después' => ['Temp. 15.9 0 ds', 'TEMPERATURA', 15.9],
            // Lecturas reales de Tesseract (eng, --psm 4) sobre tickets impresos de prueba.
            'OCR: puntos guía leídos como dígito' => ['SNG..6 8.7%', 'SNG', 8.7],
            'OCR: puntos guía leídos como 00.' => ['Sales..00. Q.7%', 'SALES', 0.7],
            'OCR: cero leído como Q' => ['Sales.. Q.7%', 'SALES', 0.7],
            'OCR: cero leído como @ en negativo' => ['Punto cong.. -@.551 C', 'PUNTO_CONGELACION', -0.551],
            'valor entero sin decimales' => ['Grasa 4 %', 'GRASA', 4.0],
        ];
    }

    #[DataProvider('variantes')]
    public function test_normaliza_variantes_de_etiqueta_y_numero(string $linea, string $codigo, float $esperado): void
    {
        $valores = (new TicketAnalizadorParser)->parsear($linea);

        $this->assertSame([$codigo => $esperado], $valores);
    }

    public function test_un_valor_ilegible_no_aparece_ni_se_completa_con_cero(): void
    {
        $valores = (new TicketAnalizadorParser)->parsear("Densidad. ......... SVS\nGrasa 4.5 %");

        $this->assertSame(['GRASA' => 4.5], $valores);
    }

    public function test_ignora_lineas_que_no_son_parametros(): void
    {
        $valores = (new TicketAnalizadorParser)->parsear("SN: 49731 Mode: 1\n15:50 02/09/26\nAnalizador de LACTOMAT");

        $this->assertSame([], $valores);
    }

    public function test_combina_lecturas_por_mayoria_y_desempata_con_la_primera(): void
    {
        $valores = (new TicketAnalizadorParser)->parsearLecturas([
            "Temp. 15.9 C\nLactosa 4.1 %\nSales 0.1 %",
            "Temp. 5.9 C\nLactosa 4.7 %",
            "Temp. 15.9 C\nLactosa 4.7 %\nSales 0.7 %",
        ]);

        $this->assertSame([
            'TEMPERATURA' => 15.9,
            'LACTOSA' => 4.7,
            'SALES' => 0.1,
        ], $this->ordenado($valores));
    }

    /**
     * @param  array<string, float>  $valores
     * @return array<string, float>
     */
    private function ordenado(array $valores): array
    {
        $orden = ['TEMPERATURA', 'GRASA', 'SNG', 'DENSIDAD', 'PROTEINA', 'LACTOSA', 'SALES', 'TOTAL_SOLIDOS', 'AGUA_ANADIDA', 'PUNTO_CONGELACION', 'PH'];

        return array_filter(array_merge(array_fill_keys($orden, null), $valores), static fn ($valor): bool => $valor !== null);
    }
}
