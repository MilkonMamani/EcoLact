<?php

namespace App\Application\Acopio;

use App\Application\Pago\Contracts\EntregasPagablesQuery;
use App\Application\Pago\SincronizarPagosProveedor;
use App\Application\Produccion\RevertirIngresoLeche;
use App\Application\Shared\TransactionManager;
use App\Domain\Entrega\Entrega;
use App\Domain\Entrega\EntregaRepository;
use App\Domain\Shared\Enums\EstadoEntrega;
use DomainException;

/**
 * Anula una entrega registrada por error. Usa el estado ANULADA que ya existía
 * en EstadoEntrega (no se inventa ningún estado nuevo).
 *
 * Si la entrega ya ingresó leche al inventario, se registra un movimiento
 * REVERSO equivalente (el ingreso original no se borra, para conservar la
 * trazabilidad). Si la leche ya se consumió en producción y el stock no
 * alcanza para revertirla, la anulación se rechaza y no cambia nada.
 *
 * No se anula una entrega que ya forma parte de un pago realizado (o de una
 * liquidación anterior): revertirlo es otro proceso de negocio. Si el pago aún
 * está pendiente, se recalcula sin ella.
 */
final class AnularEntrega
{
    public function __construct(
        private readonly EntregaRepository $entregas,
        private readonly EntregasPagablesQuery $entregasPagables,
        private readonly SincronizarPagosProveedor $pagosProveedor,
        private readonly RevertirIngresoLeche $revertirIngresoLeche,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(int $entregaId, string $motivo, int $anuladaPorUserId): Entrega
    {
        if (trim($motivo) === '') {
            throw new DomainException('El motivo de anulación es obligatorio.');
        }

        $entrega = $this->entregas->buscarPorId($entregaId)
            ?? throw new DomainException('La entrega indicada no existe.');

        if ($entrega->estado() === EstadoEntrega::ANULADA) {
            throw new DomainException('La entrega ya está anulada.');
        }

        if ($this->entregasPagables->estaEnPagoRealizado($entregaId)) {
            throw new DomainException('No se puede anular una entrega que ya forma parte de un pago realizado al proveedor.');
        }

        return $this->transacciones->run(function () use ($entrega, $motivo, $anuladaPorUserId): Entrega {
            $this->revertirIngresoLeche->execute($entrega->id(), $anuladaPorUserId, $motivo);

            $anulada = $this->entregas->actualizar(new Entrega(
                id: $entrega->id(),
                proveedorId: $entrega->proveedorId(),
                acopiadorId: $entrega->acopiadorId(),
                zonaId: $entrega->zonaId(),
                registradoPorUserId: $entrega->registradoPorUserId(),
                fechaHora: $entrega->fechaHora(),
                litros: $entrega->litros(),
                metodoIdentificacion: $entrega->metodoIdentificacion(),
                estado: EstadoEntrega::ANULADA,
                observacion: trim(($entrega->observacion() ? $entrega->observacion().' | ' : '').'Anulada: '.$motivo),
                recorridoDetalleId: $entrega->recorridoDetalleId(),
                tokenIdempotencia: $entrega->tokenIdempotencia(),
            ));

            // La entrega anulada deja de contar: se recalcula el pago pendiente.
            $this->pagosProveedor->execute($entrega->proveedorId());

            return $anulada;
        });
    }
}
