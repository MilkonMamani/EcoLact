<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\CompraInsumoRepository;
use App\Domain\Produccion\Insumo;
use App\Domain\Produccion\InsumoMovimiento;
use App\Domain\Produccion\InsumoMovimientoRepository;
use App\Domain\Produccion\InsumoRepository;

/**
 * Datos de la pantalla "Insumos": catálogo con su stock actual, indicadores,
 * la fecha de la última compra y del último movimiento de cada insumo, y los últimos movimientos de
 * cada uno (para verlos desplegados en la misma fila, sin cambiar de pantalla).
 *
 * @phpstan-type Kpis array{insumos: int, conStock: int, stockBajo: int, sinStock: int}
 */
final class ObtenerInventarioInsumos
{
    private const MOVIMIENTOS_POR_INSUMO = 6;

    public function __construct(
        private readonly InsumoRepository $insumos,
        private readonly InsumoMovimientoRepository $movimientos,
        private readonly CompraInsumoRepository $compras,
    ) {}

    /**
     * @return array{insumos: list<Insumo>, kpis: Kpis, movimientos: array<int, list<InsumoMovimiento>>, ultimasCompras: array<int, \DateTimeImmutable>, ultimosMovimientos: array<int, \DateTimeImmutable>}
     */
    public function execute(?string $busqueda = null, ?string $nivel = null, ?string $tipo = null, bool $conMovimientos = true): array
    {
        $todos = $this->insumos->listar();

        $kpis = [
            'insumos' => count($todos),
            'conStock' => count(array_filter($todos, fn (Insumo $i): bool => $i->stockActual() > 0.0)),
            'stockBajo' => count(array_filter($todos, fn (Insumo $i): bool => $i->stockMinimo() > 0.0 && $i->estaBajoMinimo() && $i->stockActual() > 0.0)),
            'sinStock' => count(array_filter($todos, fn (Insumo $i): bool => $i->stockActual() <= 0.0)),
        ];

        $filtrados = array_values(array_filter($todos, function (Insumo $insumo) use ($busqueda, $nivel, $tipo): bool {
            if ($busqueda !== null && $busqueda !== '' && ! str_contains(mb_strtolower($insumo->nombre()), mb_strtolower($busqueda))) {
                return false;
            }

            if ($tipo !== null && $tipo !== '' && $insumo->tipo()->value !== $tipo) {
                return false;
            }

            $sinStock = $insumo->stockActual() <= 0.0;
            $bajo = $insumo->stockMinimo() > 0.0 && $insumo->estaBajoMinimo() && ! $sinStock;

            return match ($nivel) {
                'sin_stock' => $sinStock,
                'bajo' => $bajo,
                'normal' => ! $sinStock && ! $bajo,
                default => true,
            };
        }));

        $movimientos = [];

        // La vista con panel lateral (admin) carga los movimientos solo del insumo elegido.
        foreach ($conMovimientos ? $filtrados : [] as $insumo) {
            $movimientos[$insumo->id()] = array_slice($this->movimientos->listarPorInsumo($insumo->id()), 0, self::MOVIMIENTOS_POR_INSUMO);
        }

        return [
            'insumos' => $filtrados,
            'kpis' => $kpis,
            'movimientos' => $movimientos,
            'ultimasCompras' => $this->compras->ultimaCompraPorInsumo(),
            'ultimosMovimientos' => $this->movimientos->ultimoMovimientoPorInsumo(),
        ];
    }
}
