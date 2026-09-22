<?php

namespace App\Application\Proveedor;

use App\Domain\Proveedor\Proveedor;
use App\Domain\Proveedor\ProveedorRepository;
use DomainException;

/**
 * Expone el qr_token del proveedor autenticado. Recibe siempre el id del
 * usuario autenticado (nunca un proveedor_id manipulable desde el
 * navegador) y resuelve el proveedor propio a través del vínculo
 * user_id -> proveedores.user_id, igual que el resto de flujos de
 * autoservicio del proveedor.
 */
final class ObtenerMiQr
{
    public function __construct(
        private readonly ProveedorRepository $proveedores,
    ) {}

    public function execute(int $userId): Proveedor
    {
        return $this->proveedores->buscarPorUserId($userId)
            ?? throw new DomainException('No existe un proveedor asociado al usuario autenticado.');
    }
}
