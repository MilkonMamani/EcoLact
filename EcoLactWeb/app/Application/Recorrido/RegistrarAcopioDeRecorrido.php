<?php

namespace App\Application\Recorrido;

use App\Application\Acopio\RegistrarEntrega;
use App\Application\Shared\TransactionManager;
use App\Domain\Acopiador\AcopiadorRepository;
use App\Domain\Entrega\Entrega;
use App\Domain\Proveedor\ProveedorRepository;
use App\Domain\Recorrido\RecorridoAcopioRepository;
use App\Domain\Recorrido\RecorridoDetalle;
use App\Domain\Recorrido\RecorridoDetalleRepository;
use App\Domain\Shared\Enums\EstadoParadaRecorrido;
use DateTimeImmutable;
use DomainException;

/**
 * Registra el acopio de una parada ya identificada dentro de un recorrido
 * abierto, y actualiza esa parada a RECOLECTADO, todo en una única
 * transacción: si falla el guardado de la entrega, la parada permanece
 * PENDIENTE (nunca se marca "recolectado" solo por seleccionar/escanear).
 *
 * $tokenIdempotencia protege contra doble envío/reintento/concurrencia: se
 * delega en RegistrarEntrega, que a su vez se apoya en el índice único de
 * entregas.token_idempotencia (ver docblock de esa clase).
 */
final class RegistrarAcopioDeRecorrido
{
    public function __construct(
        private readonly RecorridoAcopioRepository $recorridos,
        private readonly RecorridoDetalleRepository $recorridoDetalles,
        private readonly ProveedorRepository $proveedores,
        private readonly AcopiadorRepository $acopiadores,
        private readonly RegistrarEntrega $registrarEntrega,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(
        int $recorridoDetalleId,
        int $acopiadorUserId,
        float $litros,
        string $tokenIdempotencia,
        ?DateTimeImmutable $fechaHoraOrdeño = null,
        ?string $observacion = null,
    ): RegistroAcopioResultado {
        $parada = $this->recorridoDetalles->buscarPorId($recorridoDetalleId);

        if ($parada === null) {
            throw new DomainException('La parada indicada no existe.');
        }

        $recorrido = $this->recorridos->buscarPorId($parada->recorridoId());

        if ($recorrido === null) {
            throw new DomainException('El recorrido de esta parada no existe.');
        }

        $acopiador = $this->acopiadores->buscarPorUserId($acopiadorUserId);

        if ($acopiador === null || $recorrido->acopiadorId() !== $acopiador->id()) {
            throw new DomainException('No tienes permiso para registrar acopio en este recorrido.');
        }

        if (! $recorrido->estaAbierto()) {
            throw new DomainException('Este recorrido ya no admite nuevos registros.');
        }

        $proveedor = $this->proveedores->buscarPorId($parada->proveedorId());

        if ($proveedor === null) {
            throw new DomainException('El proveedor de esta parada no existe.');
        }

        return $this->transacciones->run(function () use (
            $parada, $proveedor, $acopiadorUserId, $litros, $tokenIdempotencia, $fechaHoraOrdeño, $observacion,
        ): RegistroAcopioResultado {
            $entrega = $this->registrarEntrega->execute(
                metodoIdentificacion: 'DNI',
                identificador: $proveedor->dni(),
                acopiadorUserId: $acopiadorUserId,
                litros: $litros,
                fechaHora: $fechaHoraOrdeño,
                observacion: $observacion,
                recorridoDetalleId: $parada->id(),
                tokenIdempotencia: $tokenIdempotencia,
            );

            $paradaActualizada = $this->recorridoDetalles->actualizar(new RecorridoDetalle(
                id: $parada->id(),
                recorridoId: $parada->recorridoId(),
                proveedorId: $parada->proveedorId(),
                rutaDetalleId: $parada->rutaDetalleId(),
                ordenVisita: $parada->ordenVisita(),
                latitud: $parada->latitud(),
                longitud: $parada->longitud(),
                referenciaUbicacion: $parada->referenciaUbicacion(),
                estado: EstadoParadaRecorrido::RECOLECTADO,
                observacion: $parada->observacion(),
            ));

            return new RegistroAcopioResultado($entrega, $paradaActualizada);
        });
    }
}
