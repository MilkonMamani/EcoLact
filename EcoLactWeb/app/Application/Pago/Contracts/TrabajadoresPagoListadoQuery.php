<?php

namespace App\Application\Pago\Contracts;

use App\Application\Pago\ComprobantePagoTrabajador;
use App\Application\Pago\TrabajadoresPagoFiltro;
use App\Application\Pago\TrabajadoresPagoResumen;
use App\Application\Pago\TrabajadorPagoFila;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TrabajadoresPagoListadoQuery
{
    /**
     * @return LengthAwarePaginator<int, TrabajadorPagoFila>
     */
    public function obtener(TrabajadoresPagoFiltro $filtro): LengthAwarePaginator;

    /** Métricas del listado con los mismos filtros que obtener(). */
    public function resumen(TrabajadoresPagoFiltro $filtro): TrabajadoresPagoResumen;

    public function buscarFila(int $obligacionId): ?TrabajadorPagoFila;

    /** Fila "sin sueldo" de un trabajador (sin sueldo vigente propio en el mes) en el mes indicado (Y-m). */
    public function buscarSinSueldo(int $trabajadorId, string $periodo): ?TrabajadorPagoFila;

    public function comprobante(int $obligacionId): ?ComprobantePagoTrabajador;
}
