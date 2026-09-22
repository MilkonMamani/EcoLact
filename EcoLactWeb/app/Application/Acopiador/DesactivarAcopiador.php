<?php

namespace App\Application\Acopiador;

use App\Application\Shared\TransactionManager;
use App\Domain\Acopiador\Acopiador;
use App\Domain\Acopiador\AcopiadorRepository;
use App\Domain\Acopiador\AcopiadorZonaHistorialRepository;
use App\Domain\Shared\Enums\EstadoUsuario;
use DateTimeImmutable;
use DomainException;

/**
 * Da de baja (INACTIVO) a un acopiador que está ACTIVO: cierra su historial
 * de zona vigente (colocando fecha_fin) y actualiza su estado, sin borrar
 * ningún registro histórico (usuario, acopiador, entregas, análisis e
 * historial de zona se conservan intactos). Un acopiador INACTIVO no puede
 * registrar nuevas entregas (ver RegistrarEntrega).
 */
final class DesactivarAcopiador
{
    public function __construct(
        private readonly AcopiadorRepository $acopiadores,
        private readonly AcopiadorZonaHistorialRepository $zonaHistorial,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(int $acopiadorId): Acopiador
    {
        $acopiador = $this->acopiadores->buscarPorId($acopiadorId);

        if ($acopiador === null) {
            throw new DomainException('El acopiador indicado no existe.');
        }

        if ($acopiador->estado() !== EstadoUsuario::ACTIVO) {
            throw new DomainException('El acopiador ya se encuentra inactivo.');
        }

        return $this->transacciones->run(function () use ($acopiador): Acopiador {
            $this->zonaHistorial->cerrarVigente($acopiador->id(), new DateTimeImmutable);

            return $this->acopiadores->actualizar(new Acopiador(
                id: $acopiador->id(),
                userId: $acopiador->userId(),
                zonaId: $acopiador->zonaId(),
                dni: $acopiador->dni(),
                nombres: $acopiador->nombres(),
                apellidos: $acopiador->apellidos(),
                telefono: $acopiador->telefono(),
                estado: EstadoUsuario::INACTIVO,
                fechaIngreso: $acopiador->fechaIngreso(),
            ));
        });
    }
}
