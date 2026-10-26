<?php

namespace App\Application\Pago;

use App\Application\Pago\Contracts\EntregasPagablesQuery;
use App\Application\Pago\Contracts\PagosPendientesProveedorStore;
use App\Application\Shared\TransactionManager;
use App\Domain\Pago\ConfiguracionPago;
use App\Domain\Pago\ConfiguracionPagoRepository;
use App\Domain\Pago\PeriodoPago;
use App\Domain\Pago\PrecioLecheRepository;
use App\Domain\Pago\ReglaPagoCalidadRepository;
use App\Domain\Shared\Enums\EstadoObligacionPago;
use App\Domain\Shared\Enums\ResultadoCalidad;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use DateTimeImmutable;

/**
 * Mantiene sincronizados, sin intervención del administrador, los pagos
 * PENDIENTES de los proveedores con lo que realmente entregaron.
 *
 * La calidad se liquida POR SEMANA con el PEOR resultado de los análisis
 * CONFIRMADOS de la semana (RECHAZADA > OBSERVADA > ACEPTADA):
 *
 * - ACEPTADA: todos los litros al precio base vigente el día de cada entrega.
 * - OBSERVADA: el descuento por litro de la regla OBSERVADA se aplica a TODOS
 *   los litros de la semana (no solo a la entrega observada), si la
 *   configuración del proveedor aplica calidad.
 * - RECHAZADA: la semana completa no se paga (0 litros pagables). Queda
 *   registrada como liquidación NO_PAGABLE con S/ 0.00 (visible en Pagar a
 *   proveedores, nunca pagable) y sus compras de planilla pasan a la
 *   siguiente semana pagable.
 *
 * Una entrega sin análisis confirmado no aporta resultado: se paga según el
 * resto de la semana (si ninguna tiene análisis, al precio base). El pendiente
 * se recalcula cuando se confirma un análisis, mientras no esté PAGADO.
 *
 * Las entregas se agrupan por proveedor y semana (jueves a miércoles) en UN
 * pago pendiente abierto, que se recalcula cada vez que llega una entrega, se
 * confirma un análisis o cambia un dato. Un pago PAGADO es inmutable: sus
 * entregas ya no se tocan, y las entregas posteriores de la misma semana
 * pasan a la siguiente semana disponible. Cualquier diferencia posterior sobre un pago
 * realizado requeriría un flujo de regularización (no existe todavía).
 *
 * Es idempotente y NUNCA lanza por faltas de configuración (sin configuración de
 * pago, sin precio de leche): esas entregas quedan sin asignar y se reportan,
 * para no bloquear el acopio ni la calidad. Debe ejecutarse dentro de la misma
 * transacción que el cambio que la origina.
 */
final class SincronizarPagosProveedor
{
    public function __construct(
        private readonly EntregasPagablesQuery $entregas,
        private readonly PagosPendientesProveedorStore $pendientes,
        private readonly ConfiguracionPagoRepository $configuraciones,
        private readonly PrecioLecheRepository $preciosLeche,
        private readonly ReglaPagoCalidadRepository $reglasPagoCalidad,
        private readonly TransactionManager $transacciones,
    ) {}

    /**
     * @param  int|null  $proveedorId  solo ese proveedor; nulo = todos
     */
    public function execute(?int $proveedorId = null, ?int $actorUserId = null): ResultadoGeneracionPagos
    {
        $resultado = new ResultadoGeneracionPagos;
        $configuraciones = $this->configuraciones->listarActivasPorTipo(TipoBeneficiarioPago::PROVEEDOR);

        if ($configuraciones === []) {
            return $resultado;
        }

        $proveedores = $proveedorId !== null
            ? [$proveedorId]
            : array_values(array_unique([
                ...array_map(fn (EntregaPagable $entrega): int => $entrega->proveedorId, $this->entregas->porAsignar()),
                ...$this->entregas->proveedoresConPendientes(),
            ]));

        foreach ($proveedores as $id) {
            // Las entregas se leen DENTRO de la transacción, tras bloquear los
            // pendientes: así un pago registrado a la vez no se pisa.
            $this->transacciones->run(function () use ($id, $configuraciones, $actorUserId, $resultado): void {
                $this->sincronizarProveedor($id, $configuraciones, $actorUserId, $resultado);
            });
        }

        return $resultado;
    }

