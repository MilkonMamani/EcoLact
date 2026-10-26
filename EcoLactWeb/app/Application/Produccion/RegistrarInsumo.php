<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\Insumo;
use App\Domain\Produccion\InsumoRepository;
use App\Domain\Produccion\UnidadMedida;
use App\Domain\Shared\Enums\TipoInsumo;
use DomainException;

/**
 * Crea la FICHA de un insumo (cuajo, sal, azúcar, envases...): su unidad base
 * y el umbral mínimo que dispara la alerta de reposición. Crear un insumo NO
 * significa que haya entrado mercancía: el stock nace siempre en 0 y solo sube
 * con una compra confirmada (RegistrarCompraInsumos) o, para corregir el
 * conteo físico, con un ajuste (RegistrarAjusteInsumo).
 *
 * Los insumos del sistema (LECHE) NO se crean por aquí: los crea la
 * migración y se identifican por su código.
 */
final class RegistrarInsumo
{
    public function __construct(
        private readonly InsumoRepository $insumos,
    ) {}

    public function execute(
        string $nombre,
        string $unidadBase,
        float $stockMinimo,
        TipoInsumo $tipo = TipoInsumo::INSUMO,
        ?string $descripcion = null,
        string $estado = 'ACTIVO',
    ): Insumo {
        $nombre = trim($nombre);

        if (mb_strtolower($nombre) === 'leche') {
            throw new DomainException('La leche ya existe como insumo del sistema y no se puede crear otra.');
        }

        if ($this->insumos->buscarPorNombre($nombre) !== null) {
            throw new DomainException("Ya existe un insumo registrado con el nombre {$nombre}.");
        }

        if (! UnidadMedida::esValida($unidadBase)) {
            throw new DomainException('La unidad base no es válida. Use: '.implode(', ', UnidadMedida::codigos()).'.');
        }

        if (! in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
            throw new DomainException('El estado del insumo debe ser Activo o Inactivo.');
        }

        return $this->insumos->guardar(new Insumo(
            id: null,
            nombre: $nombre,
            unidadBase: $unidadBase,
            stockActual: 0.0,
            stockMinimo: $stockMinimo,
            estado: $estado,
            tipo: $tipo,
            codigo: null,
            descripcion: $descripcion,
        ));
    }
}
