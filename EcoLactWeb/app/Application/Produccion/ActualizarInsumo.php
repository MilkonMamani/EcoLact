<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\Insumo;
use App\Domain\Produccion\InsumoRepository;
use App\Domain\Shared\Enums\TipoInsumo;
use DomainException;

/**
 * Edita los datos NO críticos de un insumo (nombre, descripción, stock
 * mínimo y estado). Nunca toca el stock (eso solo lo cambian los
 * movimientos) ni la unidad base (cambiarla reinterpretaría todo el
 * historial).
 *
 * Protección del insumo del sistema (LECHE): no se puede desactivar ni
 * cambiar de tipo, porque las entregas de proveedores necesitan poder
 * ingresar leche y las recetas la usan.
 */
final class ActualizarInsumo
{
    public function __construct(
        private readonly InsumoRepository $insumos,
    ) {}

    public function execute(
        int $insumoId,
        string $nombre,
        ?string $descripcion,
        float $stockMinimo,
        string $estado,
        ?TipoInsumo $tipo = null,
    ): Insumo {
        $insumo = $this->insumos->buscarPorId($insumoId)
            ?? throw new DomainException('El insumo indicado no existe.');

        $nombre = trim($nombre);

        $otro = $this->insumos->buscarPorNombre($nombre);

        if ($otro !== null && $otro->id() !== $insumo->id()) {
            throw new DomainException("Ya existe un insumo registrado con el nombre {$nombre}.");
        }

        if ($insumo->esDelSistema()) {
            if ($estado !== 'ACTIVO') {
                throw new DomainException("{$insumo->nombre()} es un insumo del sistema y no se puede desactivar.");
            }

            if ($tipo !== null && $tipo !== $insumo->tipo()) {
                throw new DomainException("{$insumo->nombre()} es un insumo del sistema y no se puede cambiar de tipo.");
            }
        }

        if (! in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
            throw new DomainException('El estado del insumo debe ser ACTIVO o INACTIVO.');
        }

        return $this->insumos->actualizar(new Insumo(
            id: $insumo->id(),
            nombre: $nombre,
            unidadBase: $insumo->unidadBase(),
            stockActual: $insumo->stockActual(),
            stockMinimo: $stockMinimo,
            estado: $estado,
            tipo: $tipo ?? $insumo->tipo(),
            codigo: $insumo->codigo(),
            descripcion: $descripcion,
        ));
    }
}
