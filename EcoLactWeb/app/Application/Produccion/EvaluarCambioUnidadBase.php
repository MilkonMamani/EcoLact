<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\Insumo;
use App\Domain\Produccion\InsumoRepository;

/**
 * Dice si la UNIDAD BASE de un insumo todavía se puede corregir y, si no,
 * por qué. Solo se puede cuando nada guardado está expresado en ella: sin
 * stock, sin movimientos, sin presentaciones, sin compras, sin recetas y sin
 * consumos de producción. Así corregir "Sal en ml" a "Sal en kg" nunca
 * reinterpreta cantidades (no hay conversión segura entre masa y volumen).
 *
 * Lo usan ActualizarInsumo (lo exige) y ObtenerPanelInsumo (lo explica).
 */
final class EvaluarCambioUnidadBase
{
    private const MOTIVOS = [
        'movimientos' => 'movimientos de stock',
        'presentaciones' => 'presentaciones de compra',
        'compras' => 'compras registradas',
        'recetas' => 'recetas que lo usan',
        'consumos' => 'consumos de producción',
    ];

    public function __construct(
        private readonly InsumoRepository $insumos,
    ) {}

    /**
     * @return list<string> motivos legibles; vacío si la unidad se puede cambiar.
     */
    public function motivos(Insumo $insumo): array
    {
        if ($insumo->esDelSistema()) {
            return ['es un insumo del sistema'];
        }

        $motivos = $insumo->stockActual() > 0.0 ? ['tiene stock'] : [];

        foreach ($this->insumos->contarDependenciasDeUnidad((int) $insumo->id()) as $clave => $cantidad) {
            if ($cantidad > 0) {
                $motivos[] = self::MOTIVOS[$clave];
            }
        }

        return $motivos;
    }
}
