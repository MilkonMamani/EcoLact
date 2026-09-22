<?php

namespace App\Application\Recorrido;

use App\Application\Shared\TransactionManager;
use App\Domain\Acopiador\AcopiadorRepository;
use App\Domain\Recorrido\RecorridoAcopio;
use App\Domain\Recorrido\RecorridoAcopioRepository;
use App\Domain\Recorrido\RecorridoDetalle;
use App\Domain\Recorrido\RecorridoDetalleRepository;
use App\Domain\Ruta\RutaDetalleRepository;
use App\Domain\Ruta\RutaRepository;
use App\Domain\Shared\Enums\EstadoParadaRecorrido;
use App\Domain\Shared\Enums\EstadoRecorrido;
use App\Domain\Shared\Enums\EstadoUsuario;
use DateTimeImmutable;
use DomainException;

/**
 * Crea (o, si ya existe uno abierto para esa ruta y fecha, simplemente
 * devuelve) el recorrido operativo del día: acción exclusiva de
 * ADMINISTRADOR desde el módulo Zonas ("asignar acopiador y abrir
 * recorrido"). El acopiador nunca crea sus propios recorridos, solo abre
 * (consulta y opera) los que ya le fueron asignados aquí.
 *
 * Al crear el recorrido, congela una parada (RecorridoDetalle) por cada
 * proveedor vigente de la ruta en ese momento, copiando su orden y su
 * ubicación habitual de entrega. Esa copia es la que hace que un recorrido
 * ya creado no cambie si después se reordena la ruta o se traslada un
 * proveedor.
 */
final class AbrirRecorrido
{
    public function __construct(
        private readonly RutaRepository $rutas,
        private readonly RutaDetalleRepository $rutaDetalles,
        private readonly AcopiadorRepository $acopiadores,
        private readonly RecorridoAcopioRepository $recorridos,
        private readonly RecorridoDetalleRepository $recorridoDetalles,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(
        int $rutaId,
        int $acopiadorId,
        ?DateTimeImmutable $fechaOperativa = null,
    ): RecorridoAcopio {
        $ruta = $this->rutas->buscarPorId($rutaId);

        if ($ruta === null) {
            throw new DomainException('La ruta indicada no existe.');
        }

        if (! $ruta->estaActiva()) {
            throw new DomainException('No se puede abrir un recorrido para una ruta inactiva.');
        }

        $acopiador = $this->acopiadores->buscarPorId($acopiadorId);

        if ($acopiador === null) {
            throw new DomainException('El acopiador indicado no existe.');
        }

        if ($acopiador->estado() !== EstadoUsuario::ACTIVO) {
            throw new DomainException('No se puede asignar un recorrido a un acopiador inactivo.');
        }

        $fecha = $fechaOperativa ?? new DateTimeImmutable(now(config('app.timezone'))->toDateString());

        $abierto = $this->recorridos->buscarAbiertoPorRutaYFecha($rutaId, $fecha);

        if ($abierto !== null) {
            return $abierto;
        }

        return $this->transacciones->run(function () use ($ruta, $acopiador, $fecha): RecorridoAcopio {
            $recorrido = $this->recorridos->guardar(new RecorridoAcopio(
                id: null,
                rutaId: $ruta->id(),
                acopiadorId: $acopiador->id(),
                fechaOperativa: $fecha,
                estado: EstadoRecorrido::PENDIENTE,
            ));

            $recorridoId = $recorrido->id() ?? throw new DomainException('No se pudo crear el recorrido.');

            $paradas = $this->rutaDetalles->listarPorRuta($ruta->id());
            foreach ($paradas as $parada) {
                $this->recorridoDetalles->guardar(new RecorridoDetalle(
                    id: null,
                    recorridoId: $recorridoId,
                    proveedorId: $parada->proveedorId(),
                    rutaDetalleId: $parada->id(),
                    ordenVisita: $parada->ordenVisita(),
                    latitud: $parada->latitud(),
                    longitud: $parada->longitud(),
                    referenciaUbicacion: $parada->referenciaUbicacion(),
                    estado: EstadoParadaRecorrido::PENDIENTE,
                ));
            }

            return $recorrido;
        });
    }
}
