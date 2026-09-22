<?php

namespace App\Application\Comunicacion;

use App\Domain\Comunicacion\CatalogoComunicado;
use App\Domain\Comunicacion\Comunicado;
use App\Domain\Comunicacion\Repositories\ComunicadoRepository;
use DateTimeImmutable;
use DomainException;

/**
 * Un comunicado ya PUBLICADO es inmutable: modificarlo alteraría lo que sus
 * destinatarios ya recibieron. Solo se puede editar mientras está en
 * BORRADOR (misma decisión de negocio que ActualizarEntrega/AnalisisCalidad
 * ya aplican en sus propios módulos).
 */
final class ActualizarComunicado
{
    public function __construct(
        private readonly ComunicadoRepository $comunicados,
    ) {}

    public function execute(
        int $comunicadoId,
        ?string $titulo = null,
        ?string $contenido = null,
        ?string $tipo = null,
        ?DateTimeImmutable $fechaEvento = null,
    ): Comunicado {
        $comunicado = $this->comunicados->buscarPorId($comunicadoId);

        if ($comunicado === null) {
            throw new DomainException('El comunicado indicado no existe.');
        }

        if ($comunicado->estado() !== 'BORRADOR') {
            throw new DomainException('Solo se puede editar un comunicado mientras está en borrador.');
        }

        $tipoFinal = $tipo ?? $comunicado->tipo();

        if (! in_array($tipoFinal, CatalogoComunicado::TIPOS_VALIDOS, true)) {
            throw new DomainException('El tipo de comunicado indicado no es válido.');
        }

        return $this->comunicados->actualizar(new Comunicado(
            id: $comunicado->id(),
            titulo: $titulo ?? $comunicado->titulo(),
            contenido: $contenido ?? $comunicado->contenido(),
            tipo: $tipoFinal,
            alcance: $comunicado->alcance(),
            zonaId: $comunicado->zonaId(),
            fechaPublicacion: $comunicado->fechaPublicacion(),
            fechaEvento: $fechaEvento ?? $comunicado->fechaEvento(),
            creadoPorUserId: $comunicado->creadoPorUserId(),
            estado: $comunicado->estado(),
        ));
    }
}
