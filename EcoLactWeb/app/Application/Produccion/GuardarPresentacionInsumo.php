<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\InsumoRepository;
use App\Domain\Produccion\PresentacionInsumo;
use App\Domain\Produccion\PresentacionInsumoRepository;
use DomainException;

/**
 * Crea o edita una presentación de compra de un insumo ("Saco" de 25 kg de
 * Sal). La equivalencia en unidad base la calcula PresentacionInsumo con
 * UnidadMedida. Editar una presentación no cambia compras pasadas: cada
 * detalle de compra guarda su propia fotografía de la presentación.
 */
final class GuardarPresentacionInsumo
{
    public function __construct(
        private readonly InsumoRepository $insumos,
        private readonly PresentacionInsumoRepository $presentaciones,
    ) {}

    public function execute(
        int $insumoId,
        string $nombre,
        float $cantidad,
        string $unidad,
        ?int $presentacionId = null,
        string $estado = PresentacionInsumo::ACTIVA,
    ): PresentacionInsumo {
        $insumo = $this->insumos->buscarPorId($insumoId)
            ?? throw new DomainException('El insumo indicado no existe.');

        if ($presentacionId !== null) {
            $actual = $this->presentaciones->buscarPorId($presentacionId);

            if ($actual === null || $actual->insumoId() !== $insumoId) {
                throw new DomainException('La presentación indicada no pertenece a este insumo.');
            }
        }

        $presentacion = PresentacionInsumo::para($insumo, $nombre, $cantidad, $unidad, $presentacionId, $estado);

        foreach ($this->presentaciones->listarPorInsumo($insumoId) as $otra) {
            if ($otra->id() !== $presentacionId && $otra->estaActiva() && $presentacion->estaActiva()
                && TextoComparable::clave($otra->nombre()) === TextoComparable::clave($presentacion->nombre())
                && abs($otra->cantidadBase() - $presentacion->cantidadBase()) < 0.0001) {
                throw new DomainException("{$insumo->nombre()} ya tiene la presentación {$otra->etiqueta()}.");
            }
        }

        return $presentacionId === null
            ? $this->presentaciones->guardar($presentacion)
            : $this->presentaciones->actualizar($presentacion);
    }
}
