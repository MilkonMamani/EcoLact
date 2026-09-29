<?php

namespace App\Application\Trabajador;

/**
 * Fichas de personal sin zona que comparten la pantalla "listado + panel" de
 * Acopiadores (admin). El valor es la tabla de la ficha; cada tipo se liga a
 * su actividad real por el usuario que la hizo:
 *
 * - ANALISTA_CALIDAD: analisis_calidad.confirmado_por_user_id
 * - ENCARGADO_PLANTA: lotes_produccion.registrado_por_user_id
 * - PERSONAL_VENTAS: ventas_producto.registrado_por_user_id
 */
enum TipoFichaPersonal: string
{
    case ANALISTA_CALIDAD = 'analistas_calidad';
    case ENCARGADO_PLANTA = 'encargados_planta';
    case PERSONAL_VENTAS = 'personal_ventas';

    public function tabla(): string
    {
        return $this->value;
    }
}
