<?php

namespace App\Application\Acopio;

use App\Application\Pago\Contracts\EntregasPagablesQuery;
use App\Application\Pago\SincronizarPagosProveedor;
use App\Application\Produccion\AjustarIngresoLecheDeEntrega;
use App\Application\Shared\TransactionManager;
use App\Domain\Auditoria\AuditoriaRepository;
use App\Domain\Entrega\Entrega;
use App\Domain\Entrega\EntregaRepository;
use App\Domain\Shared\Enums\EstadoEntrega;
use Carbon\CarbonImmutable;
use DomainException;

/**
 * Corrige SOLO los litros de una entrega (edición inline del modal "Ver
 * entrega"), dejando rastro en `auditorias` con el valor anterior y el nuevo.
 *
 * Todo ocurre en una única transacción: actualizar la entrega, ajustar el
 * inventario de leche por la diferencia, recalcular el pago pendiente del
 * proveedor y registrar la auditoría. Si cualquiera de esos pasos falla no
 * queda ni la entrega modificada sin trazabilidad ni una auditoría huérfana.
 *
 * Mismas restricciones que el resto del módulo: solo se corrige mientras la
 * entrega sigue PENDIENTE_ANALISIS (ActualizarEntrega) y nunca si ya forma
 * parte de un pago realizado al proveedor (AnularEntrega), porque un pago
 * PAGADO es inmutable. Además, los litros solo se corrigen dentro de los
 * primeros Entrega::MINUTOS_EDICION_LITROS minutos desde la hora de acopio.
 */
final class CorregirLitrosEntrega
{
    /** Valor de `auditorias.accion` para este cambio (máx. 30 caracteres). */
    public const ACCION_AUDITORIA = 'CORRECCION_LITROS_ENTREGA';

    public function __construct(
        private readonly EntregaRepository $entregas,
        private readonly EntregasPagablesQuery $entregasPagables,
        private readonly AjustarIngresoLecheDeEntrega $ajustarIngresoLeche,
        private readonly SincronizarPagosProveedor $pagosProveedor,
        private readonly AuditoriaRepository $auditorias,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(int $entregaId, float $litros, int $adminUserId, ?string $ip = null, ?string $userAgent = null): Entrega
    {
        $litros = round($litros, 2);

        if ($litros <= 0) {
            throw new DomainException('Los litros deben ser mayores a 0.');
        }

        return $this->transacciones->run(function () use ($entregaId, $litros, $adminUserId, $ip, $userAgent): Entrega {
            $entrega = $this->entregas->buscarPorId($entregaId)
                ?? throw new DomainException('La entrega indicada no existe.');

            if ($entrega->estado() === EstadoEntrega::ANULADA) {
                throw new DomainException('No se puede modificar esta entrega porque está anulada.');
            }

            if ($entrega->estado() !== EstadoEntrega::PENDIENTE_ANALISIS) {
                throw new DomainException('No se puede modificar esta entrega porque ya tiene un análisis de calidad confirmado.');
            }

            if (! $entrega->permiteEditarLitros(CarbonImmutable::now(config('app.timezone')))) {
                throw new DomainException(sprintf('No se puede modificar esta entrega porque ya pasaron más de %d minutos desde la hora de acopio.', Entrega::MINUTOS_EDICION_LITROS));
            }

            if ($this->entregasPagables->estaEnPagoRealizado($entregaId)) {
                throw new DomainException('No se puede modificar esta entrega porque ya forma parte de un pago realizado al proveedor.');
            }

            $litrosAnteriores = round($entrega->litros(), 2);

            if ($litrosAnteriores === $litros) {
                throw new DomainException('Los litros ingresados son iguales a los registrados.');
            }

            $actualizada = $this->entregas->actualizar(new Entrega(
                id: $entrega->id(),
                proveedorId: $entrega->proveedorId(),
                acopiadorId: $entrega->acopiadorId(),
                zonaId: $entrega->zonaId(),
                registradoPorUserId: $entrega->registradoPorUserId(),
                fechaHora: $entrega->fechaHora(),
                litros: $litros,
                metodoIdentificacion: $entrega->metodoIdentificacion(),
                estado: $entrega->estado(),
                observacion: $entrega->observacion(),
                recorridoDetalleId: $entrega->recorridoDetalleId(),
                tokenIdempotencia: $entrega->tokenIdempotencia(),
            ));

            // El inventario de leche registra solo la diferencia y el pago
            // PENDIENTE del proveedor se recalcula con los nuevos litros.
            $this->ajustarIngresoLeche->execute($entregaId, $litros, $adminUserId);
            $this->pagosProveedor->execute($entrega->proveedorId(), $adminUserId);

            $this->auditorias->registrar(
                userId: $adminUserId,
                accion: self::ACCION_AUDITORIA,
                tablaAfectada: 'entregas',
                registroId: $entregaId,
                datosAnteriores: ['litros' => $litrosAnteriores],
                datosNuevos: ['litros' => $litros],
                motivo: null,
                ip: $ip,
                userAgent: $userAgent,
            );

            return $actualizada;
        });
    }
}
