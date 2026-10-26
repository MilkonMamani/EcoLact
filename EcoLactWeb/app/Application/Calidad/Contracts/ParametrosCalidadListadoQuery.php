<?php

namespace App\Application\Calidad\Contracts;

/**
 * Lectura para la pantalla "Parámetros de calidad" (tabla + panel lateral).
 * Solo lee: registrar/actualizar sigue en los Use Cases de Calidad.
 */
interface ParametrosCalidadListadoQuery
{
    /**
     * Todos los parámetros con su regla actual (la vigente hoy, la misma que usa el análisis);
     * reglaActual = null si ninguna regla está vigente. tieneHistorial indica si existieron reglas
     * (vencidas/inactivas) que se conservan como historial. Sin N+1 (eager loading).
     *
     * @return list<array{id: int, codigo: string, nombre: string, descripcion: string|null, unidad: string|null, estado: string, actualizado: string|null, tieneHistorial: bool, reglaActual: array{id: int, valorMinimo: string|null, valorMaximo: string|null, rango: string, resultadoIncumplimiento: string, fechaInicio: string, fechaFin: string|null, estado: string}|null}>
     */
    public function listar(): array;

    /**
     * Un parámetro con la misma forma que listar(); null si no existe.
     *
     * @return array{id: int, codigo: string, nombre: string, descripcion: string|null, unidad: string|null, estado: string, actualizado: string|null, tieneHistorial: bool, reglaActual: array{id: int, valorMinimo: string|null, valorMaximo: string|null, rango: string, resultadoIncumplimiento: string, fechaInicio: string, fechaFin: string|null, estado: string}|null}|null
     */
    public function panel(int $parametroId): ?array;
}
