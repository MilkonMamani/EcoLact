<?php

namespace App\Application\PersonalVentas;

use App\Application\Trabajador\CambiarEstadoCuentaTrabajador;
use App\Application\Trabajador\SincronizarTrabajadores;
use App\Domain\PersonalVentas\PersonalVentas;
use App\Domain\PersonalVentas\PersonalVentasRepository;
use App\Domain\Shared\Enums\EstadoUsuario;
use DomainException;

final class DesactivarPersonalVentas
{
    public function __construct(
        private readonly PersonalVentasRepository $personal,
        private readonly SincronizarTrabajadores $sincronizarTrabajadores,
        private readonly CambiarEstadoCuentaTrabajador $cambiarEstadoCuenta,
    ) {}

    public function execute(int $personalId): PersonalVentas
    {
        $personal = $this->personal->buscarPorId($personalId);

        if ($personal === null) {
            throw new DomainException('El personal de ventas indicado no existe.');
        }

        if ($personal->estado() !== EstadoUsuario::ACTIVO) {
            throw new DomainException('El personal de ventas ya se encuentra inactivo.');
        }

        $ficha = $this->personal->actualizar(new PersonalVentas(
            id: $personal->id(),
            userId: $personal->userId(),
            dni: $personal->dni(),
            nombres: $personal->nombres(),
            apellidos: $personal->apellidos(),
            telefono: $personal->telefono(),
            estado: EstadoUsuario::INACTIVO,
            fechaIngreso: $personal->fechaIngreso(),
        ));

        $this->cambiarEstadoCuenta->execute($ficha->userId(), EstadoUsuario::INACTIVO);

        $this->sincronizarTrabajadores->execute(soloUserId: $ficha->userId());

        return $ficha;
    }
}
