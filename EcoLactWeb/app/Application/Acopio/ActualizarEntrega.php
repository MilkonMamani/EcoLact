<?php

namespace App\Application\Acopio;

use App\Application\Pago\SincronizarPagosProveedor;
use App\Application\Produccion\AjustarIngresoLecheDeEntrega;
use App\Application\Shared\TransactionManager;
use App\Domain\Entrega\Entrega;
use App\Domain\Entrega\EntregaRepository;
use App\Domain\Shared\Enums\EstadoEntrega;
use DateTimeImmutable;
use DomainException;

/**
 * Corrige los datos de una entrega ya registrada (proveedor, zona,
 * acopiador, fecha/hora, litros, método de identificación, observación).
 * Exige un motivo de modificación, pero NO lo persiste: la tabla
 * auditorias existe en el esquema pero, por instrucción explícita, ningún
 * Use Case escribe todavía en ella — inventar aquí un lugar alterno de
 * persistencia (p. ej. anexarlo a la observación) sería fabricar un
 * mecanismo de auditoría paralelo que nadie pidió.
 *
 * Solo puede editarse mientras la entrega sigue PENDIENTE_ANALISIS: una vez
 * analizada, ya puede tener valorización o formar parte de una liquidación,
 * así que queda inmutable (igual que un AnalisisCalidad CONFIRMADO).
 */
final class ActualizarEntrega
{
    public function __construct(
        private readonly EntregaRepository $entregas,
        private readonly AjustarIngresoLecheDeEntrega $ajustarIngresoLeche,
        private readonly SincronizarPagosProveedor $pagosProveedor,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(
        int $entregaId,
        string $motivoModificacion,
        ?int $proveedorId = null,
        ?int $zonaId = null,
        ?int $acopiadorId = null,
        ?DateTimeImmutable $fechaHora = null,
        ?float $litros = null,
        ?string $metodoIdentificacion = null,
        ?string $observacion = null,
    ): Entrega {
        if (trim($motivoModificacion) === '') {
            throw new DomainException('El motivo de modificación es obligatorio.');
        }

        $entrega = $this->entregas->buscarPorId($entregaId);

        if ($entrega === null) {
            throw new DomainException('La entrega indicada no existe.');
        }

        if ($entrega->estado() !== EstadoEntrega::PENDIENTE_ANALISIS) {
            throw new DomainException('Solo se puede editar una entrega mientras está pendiente de análisis.');
        }

        return $this->transacciones->run(function () use (
            $entrega, $proveedorId, $zonaId, $acopiadorId, $fechaHora, $litros, $metodoIdentificacion, $observacion,
        ): Entrega {
            $actualizada = $this->entregas->actualizar(new Entrega(
                id: $entrega->id(),
                proveedorId: $proveedorId ?? $entrega->proveedorId(),
                acopiadorId: $acopiadorId ?? $entrega->acopiadorId(),
                zonaId: $zonaId ?? $entrega->zonaId(),
                registradoPorUserId: $entrega->registradoPorUserId(),
                fechaHora: $fechaHora ?? $entrega->fechaHora(),
                litros: $litros ?? $entrega->litros(),
                metodoIdentificacion: $metodoIdentificacion ?? $entrega->metodoIdentificacion(),
                estado: $entrega->estado(),
                observacion: $observacion ?? $entrega->observacion(),
                recorridoDetalleId: $entrega->recorridoDetalleId(),
                tokenIdempotencia: $entrega->tokenIdempotencia(),
            ));

            // Si cambiaron los litros, el inventario de leche registra solo la
            // diferencia (sin borrar movimientos anteriores).
            if ($litros !== null && round($litros, 4) !== round($entrega->litros(), 4)) {
                $this->ajustarIngresoLeche->execute($entrega->id(), $litros, $entrega->registradoPorUserId());
            }

            // Litros, fecha o proveedor cambiaron: se recalculan los pagos PENDIENTES.
            $this->pagosProveedor->execute($entrega->proveedorId());

            if ($actualizada->proveedorId() !== $entrega->proveedorId()) {
                $this->pagosProveedor->execute($actualizada->proveedorId());
            }

            return $actualizada;
        });
    }
}
