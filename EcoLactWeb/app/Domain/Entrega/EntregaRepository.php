<?php

namespace App\Domain\Entrega;

use DateTimeImmutable;

interface EntregaRepository
{
    public function guardar(Entrega $entrega): Entrega;

    public function actualizar(Entrega $entrega): Entrega;

    public function buscarPorId(int $id): ?Entrega;

    public function buscarPorTokenIdempotencia(string $token): ?Entrega;

    /**
     * @return Entrega[]
     */
    public function listarPorProveedor(int $proveedorId): array;

    /**
     * @return Entrega[]
     */
    public function listarPorZona(int $zonaId): array;

    /**
     * @return Entrega[]
     */
    public function listar(): array;

    /**
     * @return Entrega[]
     */
    public function listarPorAcopiador(int $acopiadorId): array;

    /**
     * Entregas no anuladas registradas desde la fecha indicada (inclusive).
     *
     * @return Entrega[]
     */
    public function listarNoAnuladasDesde(DateTimeImmutable $desde): array;
}
