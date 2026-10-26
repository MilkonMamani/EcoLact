<?php

namespace App\Application\Pago;

use App\Application\Pago\Contracts\PersonalPagableQuery;
use App\Domain\Auth\Repositories\RoleRepository;
use App\Domain\Pago\ConfiguracionPago;
use App\Domain\Pago\ConfiguracionPagoRepository;
use App\Domain\Shared\Enums\DiagnosticoSueldosPeriodo;
use DateTimeImmutable;

/**
 * Explica por qué "Pagar a trabajadores" no muestra ninguna fila para un
 * periodo: para no confundir "nadie cobra este mes" (normal, sin
 * configuración) con "hay algo raro" (sueldos y personal, pero cero
 * obligaciones). Se llama solo cuando la sincronización ya corrió y aun así
 * no hay filas, así que es una lectura barata (pocos trabajadores).
 *
 * El sueldo se configura por trabajador: se cruza cada sueldo vigente con el
 * personal activo.
 */
final class DiagnosticarSueldosDelPeriodo
{
    public function __construct(
        private readonly RoleRepository $roles,
        private readonly ConfiguracionPagoRepository $configuraciones,
        private readonly PersonalPagableQuery $personal,
    ) {}

    public function execute(DateTimeImmutable $fechaDelPeriodo): DiagnosticoSueldosPeriodo
    {
        $conSueldo = array_flip(array_map(
            fn (ConfiguracionPago $configuracion): int => (int) $configuracion->userId(),
            $this->configuraciones->listarVigentesDeTrabajadores($fechaDelPeriodo),
        ));

        if ($conSueldo === []) {
            return DiagnosticoSueldosPeriodo::SIN_CONFIGURACION;
        }

        foreach ($this->roles->listarRemunerables() as $rol) {
            foreach ($this->personal->activosPorRol($rol->id()) as $persona) {
                if (isset($conSueldo[$persona->userId])) {
                    return DiagnosticoSueldosPeriodo::SIN_REGISTROS;
                }
            }
        }

        return DiagnosticoSueldosPeriodo::SIN_PERSONAL_ACTIVO;
    }
}
