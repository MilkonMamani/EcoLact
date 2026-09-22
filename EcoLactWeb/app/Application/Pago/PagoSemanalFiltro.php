<?php

namespace App\Application\Pago;

/**
 * Filtros de la pantalla "Pago semanal a proveedores", ya normalizados
 * (valores inválidos se ignoran).
 *
 * - Semana: una fecha de inicio (Y-m-d), "todos" (todo el historial) o, sin valor,
 *   la semana más reciente con pagos pendientes.
 * - Estado: sin valor se muestran pendientes y pagados; un pago PAGADO es histórico
 *   y visible, solo deja de ser accionable.
 */
final class PagoSemanalFiltro
{
    public const PENDIENTES = 'pendientes';

    public const PAGADOS = 'pagados';

    public const TODOS = 'todos';

    /** Valor de ?semana= que significa "todo el historial". */
    public const SEMANA_TODAS = 'todos';

    private function __construct(
        public readonly ?string $semana,
        public readonly bool $todasLasSemanas,
        public readonly ?int $zonaId,
        public readonly string $estado,
    ) {}

    /**
     * @param  string|null  $semana  fecha de inicio del periodo (Y-m-d) o "todos"
     */
    public static function resolver(?string $semana = null, ?string $zona = null, ?string $estado = null): self
    {
        $todas = $semana !== null && strtolower(trim($semana)) === self::SEMANA_TODAS;
        $semana = ! $todas && $semana !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $semana) === 1 ? $semana : null;
        $zonaId = $zona !== null && ctype_digit($zona) && (int) $zona > 0 ? (int) $zona : null;
        $estado = in_array($estado, [self::PENDIENTES, self::PAGADOS, self::TODOS], true) ? $estado : self::TODOS;

        return new self($semana, $todas, $zonaId, $estado);
    }
}
