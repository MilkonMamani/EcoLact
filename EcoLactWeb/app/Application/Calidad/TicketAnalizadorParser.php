<?php

namespace App\Application\Calidad;

/**
 * Convierte el texto bruto que devuelve el OCR del ticket del analizador de
 * leche en valores numéricos indexados por código de parámetro de calidad.
 *
 * Solo interpreta texto: no ejecuta OCR, no consulta la base de datos ni
 * decide resultados. El ticket tiene un formato estable ("Grasa....... 4.5%"),
 * pero el OCR deforma etiquetas ("Proteinase", "ONG", "Punta cang") y
 * separadores ("4,5", "Grasa: 4.5"), por eso cada etiqueta se reconoce con
 * un patrón tolerante que debe aparecer al inicio de la línea, y el valor es
 * el primer número con decimales que sigue a la etiqueta (ver primerNumero).
 */
final class TicketAnalizadorParser
{
    /**
     * Patrones de etiqueta (sobre texto en minúsculas y sin tildes), en el
     * orden en que se evalúan para cada línea.
     *
     * @var array<string, string>
     */
    public const ETIQUETAS = [
        'TOTAL_SOLIDOS' => '/t[o0]t[a4]l|s[o0]l[i1l]d[o0]s/',
        'AGUA_ANADIDA' => '/agu[a4]|an[a4]d[i1l]d[a4]/',
        'PUNTO_CONGELACION' => '/punt|c[o0]ng/',
        'TEMPERATURA' => '/[tfl]?emp\b|temperatura/',
        'GRASA' => '/^\W*g\S{1,3}s|gr[a4]s/',
        'SNG' => '/^\W*[s5o0]\s?n\s?[gq69]\b/',
        'DENSIDAD' => '/den[s5]|[s5][i1l]dad|idad/',
        'PROTEINA' => '/pr[o0]t/',
        'LACTOSA' => '/act[o0]s|t[o0]sa|kosa/',
        'SALES' => '/^\W*[s5]\s?[a4]\s?[l1i]\s?[e3@]/',
        'PH' => '/^\W*[pd]\s?[hl1i]{1,2}\b/',
    ];

    /** Una etiqueta que empieza más allá de esta columna no es la de la línea. */
    private const COLUMNA_MAXIMA_ETIQUETA = 8;

    private const NUMERO = '/-?\d+(?:[.,]\d+)?/';

    /**
     * Combina varias lecturas OCR del mismo ticket: para cada parámetro gana
     * el valor que más lecturas coinciden en leer; ante un empate, el de la
     * lectura más temprana (las lecturas llegan en orden de preferencia).
     *
     * @param  list<string>  $textos
     * @return array<string, float>
     */
    public function parsearLecturas(array $textos): array
    {
        /** @var array<string, array<string, int>> $votos */
        $votos = [];

        foreach ($textos as $texto) {
            foreach ($this->parsear($texto) as $codigo => $valor) {
                $clave = (string) $valor;
                $votos[$codigo][$clave] = ($votos[$codigo][$clave] ?? 0) + 1;
            }
        }

        $valores = [];

        foreach (array_keys(self::ETIQUETAS) as $codigo) {
            if (! isset($votos[$codigo])) {
                continue;
            }

            // arsort es estable: entre valores empatados conserva el primero leído.
            arsort($votos[$codigo]);
            $valores[$codigo] = (float) array_key_first($votos[$codigo]);
        }

        return $valores;
    }

    /**
     * @return array<string, float> Valores detectados, por código de parámetro. Un parámetro que no se lee no aparece (nunca se completa con 0).
     */
    public function parsear(string $texto): array
    {
        $valores = [];

        foreach (preg_split('/\R/u', $texto) ?: [] as $lineaOriginal) {
            $linea = $this->normalizar($lineaOriginal);

            if ($linea === '') {
                continue;
            }

            foreach (self::ETIQUETAS as $codigo => $patron) {
                if (! preg_match($patron, $linea, $coincidencia, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                [$etiqueta, $columna] = $coincidencia[0];

                if ($columna > self::COLUMNA_MAXIMA_ETIQUETA) {
                    continue;
                }

                // La línea ya pertenece a esta etiqueta aunque su valor sea
                // ilegible: no se sigue probando con las demás.
                if (! array_key_exists($codigo, $valores)) {
                    $valor = $this->primerNumero(substr($linea, $columna + strlen($etiqueta)));

                    if ($valor !== null) {
                        $valores[$codigo] = $valor;
                    }
                }

                break;
            }
        }

        return $valores;
    }

    private function normalizar(string $linea): string
    {
        $linea = mb_strtolower(trim($linea), 'UTF-8');

        return strtr($linea, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }

    /**
     * El valor es el primer número CON decimales tras la etiqueta (el ticket
     * siempre imprime decimales: 15.9, 4.5, -0.551, 11.0). Un entero suelto
     * antes de él es ruido de los puntos guía ("SNG..6 8.7%", "Sales..00. 0.7%");
     * lo que venga después del valor también se ignora ("Temp. 15.9 0 ds").
     * Si la línea no trae ningún decimal se usa el primer número.
     */
    private function primerNumero(string $resto): ?float
    {
        // El OCR suele leer el cero inicial como letra o símbolo: "O.0%", "Q.7%", "-@.551".
        $resto = preg_replace('/(?<![a-z])[oq@](?=[.,]\d)/', '0', $resto) ?? $resto;

        if (! preg_match_all(self::NUMERO, $resto, $numeros)) {
            return null;
        }

        $conDecimales = array_values(array_filter($numeros[0], fn (string $numero): bool => (bool) preg_match('/[.,]/', $numero)));
        $valor = (float) str_replace(',', '.', $conDecimales[0] ?? $numeros[0][0]);

        // "-0.000" (punto de congelación) es simplemente 0.
        return $valor == 0.0 ? 0.0 : $valor;
    }
}
