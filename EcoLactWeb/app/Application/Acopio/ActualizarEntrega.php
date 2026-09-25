<?php

namespace App\Application\Acopio;

use App\Application\Pago\SincronizarPagosProveedor;
use App\Application\Produccion\AjustarIngresoLecheDeEntrega;
use App\Application\Shared\TransactionManager;
use App\Domain\Auditoria\AuditoriaRepository;
use App\Domain\Entrega\Entrega;
use App\Domain\Entrega\EntregaRepository;
use App\Domain\Shared\Enums\EstadoEntrega;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DomainException;

/**
 * Corrige los datos de una entrega ya registrada (proveedor, zona,
 * acopiador, fecha/hora, litros, método de identificación, observación).
 * Exige un motivo de modificación, que se guarda en `auditorias` junto con
 * los valores anteriores y nuevos de los campos que realmente cambiaron
 * (misma transacción que la actualización).
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
        private readonly AuditoriaRepository $auditorias,
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
        ?int $actorUserId = null,
        ?string $ip = null,
        ?string $userAgent = null,
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

        // Misma ventana que CorregirLitrosEntrega: pasada, los litros ya no cambian por ninguna vía.
        if ($litros !== null && round($litros, 2) !== round($entrega->litros(), 2)
            && ! $entrega->permiteEditarLitros(CarbonImmutable::now(config('app.timezone')))) {
            throw new DomainException(sprintf('No se pueden modificar los litros porque ya pasaron más de %d minutos desde la hora de acopio.', Entrega::MINUTOS_EDICION_LITROS));
        }

        return $this->transacciones->run(function () use (
            $entrega, $motivoModificacion, $proveedorId, $zonaId, $acopiadorId, $fechaHora, $litros, $metodoIdentificacion, $observacion,
            $actorUserId, $ip, $userAgent,
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

            $this->auditar($entrega, $actualizada, $motivoModificacion, $actorUserId, $ip, $userAgent);

            return $actualizada;
        });
    }

    /**
     * Registra en `auditorias` solo los campos que cambiaron; si nada cambió,
     * no deja registro.
     */
    private function auditar(Entrega $antes, Entrega $despues, string $motivo, ?int $actorUserId, ?string $ip, ?string $userAgent): void
    {
        $valoresAntes = $this->valoresAuditables($antes);
        $valoresDespues = $this->valoresAuditables($despues);
        $campos = array_keys(array_diff_assoc(array_map('strval', $valoresDespues), array_map('strval', $valoresAntes)));

        if ($campos === []) {
            return;
        }

        $this->auditorias->registrar(
            userId: $actorUserId,
            accion: 'ACTUALIZACION_ENTREGA',
            tablaAfectada: 'entregas',
            registroId: $antes->id(),
            datosAnteriores: array_intersect_key($valoresAntes, array_flip($campos)),
            datosNuevos: array_intersect_key($valoresDespues, array_flip($campos)),
            motivo: trim($motivo),
            ip: $ip,
            userAgent: $userAgent,
        );
    }

    /**
     * @return array{proveedor_id: int, zona_id: int, acopiador_id: int|null, fecha_hora: string, litros: float, metodo_identificacion: string, observacion: string|null}
     */
    private function valoresAuditables(Entrega $entrega): array
    {
        return [
            'proveedor_id' => $entrega->proveedorId(),
            'zona_id' => $entrega->zonaId(),
            'acopiador_id' => $entrega->acopiadorId(),
            'fecha_hora' => $entrega->fechaHora()->format('Y-m-d H:i:s'),
            'litros' => round($entrega->litros(), 2),
            'metodo_identificacion' => $entrega->metodoIdentificacion(),
            'observacion' => $entrega->observacion(),
        ];
    }
}
