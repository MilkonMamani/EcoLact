<?php

namespace App\Application\Pago\Contracts;

use App\Application\Pago\PersonalPagable;

interface PersonalPagableQuery
{
    /**
     * Personas ACTIVAS (usuario y, si la tiene, su ficha de acopiador, analista,
     * encargado o personal de ventas) que tienen el rol indicado. Quien está
     * inactivo no genera pagos. Funciona con cualquier rol, también con los
     * que se creen en el futuro.
     *
     * @return PersonalPagable[]
     */
    public function activosPorRol(int $rolId): array;

    /**
     * IDs de usuario con el rol indicado que, para un rol con ficha dedicada
     * (acopiador, analista, encargado o personal de ventas), nunca tuvieron
     * un registro real en esa tabla. Un rol sin ficha dedicada (p.ej.
     * administrador) no tiene huérfanos: siempre devuelve un array vacío.
     *
     * @return list<int>
     */
    public function usuariosSinFichaReal(int $rolId): array;

    /**
     * La persona pagable ACTIVA del usuario indicado, con su rol real: con ficha
     * ACTIVA si su rol tiene ficha (acopiador, analista, encargado, personal de
     * ventas), o el usuario mismo para un rol remunerable sin ficha (p.ej. el
     * administrador). Null si su rol no es remunerable o no está activa.
     */
    public function trabajadorActivo(int $userId): ?PersonalPagable;
}
