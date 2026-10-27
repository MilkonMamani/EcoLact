<?php

namespace App\Application\Calidad\Contracts;

use DateTimeImmutable;

/**
 * Lecturas del muestreo de calidad (móvil del analista). Una "entrega
 * elegible" es la que todavía puede entrar a control de calidad: no anulada,
 * sin análisis confirmado, sin muestra previa y de un proveedor activo.
 */
interface MuestreosCalidadQuery
{
    /**
     * Muestras SELECCIONADAS o EN_ANALISIS (las "Muestras pendientes"),
     * de la más antigua a la más reciente.
     *
     * @return list<array<string, mixed>>
     */
    public function pendientes(?string $busqueda = null, ?int $zonaId = null): array;

    /**
     * Estado de la muestra de cada entrega indicada (solo las que tienen muestra).
     *
     * @param  list<int>  $entregaIds
     * @return array<int, string> entrega_id => EstadoMuestreo
     */
    public function estadosPorEntrega(array $entregaIds): array;

    /** @return list<array{id: int, nombre: string}> */
    public function zonas(): array;

    /** @return array<string, mixed>|null */
    public function muestra(int $muestreoId): ?array;

    /** Muestra de una entrega en cualquier estado; null si nunca se muestreó (p. ej. análisis históricos). */
    public function muestraPorEntrega(int $entregaId): ?array;

    /** @return array{pendientes: int, analizadas_hoy: int} */
    public function kpis(DateTimeImmutable $hoy): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function elegibles(?string $busqueda, ?int $zonaId, DateTimeImmutable $desde, DateTimeImmutable $hasta): array;

    /**
     * Ids de las entregas elegibles en el rango, sin las de $excluir.
     *
     * @param  list<int>  $excluir
     * @return list<int>
     */
    public function idsElegibles(?int $zonaId, DateTimeImmutable $desde, DateTimeImmutable $hasta, array $excluir = []): array;

    /** Fila de una entrega con el mismo formato que elegibles() (exista o no su muestra). */
    public function entrega(int $entregaId): ?array;
}
