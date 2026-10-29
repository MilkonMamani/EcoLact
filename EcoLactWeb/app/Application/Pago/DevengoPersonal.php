<?php

namespace App\Application\Pago;

/**
 * Remuneración devengada por el personal en un período (CalcularDevengoPersonal).
 *
 * - total: devengado por días trabajados dentro del período (hasta el corte).
 * - enCursoAlCorte: devengado del mes del corte que aún no es una obligación
 *   cerrada (forma parte del saldo pendiente; no se cuenta dos veces).
 * - programadoEnCurso: devengado a la fecha de los meses EN_CURSO cuyo pago
 *   programado (día 1 del mes siguiente) cae en el período; es un estimado
 *   porque el mes aún no cierra.
 * - avisos: datos faltantes o inconsistentes; nunca se rellenan inventando valores.
 * - incidencias: los mismos avisos contados por categoría, para "Por atender".
 */
final readonly class DevengoPersonal
{
    /**
     * @param  list<array{rolId: int, rol: string, trabajadores: int, dias: int, importe: float}>  $porRol
     * @param  list<array{trabajadorId: int, nombre: string, rol: string, dias: int, importe: float}>  $porTrabajador
     * @param  list<string>  $avisos
     * @param  array{diasSinSueldo: int, rolesSinSueldo: list<string>, sinObligacion: int, mesesSinCerrar: list<string>, sueldoDistinto: int}  $incidencias
     */
    public function __construct(
        public float $total,
        public int $dias,
        public array $porRol,
        public array $porTrabajador,
        public float $enCursoAlCorte,
        public float $programadoEnCurso,
        public array $avisos,
        public array $incidencias = ['diasSinSueldo' => 0, 'rolesSinSueldo' => [], 'sinObligacion' => 0, 'mesesSinCerrar' => [], 'sueldoDistinto' => 0],
    ) {}
}
