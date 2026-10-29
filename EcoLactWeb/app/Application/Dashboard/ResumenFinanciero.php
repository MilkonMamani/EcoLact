<?php

namespace App\Application\Dashboard;

/**
 * Bloque financiero del dashboard con tres conceptos que nunca se mezclan:
 *
 * - devengado: valor económico generado en el período por la operación
 *   (leche valorizada por fecha de entrega; días trabajados por fecha de jornada).
 * - pendiente: saldo reconocido y no pagado a la fecha de corte (un saldo,
 *   no un flujo: no depende del filtro, solo de su corte).
 * - programado: obligaciones cuya fecha de pago programada cae en el período.
 *
 * pendienteReconocido excluye el devengo provisional del mes en curso de los
 * trabajadores (pendienteEnCurso); venceEnPeriodo es lo programado en el
 * período aún por pagar, también sin ese estimado.
 *
 * Los totales solo suman el mismo concepto entre proveedores y trabajadores.
 *
 * @phpstan-type Programado array{importe: float, pagado: float, porPagar: float, obligaciones: int}
 */
final readonly class ResumenFinanciero
{
    /**
     * @param  array{devengado: float, sujetoACalidad: float, entregas: int, litros: array{recibidos: float, enAnalisis: float, valorizados: float, noPagables: float, sinValorizar: float}, pendiente: float, pendienteObligaciones: int, vencido: float, vencidas: int, programado: array{importe: float, pagado: float, porPagar: float, obligaciones: int}, semana: ?string}  $proveedores
     * @param  array{devengado: float, dias: int, porRol: list<array{rolId: int, rol: string, trabajadores: int, dias: int, importe: float}>, porTrabajador: list<array{trabajadorId: int, nombre: string, rol: string, dias: int, importe: float}>, pendiente: float, pendienteCerrado: float, pendienteEnCurso: float, pendienteObligaciones: int, vencido: float, vencidas: int, programado: array{importe: float, pagado: float, porPagar: float, obligaciones: int, estimado: float}, avisos: list<string>, incidencias: array{diasSinSueldo: int, rolesSinSueldo: list<string>, sinObligacion: int, mesesSinCerrar: list<string>, sueldoDistinto: int}, mes: ?string}  $trabajadores
     * @param  array{devengado: float, pendiente: float, programado: float, programadoPorPagar: float, pendienteReconocido: float, venceEnPeriodo: float}  $totales
     */
    public function __construct(
        public string $corte,
        public array $proveedores,
        public array $trabajadores,
        public array $totales,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'corte' => $this->corte,
            'proveedores' => $this->proveedores,
            'trabajadores' => $this->trabajadores,
            'totales' => $this->totales,
        ];
    }
}