    /**
     * @param  ConfiguracionPago[]  $configuraciones
     */
    private function sincronizarProveedor(int $proveedorId, array $configuraciones, ?int $actorUserId, ResultadoGeneracionPagos $resultado): void
    {
        $existentes = $this->pendientes->liberarPendientes($proveedorId);
        $cerradas = array_fill_keys($this->pendientes->semanasCerradas($proveedorId), true);
        $entregas = $this->entregas->porAsignar($proveedorId);
        $usados = [];
        $grupos = [];

        foreach ($entregas as $entrega) {
            $periodo = PeriodoPago::semanaQueContiene($entrega->fechaHora);
            while (isset($cerradas[$periodo->clave()])) {
                $periodo = $periodo->siguiente();
            }
            $grupos[$periodo->clave()][] = $entrega;
        }

        $resultadosSemanales = array_map(
            fn (array $grupo): ?ResultadoCalidad => ResultadoCalidad::peor(array_map(fn (EntregaPagable $entrega): ?ResultadoCalidad => $entrega->resultado, $grupo)),
            $grupos,
        );
        // Una semana RECHAZADA no paga: sus compras de planilla pasan a la siguiente semana pagable.
        $semanasSinPago = array_keys(array_filter($resultadosSemanales, fn (?ResultadoCalidad $r): bool => $r === ResultadoCalidad::RECHAZADA));

        foreach ($grupos as $clave => $grupo) {
            $periodo = PeriodoPago::semanaQueContiene(new DateTimeImmutable($clave));
            $nombre = $grupo[0]->proveedorNombre;
            $detalles = [];
            $configuracion = null;
            $resultadoSemanal = $resultadosSemanales[$clave];
            $noPagable = $resultadoSemanal === ResultadoCalidad::RECHAZADA;

            foreach ($grupo as $entrega) {
                $configuracion = $this->configuracionPara($configuraciones, $entrega->fechaHora);
                $detalle = $configuracion !== null ? $this->calcular($entrega, $configuracion, $resultadoSemanal) : null;

                if ($detalle === null) {
                    $resultado->omitir($nombre, $periodo->etiqueta().' ('.$periodo->rango().')', 'La entrega #'.$entrega->entregaId.' no tiene configuración de pago ni precio de leche vigentes.');

                    continue;
                }

                $detalles[] = $detalle;
            }

            $bruto = round(array_sum(array_map(fn (DetalleEntregaCalculado $d): float => $d->subtotal, $detalles)), 2);
            $descuentos = $noPagable ? [] : $this->pendientes->descuentosDisponibles($proveedorId, $periodo, $semanasSinPago);
            $totalDescuentos = round(array_sum(array_column($descuentos, 'importe')), 2);
            $monto = round($bruto - $totalDescuentos, 2);

            if ($detalles === [] || (! $noPagable && $monto <= 0.0)) {
                if ($detalles !== [] && $bruto > 0.0) {
                    $resultado->omitir($nombre, $periodo->etiqueta().' ('.$periodo->rango().')', 'Los descuentos de planilla igualan o superan el importe de la leche entregada; no hay monto que pagar.');
                }

                continue;
            }

            if ($noPagable) {
                $resultado->omitir($nombre, $periodo->etiqueta().' ('.$periodo->rango().')', 'La semana tiene una entrega RECHAZADA: la liquidación semanal no es pagable.');
            }

            $litrosEntregados = round(array_sum(array_map(fn (DetalleEntregaCalculado $d): float => $d->litrosEntregados, $detalles)), 2);
            $litrosPagables = round(array_sum(array_map(fn (DetalleEntregaCalculado $d): float => $d->litrosPagables, $detalles)), 2);
            $baseCalculo = [
                'tipo_calculo' => 'POR_LITROS',
                'litros_entregados' => $litrosEntregados,
                'litros_pagables' => $litrosPagables,
                // Ponderados por litros entregados: también se conocen en una semana RECHAZADA (0 L pagables).
                'precio_base_litro' => $litrosEntregados > 0 ? round(array_sum(array_map(fn (DetalleEntregaCalculado $d): float => $d->litrosEntregados * $d->precioBase, $detalles)) / $litrosEntregados, 4) : null,
                'ajuste_calidad_litro' => $litrosEntregados > 0 ? round(array_sum(array_map(fn (DetalleEntregaCalculado $d): float => $d->litrosEntregados * $d->ajuste, $detalles)) / $litrosEntregados, 4) : 0.0,
                'precio_litro_aplicado' => $litrosPagables > 0 ? round($bruto / $litrosPagables, 4) : 0.0,
                'aplica_calidad' => (bool) $configuracion?->aplicaCalidad(),
                'tuvo_incidencia' => count(array_filter($detalles, fn (DetalleEntregaCalculado $d): bool => $d->resultado !== null && $d->resultado !== ResultadoCalidad::ACEPTADA->value)) > 0,
                'resultado_semanal' => $resultadoSemanal?->value,
                'subtotal' => $bruto,
                'ajustes' => -$totalDescuentos,
                'descuentos' => $descuentos,
                'entregas' => array_map(fn (DetalleEntregaCalculado $d): int => $d->entregaId, $detalles),
            ];

            $usados[$clave] = true;

            $creada = $this->pendientes->guardarPendiente(
                $existentes[$clave] ?? null, $proveedorId, $nombre, $configuracion?->id(), $periodo, $noPagable ? 0.0 : $monto, $baseCalculo, $detalles, $actorUserId,
                $noPagable ? EstadoObligacionPago::NO_PAGABLE : EstadoObligacionPago::PENDIENTE,
            );

            // Una semana NO_PAGABLE queda registrada, pero no cuenta como pago generado.
            if (! $noPagable) {
                $creada ? $resultado->creadas++ : $resultado->existentes++;
            }
        }

        $this->pendientes->eliminarPendientes(array_values(array_diff_key($existentes, $usados)));
    }

