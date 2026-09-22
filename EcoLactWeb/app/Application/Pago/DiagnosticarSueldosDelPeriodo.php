<?php

namespace App\Application\Pago;

use App\Application\Pago\Contracts\PersonalPagableQuery;
use App\Domain\Auth\Repositories\RoleRepository;
use App\Domain\Pago\ConfiguracionPagoRepository;
use App\Domain\Shared\Enums\DiagnosticoSueldosPeriodo;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use DateTimeImmutable;

/**
 * Explica por qué "Pagar a trabajadores" no muestra ninguna fila para un
 * periodo: para no confundir "nadie cobra este mes" (normal, sin
 * configuración) con "hay algo raro" (config y personal, pero cero
 * obligaciones). Se llama solo cuando la sincronización ya corrió y aun así
 * no hay filas, así que es una lectura barata (roles remunerables suelen ser
 * pocos).
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
        $rolesConConfiguracion = [];

        foreach ($this->roles->listarRemunerables() as $rol) {
            if ($this->configuraciones->buscarVigente(TipoBeneficiarioPago::PERSONAL, $rol->id(), $fechaDelPeriodo) !== null) {
                $rolesConConfiguracion[] = $rol->id();
            }
        }

        if ($rolesConConfiguracion === []) {
            return DiagnosticoSueldosPeriodo::SIN_CONFIGURACION;
        }

        foreach ($rolesConConfiguracion as $rolId) {
            if ($this->personal->activosPorRol($rolId) !== []) {
                return DiagnosticoSueldosPeriodo::SIN_REGISTROS;
            }
        }

        return DiagnosticoSueldosPeriodo::SIN_PERSONAL_ACTIVO;
    }
}
