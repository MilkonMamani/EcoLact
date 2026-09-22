<?php

namespace App\Application\Produccion;

use App\Application\Shared\TransactionManager;
use App\Domain\Auth\Repositories\UsuarioRepository;
use App\Domain\Comunicacion\Notificacion;
use App\Domain\Comunicacion\Repositories\NotificacionRepository;
use App\Domain\Produccion\Insumo;
use App\Domain\Produccion\InsumoMovimiento;
use App\Domain\Produccion\InsumoRepository;
use App\Domain\Produccion\LoteProduccion;
use App\Domain\Produccion\LoteProduccionInsumo;
use App\Domain\Produccion\LoteProduccionInsumoRepository;
use App\Domain\Produccion\LoteProduccionRepository;
use App\Domain\Produccion\ProduccionDetalle;
use App\Domain\Produccion\ProduccionDetalleRepository;
use App\Domain\Shared\Enums\MotivoMovimientoInsumo;
use DateTimeImmutable;
use DomainException;

/**
 * INICIAR PRODUCCIÓN: el único momento en que se descuentan insumos.
 *
 * Todo ocurre en UNA transacción; si cualquier paso falla se deshace por
 * completo (ni lote, ni detalles, ni consumos, ni descuentos):
 *
 *   1. recalcula los requerimientos desde las recetas (no confía en lo que
 *      mostró la pantalla),
 *   2. vuelve a leer el stock bloqueando cada insumo (FOR UPDATE),
 *   3. verifica que haya receta para cada producto y stock suficiente,
 *   4. crea el lote EN_PROCESO (código LOT-00001...) y sus productos
 *      planificados, con la receta y versión usadas,
 *   5. guarda la fotografía de consumo del lote y descuenta cada insumo con un
 *      movimiento CONSUMO_PRODUCCION.
 *
 * Los productos terminados NO se ingresan aquí: eso ocurre al finalizar el
 * lote con lo realmente obtenido (FinalizarProduccion).
 */
final class IniciarProduccion
{
    public function __construct(
        private readonly CalcularRequerimientosProduccion $calcular,
        private readonly LoteProduccionRepository $lotes,
        private readonly ProduccionDetalleRepository $produccionDetalles,
        private readonly LoteProduccionInsumoRepository $consumos,
        private readonly InsumoRepository $insumos,
        private readonly RegistrarMovimientoInsumo $registrarMovimiento,
        private readonly NotificacionRepository $notificaciones,
        private readonly UsuarioRepository $usuarios,
        private readonly TransactionManager $transacciones,
    ) {}

    /**
     * @param  list<array{producto_id: int|string, cantidad: float|int|string}>  $lineas
     *
     * @throws ProduccionBloqueadaException si falta una receta o no alcanza un insumo.
     */
    public function execute(
        array $lineas,
        int $registradoPorUserId,
        ?string $observacion = null,
        ?DateTimeImmutable $fechaHoraInicio = null,
    ): LoteProduccion {
        return $this->transacciones->run(function () use ($lineas, $registradoPorUserId, $observacion, $fechaHoraInicio): LoteProduccion {
            $requerimientos = $this->calcular->execute($lineas, bloquearInsumos: true);

            if (! $requerimientos->puedeIniciar()) {
                throw new ProduccionBloqueadaException($requerimientos->mensajesDeBloqueo());
            }

            $lote = $this->lotes->guardar(new LoteProduccion(
                id: null,
                codigo: 'PENDIENTE',
                fechaHoraInicio: $fechaHoraInicio ?? new DateTimeImmutable,
                fechaHoraFin: null,
                estado: LoteProduccion::ESTADO_EN_PROCESO,
                registradoPorUserId: $registradoPorUserId,
                observacion: $observacion,
            ));

            $loteId = $lote->id() ?? throw new DomainException('No se pudo registrar el lote de producción.');

            $lote = $this->lotes->actualizar(new LoteProduccion(
                id: $loteId,
                codigo: sprintf('LOT-%05d', $loteId),
                fechaHoraInicio: $lote->fechaHoraInicio(),
                fechaHoraFin: null,
                estado: LoteProduccion::ESTADO_EN_PROCESO,
                registradoPorUserId: $registradoPorUserId,
                observacion: $observacion,
            ));

            foreach ($requerimientos->lineas as $linea) {
                $this->produccionDetalles->guardar(new ProduccionDetalle(
                    id: null,
                    loteProduccionId: $loteId,
                    productoId: $linea['productoId'],
                    cantidadPlanificada: $linea['cantidad'],
                    cantidadObtenida: null,
                    recetaId: $linea['recetaId'],
                    recetaVersion: $linea['recetaVersion'],
                ));
            }

            foreach ($requerimientos->insumos as $requerimiento) {
                $this->consumos->guardar(new LoteProduccionInsumo(
                    id: null,
                    loteProduccionId: $loteId,
                    insumoId: $requerimiento->insumoId,
                    cantidadUsada: $requerimiento->necesario,
                    cantidadRequerida: $requerimiento->necesario,
                    unidadBase: $requerimiento->unidadBase,
                ));

                $this->registrarMovimiento->execute(
                    insumoId: $requerimiento->insumoId,
                    motivo: MotivoMovimientoInsumo::CONSUMO_PRODUCCION,
                    direccion: MotivoMovimientoInsumo::SALIDA,
                    cantidad: $requerimiento->necesario,
                    registradoPorUserId: $registradoPorUserId,
                    observacion: sprintf('Consumo del lote %s.', $lote->codigo()),
                    origenTipo: InsumoMovimiento::ORIGEN_LOTE_PRODUCCION,
                    origenId: $loteId,
                    loteProduccionId: $loteId,
                    fecha: $lote->fechaHoraInicio(),
                );

                $insumo = $this->insumos->buscarPorId($requerimiento->insumoId);

                if ($insumo !== null && $insumo->stockMinimo() > 0.0 && $insumo->estaBajoMinimo()) {
                    $this->notificarStockBajo($insumo);
                }
            }

            return $lote;
        });
    }

    /**
     * Notifica a ADMINISTRADOR y ENCARGADO_PLANTA: el primero supervisa el
     * negocio, pero quien realmente debe reponer el insumo es planta.
     */
    private function notificarStockBajo(Insumo $insumo): void
    {
        $destinatarios = [
            ...$this->usuarios->listarActivosPorRole('ADMINISTRADOR'),
            ...$this->usuarios->listarActivosPorRole('ENCARGADO_PLANTA'),
        ];

        foreach ($destinatarios as $usuario) {
            $this->notificaciones->guardar(new Notificacion(
                id: null,
                userId: $usuario->id() ?? throw new DomainException('El usuario destinatario no tiene identificador válido.'),
                tipo: 'INSUMO_STOCK_BAJO',
                titulo: 'Stock bajo de insumo',
                mensaje: sprintf(
                    "El insumo '%s' quedó en %s %s, por debajo del mínimo (%s).",
                    $insumo->nombre(),
                    RegistrarMovimientoInsumo::formato($insumo->stockActual()),
                    $insumo->unidadBase(),
                    RegistrarMovimientoInsumo::formato($insumo->stockMinimo()),
                ),
                referenciaTipo: 'INSUMO',
                referenciaId: $insumo->id(),
                leido: false,
            ));
        }
    }
}
