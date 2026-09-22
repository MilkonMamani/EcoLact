<?php

namespace App\Application\Shared;

/**
 * Distingue, para un campo nullable de un Use Case de actualización, entre
 * tres estados que un simple `?T $valor = null` no puede diferenciar:
 *
 *  - NO PRESENTE: el parámetro no se envía en la llamada → se conserva el
 *    valor actual (no se instancia CampoActualizable, queda en null).
 *  - PRESENTE CON NULL: se envía explícitamente para limpiar el campo →
 *    CampoActualizable::conValor(null).
 *  - PRESENTE CON VALOR: se envía un valor nuevo → CampoActualizable::conValor($valor).
 *
 * Es un objeto puro de Application (sin dependencia de Illuminate\Http ni
 * de ningún framework), pensado para campos de Domain genuinamente
 * nullable (por ejemplo, fecha_fin o valor_minimo/valor_maximo de una
 * regla de calidad).
 *
 * @template T
 */
final class CampoActualizable
{
    private function __construct(
        private readonly mixed $valor,
    ) {}

    /**
     * @template U
     *
     * @param  U  $valor
     * @return self<U>
     */
    public static function conValor(mixed $valor): self
    {
        return new self($valor);
    }

    /**
     * @return T
     */
    public function valor(): mixed
    {
        return $this->valor;
    }

    /**
     * Resuelve el valor final a partir del valor actual: si este campo fue
     * enviado (fue instanciado), su valor gana (aunque sea null); si no fue
     * enviado, se conserva el valor actual.
     *
     * @param  T  $valorActual
     * @return T
     */
    public static function resolver(?self $campo, mixed $valorActual): mixed
    {
        return $campo instanceof self ? $campo->valor() : $valorActual;
    }
}
