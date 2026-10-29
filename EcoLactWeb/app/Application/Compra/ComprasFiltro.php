<?php

namespace App\Application\Compra;

use App\Domain\Shared\Enums\EstadoPagoCompra;
use Carbon\CarbonImmutable;

/**
 * Filtros del historial de Compras y gastos. Sin fechas muestra todo el
 * historial; los indicadores superiores se calculan con estos mismos filtros.
 *
 * `destino`: INVENTARIO (compras con algo que entra al stock) o GASTO (sin
 * ninguna línea de inventario).
 */
final readonly class ComprasFiltro
{
    public const DESTINO_INVENTARIO = 'INVENTARIO';

    public const DESTINO_GASTO = 'GASTO';

    public function __construct(
        public ?CarbonImmutable $desde,
        public ?CarbonImmutable $hasta,
        public ?string $busqueda,
        public ?int $categoriaId,
        public ?EstadoPagoCompra $estadoPago,
        public ?string $destino,
        public int $porPagina = 15,
    ) {}

    public static function resolver(?string $desde, ?string $hasta, ?string $busqueda, ?string $categoria, ?string $estadoPago, ?string $destino): self
    {
        $fecha = static function (?string $valor): ?CarbonImmutable {
            if ($valor === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
                return null;
            }

            return CarbonImmutable::createFromFormat('!Y-m-d', $valor) ?: null;
        };

        $inicio = $fecha($desde);
        $fin = $fecha($hasta);

        if ($inicio !== null && $fin !== null && $inicio > $fin) {
            [$inicio, $fin] = [$fin, $inicio];
        }

        $busqueda = $busqueda !== null ? mb_substr(trim($busqueda), 0, 100) : '';

        return new self(
            desde: $inicio,
            hasta: $fin,
            busqueda: $busqueda !== '' ? $busqueda : null,
            categoriaId: ctype_digit((string) $categoria) ? (int) $categoria : null,
            estadoPago: EstadoPagoCompra::tryFrom((string) $estadoPago),
            destino: in_array($destino, [self::DESTINO_INVENTARIO, self::DESTINO_GASTO], true) ? $destino : null,
        );
    }

    public function activo(): bool
    {
        return $this->desde !== null || $this->hasta !== null || $this->busqueda !== null || $this->categoriaId !== null || $this->estadoPago !== null || $this->destino !== null;
    }
}
