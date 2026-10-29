<?php

namespace App\Application\Produccion;

use App\Domain\Auth\Repositories\UsuarioRepository;
use App\Domain\Comunicacion\Notificacion;
use App\Domain\Comunicacion\Repositories\NotificacionRepository;
use App\Domain\Produccion\Insumo;
use App\Domain\Produccion\InsumoRepository;
use DomainException;

/**
 * Tras descontar insumos por producción, avisa de los que quedaron por debajo
 * de su mínimo. Recibe los insumos UNA vez por operación: una planificación
 * de tres lotes que consumen leche genera un solo aviso de leche, no tres.
 *
 * Notifica a ADMINISTRADOR y ENCARGADO_PLANTA: el primero supervisa el
 * negocio, pero quien realmente debe reponer el insumo es planta.
 */
final class NotificarStockBajoInsumos
{
    public function __construct(
        private readonly InsumoRepository $insumos,
        private readonly NotificacionRepository $notificaciones,
        private readonly UsuarioRepository $usuarios,
    ) {}

    /**
     * @param  list<int>  $insumoIds
     */
    public function execute(array $insumoIds): void
    {
        foreach (array_unique($insumoIds) as $insumoId) {
            $insumo = $this->insumos->buscarPorId($insumoId);

            if ($insumo !== null && $insumo->stockMinimo() > 0.0 && $insumo->estaBajoMinimo()) {
                $this->notificar($insumo);
            }
        }
    }

    private function notificar(Insumo $insumo): void
    {
        $destinatarios = [
            ...$this->usuarios->listarActivosPorRole('ADMINISTRADOR'),
            ...$this->usuarios->listarActivosPorRole('ENCARGADO_PLANTA'),
        ];

        foreach ($destinatarios as $usuario) {
            $this->notificaciones->guardar(new Notificacion(
                id: null,
                userId: $usuario->id() ?? throw new DomainException('El usuario destinatario no tiene identificador válido.'),
                tipo: 'INSUMO_STOCK_BAJO',
                titulo: 'Stock bajo de insumo',
                mensaje: sprintf(
                    "El insumo '%s' quedó en %s %s, por debajo del mínimo (%s).",
                    $insumo->nombre(),
                    RegistrarMovimientoInsumo::formato($insumo->stockActual()),
                    $insumo->unidadBase(),
                    RegistrarMovimientoInsumo::formato($insumo->stockMinimo()),
                ),
                referenciaTipo: 'INSUMO',
                referenciaId: $insumo->id(),
                leido: false,
            ));
        }
    }
}
