<?php

namespace App\Application\Recorrido;

use App\Domain\Recorrido\RecorridoAcopio;
use App\Domain\Recorrido\RecorridoAcopioRepository;
use App\Domain\Shared\Enums\EstadoRecorrido;
use DateTimeImmutable;
use DomainException;

final class FinalizarRecorrido
{
    public function __construct(
        private readonly RecorridoAcopioRepository $recorridos,
    ) {}

    public function execute(int $recorridoId, int $acopiadorId): RecorridoAcopio
    {
        $recorrido = $this->recorridos->buscarPorId($recorridoId);

        if ($recorrido === null) {
            throw new DomainException('El recorrido indicado no existe.');
        }

        if ($recorrido->acopiadorId() !== $acopiadorId) {
            throw new DomainException('No tienes permiso para finalizar este recorrido.');
        }

        if (! $recorrido->estaAbierto()) {
            throw new DomainException('Este recorrido ya no admite cambios.');
        }

        return $this->recorridos->actualizar(new RecorridoAcopio(
            id: $recorrido->id(),
            rutaId: $recorrido->rutaId(),
            acopiadorId: $recorrido->acopiadorId(),
            fechaOperativa: $recorrido->fechaOperativa(),
            estado: EstadoRecorrido::FINALIZADO,
            iniciadoEn: $recorrido->iniciadoEn(),
            finalizadoEn: new DateTimeImmutable,
        ));
    }
}
