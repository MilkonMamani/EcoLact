<?php

namespace App\Application\Recorrido;

use App\Domain\Proveedor\ProveedorRepository;
use App\Domain\Recorrido\RecorridoDetalle;
use App\Domain\Recorrido\RecorridoDetalleRepository;
use DomainException;
use InvalidArgumentException;

/**
 * Identifica, dentro de un recorrido ya abierto, cuál de sus paradas
 * corresponde al proveedor indicado por DNI o por el token QR interno del
 * proveedor (nunca por lectura de un documento físico: ver
 * docs/rutas-recorridos-acopio.md, sección DNI y escaneo). Un DNI o QR que
 * no pertenezca a NINGÚN proveedor del recorrido actual se rechaza aquí con
 * un mensaje claro, sin registrar nada automáticamente.
 */
final class IdentificarParada
{
    private const METODOS_SOPORTADOS = ['QR', 'DNI'];

    public function __construct(
        private readonly ProveedorRepository $proveedores,
        private readonly RecorridoDetalleRepository $recorridoDetalles,
    ) {}

    public function execute(int $recorridoId, string $metodo, string $identificador): RecorridoDetalle
    {
        $proveedor = match ($metodo) {
            'QR' => $this->proveedores->buscarPorQrToken($identificador),
            'DNI' => $this->proveedores->buscarPorDni($identificador),
            default => throw new InvalidArgumentException(
                sprintf('Método de identificación no soportado: %s. Use %s.', $metodo, implode(' o ', self::METODOS_SOPORTADOS))
            ),
        };

        if ($proveedor === null) {
            throw new DomainException('No se encontró un proveedor con el identificador indicado.');
        }

        $parada = $this->recorridoDetalles->buscarPorRecorridoYProveedor($recorridoId, $proveedor->id());

        if ($parada === null) {
            throw new DomainException('Este proveedor no pertenece al recorrido actual.');
        }

        return $parada;
    }
}
