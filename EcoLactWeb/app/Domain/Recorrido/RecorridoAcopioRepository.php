<?php

namespace App\Domain\Recorrido;

use DateTimeImmutable;

interface RecorridoAcopioRepository
{
    public function guardar(RecorridoAcopio $recorrido): RecorridoAcopio;

    public function actualizar(RecorridoAcopio $recorrido): RecorridoAcopio;

    public function buscarPorId(int $id): ?RecorridoAcopio;

    /**
     * Busca un recorrido de esa ruta, en esa fecha operativa, que siga
     * abierto (PENDIENTE o EN_PROGRESO). Se usa para no crear un segundo
     * recorrido duplicado el mismo día para la misma ruta.
     */
    public function buscarAbiertoPorRutaYFecha(int $rutaId, DateTimeImmutable $fechaOperativa): ?RecorridoAcopio;

    /**
     * @return RecorridoAcopio[] ordenados por fecha_operativa descendente
     */
    public function listarPorAcopiador(int $acopiadorId): array;

    /**
     * @return RecorridoAcopio[] ordenados por fecha_operativa descendente
     */
    public function listarPorRuta(int $rutaId): array;
}
