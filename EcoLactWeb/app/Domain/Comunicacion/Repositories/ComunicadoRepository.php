<?php

namespace App\Domain\Comunicacion\Repositories;

use App\Domain\Comunicacion\Comunicado;
use DateTimeImmutable;

interface ComunicadoRepository
{
    public function guardar(Comunicado $comunicado): Comunicado;

    public function actualizar(Comunicado $comunicado): Comunicado;

    /**
     * @param  int[]  $userIds
     */
    public function agregarDestinatarios(int $comunicadoId, array $userIds): void;

    public function buscarPorId(int $id): ?Comunicado;

    /**
     * @return Comunicado[]
     */
    public function listarParaUsuario(int $userId): array;

    /**
     * @return Comunicado[]
     */
    public function listar(): array;

    /**
     * @return list<array{userId: int, leido: bool, fechaLectura: ?DateTimeImmutable}>
     */
    public function listarDestinatarios(int $comunicadoId): array;

    /**
     * @return array{leido: bool, fechaLectura: ?DateTimeImmutable}|null Null si el usuario no es destinatario.
     */
    public function buscarLecturaDestinatario(int $comunicadoId, int $userId): ?array;

    public function marcarComoLeido(int $comunicadoId, int $userId): void;

    /**
     * @return array<int, bool> comunicado_id => leído
     */
    public function listarLecturaParaUsuario(int $userId): array;
}