    /**
     * Configuración vigente el día de la entrega; si la entrega es anterior a la
     * primera configuración, se usa la más antigua (esas entregas siguen sin
     * pagarse y no deben perderse).
     *
     * @param  ConfiguracionPago[]  $configuraciones
     */
    private function configuracionPara(array $configuraciones, DateTimeImmutable $fecha): ?ConfiguracionPago
    {
        foreach ($configuraciones as $configuracion) {
            if ($configuracion->estaVigente($fecha)) {
                return $configuracion;
            }
        }

        $primera = $configuraciones[0];

        foreach ($configuraciones as $configuracion) {
            if ($configuracion->fechaInicio() < $primera->fechaInicio()) {
                $primera = $configuracion;
            }
        }

        return $fecha->setTime(0, 0) < $primera->fechaInicio() ? $primera : null;
    }

    /**
     * Valoriza una entrega con el resultado de la SEMANA, no con el suyo: ACEPTADA
     * no lleva ajuste (precio normal), OBSERVADA aplica el descuento vigente el día
     * de la entrega y RECHAZADA deja la entrega sin litros pagables.
     */
    private function calcular(EntregaPagable $entrega, ConfiguracionPago $configuracion, ?ResultadoCalidad $resultadoSemanal): ?DetalleEntregaCalculado
    {
        $precio = $this->preciosLeche->buscarVigente($entrega->fechaHora);

        if ($precio === null) {
            return null;
        }

        $precioBase = $precio->precioLitro();
        $ajuste = 0.0;

        if ($configuracion->aplicaCalidad() && $resultadoSemanal === ResultadoCalidad::OBSERVADA) {
            $regla = $this->reglasPagoCalidad->buscarVigentePorResultado(ResultadoCalidad::OBSERVADA, $entrega->fechaHora);
            $ajuste = $regla !== null ? $regla->calcularAjuste($precioBase) : 0.0;
        }

        $precioFinal = max(0.0, round($precioBase + $ajuste, 4));
        $litrosPagables = $resultadoSemanal === ResultadoCalidad::RECHAZADA ? 0.0 : $entrega->litros;

        return new DetalleEntregaCalculado(
            entregaId: $entrega->entregaId,
            litrosEntregados: $entrega->litros,
            litrosPagables: $litrosPagables,
            resultado: $entrega->resultado?->value,
            precioBase: $precioBase,
            ajuste: $ajuste,
            precioFinal: $precioFinal,
            subtotal: round($litrosPagables * $precioFinal, 2),
        );
    }
}
