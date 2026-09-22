<?php

namespace App\Application\Comunicacion;

use App\Domain\Comunicacion\Comunicado;
use App\Domain\Comunicacion\Repositories\ComunicadoRepository;
use DateTimeImmutable;
use DomainException;

final class PublicarComunicado
{
    public function __construct(
        private readonly ComunicadoRepository $comunicados,
    ) {}

    public function execute(int $comunicadoId): Comunicado
    {
        $comunicado = $this->comunicados->buscarPorId($comunicadoId);

        if ($comunicado === null) {
            throw new DomainException('El comunicado indicado no existe.');
        }

        if ($comunicado->estado() !== 'BORRADOR') {
            throw new DomainException('Solo se puede publicar un comunicado que esté en borrador.');
        }

        return $this->comunicados->actualizar(new Comunicado(
            id: $comunicado->id(),
            titulo: $comunicado->titulo(),
            contenido: $comunicado->contenido(),
            tipo: $comunicado->tipo(),
            alcance: $comunicado->alcance(),
            zonaId: $comunicado->zonaId(),
            fechaPublicacion: new DateTimeImmutable,
            fechaEvento: $comunicado->fechaEvento(),
            creadoPorUserId: $comunicado->creadoPorUserId(),
            estado: 'PUBLICADO',
        ));
    }
}
