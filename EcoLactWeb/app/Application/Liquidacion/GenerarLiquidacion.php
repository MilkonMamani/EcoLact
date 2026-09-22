<?php

namespace App\Application\Liquidacion;

use App\Application\Shared\TransactionManager;
use App\Domain\Comunicacion\Notificacion;
use App\Domain\Comunicacion\Repositories\NotificacionRepository;
use App\Domain\Pago\AjusteLiquidacion;
use App\Domain\Pago\AjusteLiquidacionRepository;
use App\Domain\Pago\Liquidacion;
use App\Domain\Pago\LiquidacionDetalle;
use App\Domain\Pago\LiquidacionDetalleRepository;
use App\Domain\Pago\LiquidacionRepository;
use App\Domain\Pago\PeriodoLiquidacion;
use App\Domain\Pago\PeriodoLiquidacionRepository;
use App\Domain\Proveedor\ProveedorRepository;
use App\Domain\Shared\Enums\EstadoLiquidacion;
use DateTimeImmutable;
use DomainException;

/**
 * Genera la liquidación semanal de un proveedor para un periodo dado (el
 * periodo real de EcoLact corre de jueves a miércoles). El proveedor NO
 * recibe un precio distinto por cada día: se suman todos los litros
 * pagables del periodo y se les aplica UN SOLO precio por litro para toda
 * la semana. Ese precio único baja (regla de pago por resultado OBSERVADA)
 * si CUALQUIER entrega del periodo fue OBSERVADA o RECHAZADA; si ninguna lo
 * fue, se paga el precio base (regla ACEPTADA) a todo el periodo.
 *
 * También descuenta automáticamente las compras de producto (ej. queso)
 * que el proveedor pidió a cuenta de este pago (ventas_producto con
 * forma_pago DESCUENTO_PLANILLA de este mismo periodo), además de los
 * ajustes manuales recibidos en $ajustes (ej. sanciones).
 */
final class GenerarLiquidacion
{
    public function __construct(
        private readonly CalcularLiquidacion $calcularLiquidacion,
        private readonly ProveedorRepository $proveedores,
        private readonly PeriodoLiquidacionRepository $periodos,
        private readonly LiquidacionRepository $liquidaciones,
        private readonly LiquidacionDetalleRepository $liquidacionDetalles,
        private readonly AjusteLiquidacionRepository $ajustesLiquidacion,
        private readonly NotificacionRepository $notificaciones,
        private readonly TransactionManager $transacciones,
    ) {}

    /**
     * @param  array<int, array{tipo: string, descripcion: string, monto: float, sancionId?: int|null, ventaProductoId?: int|null}>  $ajustes
     */
    public function execute(
        int $proveedorId,
        DateTimeImmutable $fechaInicio,
        DateTimeImmutable $fechaFin,
        int $generadoPorUserId,
        array $ajustes = [],
        bool $aplicaCalidad = true,
    ): Liquidacion {
        $proveedor = $this->proveedores->buscarPorId($proveedorId);

        if ($proveedor === null) {
            throw new DomainException('El proveedor indicado no existe.');
        }

        $periodo = $this->periodos->buscarPorFechas($fechaInicio, $fechaFin)
            ?? $this->periodos->guardar(new PeriodoLiquidacion(
                id: null,
                fechaInicio: $fechaInicio,
                fechaFin: $fechaFin,
                estado: 'ABIERTO',
            ));

        $periodoId = $periodo->id() ?? throw new DomainException('No se pudo determinar el periodo de liquidación.');

        foreach ($this->liquidaciones->listarPorProveedor($proveedorId) as $existente) {
            if ($existente->periodoId() === $periodoId) {
                throw new DomainException('Ya existe una liquidación para este proveedor en el periodo indicado.');
            }
        }

        $calculo = $this->calcularLiquidacion->execute($proveedorId, $fechaInicio, $fechaFin, $periodoId, $ajustes, $aplicaCalidad);
        $totalLitrosEntregados = $calculo['totalLitrosEntregados'];
        $totalLitrosPagables = $calculo['totalLitrosPagables'];
        $precioLitroAplicado = $calculo['precioLitroAplicado'];
        $subtotal = $calculo['subtotal'];
        $totalAjustes = $calculo['totalAjustes'];
        $totalPagar = $calculo['totalPagar'];
        $tuvoIncidencia = $calculo['tuvoIncidencia'];
        $detallesAPersistir = $calculo['detallesAPersistir'];
        $ajustes = $calculo['ajustes'];

        return $this->transacciones->run(function () use (
            $periodoId, $proveedorId, $totalLitrosEntregados, $totalLitrosPagables,
            $subtotal, $totalAjustes, $totalPagar, $generadoPorUserId,
            $precioLitroAplicado, $tuvoIncidencia, $detallesAPersistir, $ajustes, $proveedor,
        ): Liquidacion {
            $liquidacion = $this->liquidaciones->guardar(new Liquidacion(
                id: null,
                periodoId: $periodoId,
                proveedorId: $proveedorId,
                totalLitrosEntregados: round($totalLitrosEntregados, 2),
                totalLitrosPagables: round($totalLitrosPagables, 2),
                subtotal: $subtotal,
                totalAjustes: $totalAjustes,
                totalPagar: $totalPagar,
                estado: EstadoLiquidacion::PENDIENTE,
                fechaCalculo: new DateTimeImmutable,
                generadoPorUserId: $generadoPorUserId,
                precioLitroAplicado: $precioLitroAplicado,
                tuvoIncidencia: $tuvoIncidencia,
            ));

            $liquidacionId = $liquidacion->id() ?? throw new DomainException('No se pudo registrar la liquidación.');

            foreach ($detallesAPersistir as $valorizacion) {
                $this->liquidacionDetalles->guardar(new LiquidacionDetalle(
                    id: null,
                    liquidacionId: $liquidacionId,
                    valorizacionEntregaId: $valorizacion->id() ?? throw new DomainException('La valorización no tiene identificador válido.'),
                    importe: round($valorizacion->litrosPagables() * $precioLitroAplicado, 2),
                ));
            }

            foreach ($ajustes as $ajuste) {
                $this->ajustesLiquidacion->guardar(new AjusteLiquidacion(
                    id: null,
                    liquidacionId: $liquidacionId,
                    sancionId: $ajuste['sancionId'] ?? null,
                    tipo: $ajuste['tipo'],
                    descripcion: $ajuste['descripcion'],
                    monto: $ajuste['monto'],
                    creadoPorUserId: $generadoPorUserId,
                    ventaProductoId: $ajuste['ventaProductoId'] ?? null,
                ));
            }

            $this->notificaciones->guardar(new Notificacion(
                id: null,
                userId: $proveedor->userId(),
                tipo: 'LIQUIDACION_GENERADA',
                titulo: 'Liquidación generada',
                mensaje: sprintf('Se generó tu liquidación por un total de S/ %.2f.', $totalPagar),
                referenciaTipo: 'LIQUIDACION',
                referenciaId: $liquidacionId,
                leido: false,
            ));

            return $liquidacion;
        });
    }
}
