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
use App\Domain\Shared\Enums\ResultadoCalidad;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use DateTimeImmutable;

/**
 * Mantiene sincronizados, sin intervención del administrador, los pagos
 * PENDIENTES de los proveedores con lo que realmente entregaron.
 *
 * Cada entrega válida (no anulada) genera deuda por sí misma: litros pagables x
 * (precio base vigente el día de la entrega + ajuste de calidad). El ajuste solo
 * se aplica si existe un análisis CONFIRMADO y la configuración del proveedor
 * activa los ajustes; sin análisis se paga el precio base. La leche RECHAZADA no
 * se paga (0 litros pagables): no ingresó a la planta.
 *
 * Las entregas se agrupan por proveedor y semana (jueves a miércoles) en UN
 * pago pendiente abierto, que se recalcula cada vez que llega una entrega, se
 * confirma un análisis o cambia un dato. Un pago PAGADO es inmutable: sus
 * entregas ya no se tocan, y las entregas posteriores de la misma semana
 * forman otro pago pendiente. Cualquier diferencia posterior sobre un pago
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
        $entregas = $this->entregas->porAsignar($proveedorId);
        $usados = [];
        $grupos = [];

        foreach ($entregas as $entrega) {
            $grupos[PeriodoPago::semanaQueContiene($entrega->fechaHora)->clave()][] = $entrega;
        }

        foreach ($grupos as $clave => $grupo) {
            $periodo = PeriodoPago::semanaQueContiene($grupo[0]->fechaHora);
            $nombre = $grupo[0]->proveedorNombre;
            $detalles = [];
            $configuracion = null;

            foreach ($grupo as $entrega) {
                $configuracion = $this->configuracionPara($configuraciones, $entrega->fechaHora);
                $detalle = $configuracion !== null ? $this->calcular($entrega, $configuracion) : null;

                if ($detalle === null) {
                    $resultado->omitir($nombre, $periodo->etiqueta().' ('.$periodo->rango().')', 'La entrega #'.$entrega->entregaId.' no tiene configuración de pago ni precio de leche vigentes.');

                    continue;
                }

                $detalles[] = $detalle;
            }

            $bruto = round(array_sum(array_map(fn (DetalleEntregaCalculado $d): float => $d->subtotal, $detalles)), 2);
            $descuentos = $this->pendientes->descuentosDisponibles($proveedorId, $periodo);
            $totalDescuentos = round(array_sum(array_column($descuentos, 'importe')), 2);
            $monto = round($bruto - $totalDescuentos, 2);

            if ($detalles === [] || $monto <= 0.0) {
                if ($detalles !== [] && $bruto > 0.0) {
                    $resultado->omitir($nombre, $periodo->etiqueta().' ('.$periodo->rango().')', 'Los descuentos de planilla igualan o superan el importe de la leche entregada; no hay monto que pagar.');
                }

                continue;
            }

            $litrosPagables = round(array_sum(array_map(fn (DetalleEntregaCalculado $d): float => $d->litrosPagables, $detalles)), 2);
            $baseCalculo = [
                'tipo_calculo' => 'POR_LITROS',
                'litros_entregados' => round(array_sum(array_map(fn (DetalleEntregaCalculado $d): float => $d->litrosEntregados, $detalles)), 2),
                'litros_pagables' => $litrosPagables,
                'precio_base_litro' => $litrosPagables > 0 ? round(array_sum(array_map(fn (DetalleEntregaCalculado $d): float => $d->litrosPagables * $d->precioBase, $detalles)) / $litrosPagables, 4) : null,
                'precio_litro_aplicado' => $litrosPagables > 0 ? round($bruto / $litrosPagables, 4) : null,
                'aplica_calidad' => (bool) $configuracion?->aplicaCalidad(),
                'tuvo_incidencia' => count(array_filter($detalles, fn (DetalleEntregaCalculado $d): bool => $d->resultado !== null && $d->resultado !== ResultadoCalidad::ACEPTADA->value)) > 0,
                'subtotal' => $bruto,
                'ajustes' => -$totalDescuentos,
                'descuentos' => $descuentos,
                'entregas' => array_map(fn (DetalleEntregaCalculado $d): int => $d->entregaId, $detalles),
            ];

            $usados[$clave] = true;

            if ($this->pendientes->guardarPendiente($existentes[$clave] ?? null, $proveedorId, $nombre, $configuracion?->id(), $periodo, $monto, $baseCalculo, $detalles, $actorUserId)) {
                $resultado->creadas++;
            } else {
                $resultado->existentes++;
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

    private function calcular(EntregaPagable $entrega, ConfiguracionPago $configuracion): ?DetalleEntregaCalculado
    {
        $precio = $this->preciosLeche->buscarVigente($entrega->fechaHora);

        if ($precio === null) {
            return null;
        }

        $precioBase = $precio->precioLitro();
        $ajuste = 0.0;

        if ($configuracion->aplicaCalidad() && $entrega->resultado !== null && $entrega->resultado !== ResultadoCalidad::RECHAZADA) {
            $regla = $this->reglasPagoCalidad->buscarVigentePorResultado($entrega->resultado, $entrega->fechaHora);
            $ajuste = $regla !== null ? $regla->calcularAjuste($precioBase) : 0.0;
        }

        $precioFinal = max(0.0, round($precioBase + $ajuste, 4));
        $litrosPagables = $entrega->resultado === ResultadoCalidad::RECHAZADA ? 0.0 : $entrega->litros;

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
