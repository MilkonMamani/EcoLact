<?php

namespace App\Application\Comunicacion;

use App\Domain\Comunicacion\CatalogoComunicado;
use App\Domain\Comunicacion\Comunicado;
use App\Domain\Comunicacion\Repositories\ComunicadoRepository;
use App\Domain\Proveedor\Proveedor;
use App\Domain\Proveedor\ProveedorRepository;
use App\Domain\Shared\Enums\EstadoUsuario;
use DateTimeImmutable;
use DomainException;

final class CrearComunicado
{
    public function __construct(
        private readonly ComunicadoRepository $comunicados,
        private readonly ProveedorRepository $proveedores,
    ) {}

    /**
     * @param  int[]  $destinatarioUserIds  Solo se usa cuando $alcance es 'INDIVIDUAL': para
     *                                      'ZONA' y 'GENERAL' los destinatarios se resuelven
     *                                      automáticamente a partir de los proveedores activos.
     */
    public function execute(
        string $titulo,
        string $contenido,
        string $tipo,
        string $alcance,
        int $creadoPorUserId,
        array $destinatarioUserIds = [],
        ?int $zonaId = null,
        ?DateTimeImmutable $fechaEvento = null,
        string $estado = 'PUBLICADO',
        ?DateTimeImmutable $fechaPublicacion = null,
    ): Comunicado {
        if (! in_array($tipo, CatalogoComunicado::TIPOS_VALIDOS, true)) {
            throw new DomainException('El tipo de comunicado indicado no es válido.');
        }

        if (! in_array($alcance, CatalogoComunicado::ALCANCES_VALIDOS, true)) {
            throw new DomainException('El alcance de comunicado indicado no es válido.');
        }

        if (! in_array($estado, CatalogoComunicado::ESTADOS_VALIDOS, true)) {
            throw new DomainException('El estado de comunicado indicado no es válido.');
        }

        if ($alcance === 'ZONA' && $zonaId === null) {
            throw new DomainException('Debes indicar la zona cuando el alcance es por zona.');
        }

        $destinatarios = match ($alcance) {
            'ZONA' => $this->userIdsDeProveedoresActivos($this->proveedores->listarPorZona($zonaId)),
            'GENERAL' => $this->userIdsDeProveedoresActivos($this->proveedores->listar()),
            default => $destinatarioUserIds,
        };

        $comunicado = $this->comunicados->guardar(new Comunicado(
            id: null,
            titulo: $titulo,
            contenido: $contenido,
            tipo: $tipo,
            alcance: $alcance,
            zonaId: $zonaId,
            fechaPublicacion: $fechaPublicacion ?? new DateTimeImmutable,
            fechaEvento: $fechaEvento,
            creadoPorUserId: $creadoPorUserId,
            estado: $estado,
        ));

        if ($destinatarios !== []) {
            $comunicadoId = $comunicado->id() ?? throw new DomainException('No se pudo registrar el comunicado.');

            $this->comunicados->agregarDestinatarios($comunicadoId, $destinatarios);
        }

        return $comunicado;
    }

    /**
     * @param  Proveedor[]  $proveedores
     * @return int[]
     */
    private function userIdsDeProveedoresActivos(array $proveedores): array
    {
        return array_values(array_map(
            static fn ($proveedor): int => $proveedor->userId(),
            array_filter($proveedores, static fn ($proveedor): bool => $proveedor->estado() === EstadoUsuario::ACTIVO),
        ));
    }
}
