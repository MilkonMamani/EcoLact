<?php

namespace App\Application\Recorrido;

use App\Domain\Acopiador\AcopiadorRepository;
use App\Domain\Recorrido\RecorridoAcopioRepository;
use App\Domain\Recorrido\RecorridoDetalle;
use App\Domain\Recorrido\RecorridoDetalleRepository;
use App\Domain\Shared\Enums\EstadoParadaRecorrido;
use DomainException;

/**
 * Marca una parada como "no entregó": acción explícita y distinta de
 * registrar un acopio, con motivo obligatorio. NO crea ninguna entrega
 * (ficticia ni de otro tipo). Idempotente: volver a marcar una parada que
 * ya está en NO_ENTREGO la devuelve tal cual; una parada RECOLECTADO no se
 * puede pasar a NO_ENTREGO (su entrega ya existe).
 */
final class MarcarNoEntrego
{
    public function __construct(
        private readonly RecorridoAcopioRepository $recorridos,
        private readonly RecorridoDetalleRepository $recorridoDetalles,
        private readonly AcopiadorRepository $acopiadores,
    ) {}

    public function execute(int $recorridoDetalleId, int $acopiadorUserId, string $motivo): RecorridoDetalle
    {
        if (trim($motivo) === '') {
            throw new DomainException('El motivo de "no entregó" es obligatorio.');
        }

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
            throw new DomainException('No tienes permiso para operar sobre este recorrido.');
        }

        // Reenvío de la misma marca (p. ej. la cola offline del móvil): no cambia nada.
        if ($parada->estado() === EstadoParadaRecorrido::NO_ENTREGO) {
            return $parada;
        }

        if ($parada->estado() === EstadoParadaRecorrido::RECOLECTADO) {
            throw new DomainException('Esta parada ya tiene un acopio registrado; no se puede marcar como "no entregó".');
        }

        if (! $recorrido->estaAbierto()) {
            throw new DomainException('Este recorrido ya no admite nuevos registros.');
        }

        return $this->recorridoDetalles->actualizar(new RecorridoDetalle(
            id: $parada->id(),
            recorridoId: $parada->recorridoId(),
            proveedorId: $parada->proveedorId(),
            rutaDetalleId: $parada->rutaDetalleId(),
            ordenVisita: $parada->ordenVisita(),
            latitud: $parada->latitud(),
            longitud: $parada->longitud(),
            referenciaUbicacion: $parada->referenciaUbicacion(),
            estado: EstadoParadaRecorrido::NO_ENTREGO,
            observacion: $motivo,
        ));
    }
}
