<?php

namespace App\Application\Zona;

use App\Application\Ruta\AsignarProveedorARuta;
use App\Application\Shared\TransactionManager;
use App\Domain\Auditoria\AuditoriaRepository;
use App\Domain\Proveedor\ProveedorRepository;
use App\Domain\Ruta\Ruta;
use App\Domain\Ruta\RutaDetalle;
use App\Domain\Ruta\RutaDetalleRepository;
use App\Domain\Ruta\RutaRepository;
use App\Domain\Zona\Zona;
use App\Domain\Zona\ZonaRepository;
use DomainException;

/**
 * "Mover ubicación" del mapa general de Zonas: el administrador arrastra el pin de un
 * proveedor y esta operación decide, con las delimitaciones guardadas (point-in-polygon en
 * el servidor, sin confiar en lo que detectó el navegador), qué significa ese movimiento:
 *
 * - Sigue dentro de su zona: solo cambia el punto de recolección (ruta_detalles.latitud y
 *   longitud). No se toca la zona ni la ruta ni el orden de visita.
 * - Cae en OTRA zona: el proveedor pasa a una ruta activa de esa zona con su nuevo punto,
 *   reutilizando AsignarProveedorARuta (cierra/abre proveedor_ruta_historial y
 *   proveedor_zona_historial, compacta órdenes y actualiza proveedores.zona_id). La zona se
 *   deriva de la ruta, por eso el cambio de zona es un cambio de ruta: si la zona destino
 *   tiene varias rutas activas, quien llama debe indicar cuál.
 * - Fuera de toda delimitación, o dentro de una zona inactiva: se rechaza sin guardar.
 *
 * Todo ocurre en una transacción junto con la auditoría: si algo falla no queda nada a medias.
 * Las entregas, pagos y liquidaciones ya registrados guardan su propia zona_id y no se tocan:
 * el cambio rige desde este momento. Los contadores de zona/acopiador no se guardan en ningún
 * lado: se calculan contando las asignaciones vigentes, así que se ajustan solos.
 */
final class MoverProveedorDeZona
{
    public const ACCION_CAMBIO_ZONA = 'PROVEEDOR_CAMBIO_ZONA';

    public const ACCION_UBICACION = 'PROVEEDOR_UBICACION';

    public function __construct(
        private readonly ProveedorRepository $proveedores,
        private readonly ZonaRepository $zonas,
        private readonly RutaRepository $rutas,
        private readonly RutaDetalleRepository $rutaDetalles,
        private readonly AsignarProveedorARuta $asignarProveedorARuta,
        private readonly LocalizadorZonas $localizador,
        private readonly AuditoriaRepository $auditorias,
        private readonly TransactionManager $transacciones,
    ) {}

    /**
     * @param  ?int  $rutaDestinoId  ruta de la zona destino; obligatoria solo si esa zona tiene varias rutas activas
     * @param  ?int  $zonaEsperadaId  zona que el navegador detectó y el administrador confirmó; si el servidor detecta otra, se rechaza
     */
    public function execute(
        int $proveedorId,
        float $latitud,
        float $longitud,
        ?int $rutaDestinoId = null,
        ?int $zonaEsperadaId = null,
        ?int $userId = null,
        ?string $ip = null,
        ?string $userAgent = null,
    ): MovimientoProveedorResultado {
        if ($latitud < -90 || $latitud > 90 || $longitud < -180 || $longitud > 180) {
            throw new DomainException('Las coordenadas indicadas no son válidas.');
        }

        $proveedor = $this->proveedores->buscarPorId($proveedorId);

        if ($proveedor === null) {
            throw new DomainException('El proveedor indicado ya no existe.');
        }

        $asignacion = $this->rutaDetalles->buscarPorProveedor($proveedorId);
        $rutaActual = $asignacion !== null ? $this->rutas->buscarPorId($asignacion->rutaId()) : null;

        if ($asignacion === null || $rutaActual === null) {
            throw new DomainException('El proveedor no tiene una zona y ruta asignadas: asígnalo primero desde "Asignar proveedor".');
        }

        $zonaActual = $this->zonas->buscarPorId($rutaActual->zonaId());
        $zonaDestino = $this->localizador->zonaEn($latitud, $longitud, preferidaId: $rutaActual->zonaId());

        if ($zonaDestino === null) {
            throw new DomainException('La ubicación seleccionada no pertenece a ninguna delimitación operativa.');
        }

        if ($zonaEsperadaId !== null && $zonaEsperadaId !== $zonaDestino->id()) {
            throw new DomainException("La ubicación corresponde a {$zonaDestino->nombre()}, no a la zona confirmada. Revisa el movimiento e inténtalo de nuevo.");
        }

        if (! $zonaDestino->estaActiva()) {
            throw new DomainException("La zona {$zonaDestino->nombre()} está inactiva: no se pueden mover proveedores a ella.");
        }

        $cambiaZona = $zonaDestino->id() !== $rutaActual->zonaId();
        $rutaDestino = $cambiaZona ? $this->rutaDestino($zonaDestino, $rutaDestinoId) : $rutaActual;
        $proveedorNombre = $proveedor->nombreCompleto();

        return $this->transacciones->run(function () use (
            $proveedorId, $proveedorNombre, $latitud, $longitud, $asignacion, $rutaActual, $zonaActual, $zonaDestino, $rutaDestino, $cambiaZona, $userId, $ip, $userAgent,
        ): MovimientoProveedorResultado {
            $zonaAnteriorNombre = $zonaActual?->nombre() ?? 'Zona #'.$rutaActual->zonaId();

            if ($cambiaZona) {
                $this->asignarProveedorARuta->execute(
                    rutaId: $rutaDestino->id(),
                    proveedorId: $proveedorId,
                    motivo: "Ubicación movida en el mapa de zonas: {$zonaAnteriorNombre} → {$zonaDestino->nombre()}.",
                    latitud: $latitud,
                    longitud: $longitud,
                    referenciaUbicacion: $asignacion->referenciaUbicacion(),
                );
            } else {
                $this->rutaDetalles->actualizarUbicacion(
                    $asignacion->conUbicacion($latitud, $longitud, $asignacion->referenciaUbicacion()),
                );
            }

            $this->auditorias->registrar(
                userId: $userId,
                accion: $cambiaZona ? self::ACCION_CAMBIO_ZONA : self::ACCION_UBICACION,
                tablaAfectada: 'proveedores',
                registroId: $proveedorId,
                datosAnteriores: $this->estado($rutaActual, $zonaAnteriorNombre, $asignacion),
                datosNuevos: [
                    'zona_id' => $zonaDestino->id(),
                    'zona' => $zonaDestino->nombre(),
                    'ruta_id' => $rutaDestino->id(),
                    'ruta' => $rutaDestino->nombre(),
                    'latitud' => $latitud,
                    'longitud' => $longitud,
                ],
                motivo: $cambiaZona
                    ? "Cambio de zona de {$proveedorNombre}: {$zonaAnteriorNombre} → {$zonaDestino->nombre()}."
                    : "Nueva ubicación de {$proveedorNombre} dentro de {$zonaDestino->nombre()}.",
                ip: $ip,
                userAgent: $userAgent,
            );

            return new MovimientoProveedorResultado(
                proveedorId: $proveedorId,
                proveedorNombre: $proveedorNombre,
                zonaAnteriorId: $rutaActual->zonaId(),
                zonaAnteriorNombre: $zonaAnteriorNombre,
                zonaNuevaId: (int) $zonaDestino->id(),
                zonaNuevaNombre: $zonaDestino->nombre(),
                rutaId: (int) $rutaDestino->id(),
                latitudAnterior: $asignacion->latitud(),
                longitudAnterior: $asignacion->longitud(),
                latitud: $latitud,
                longitud: $longitud,
            );
        });
    }

    private function rutaDestino(Zona $zona, ?int $rutaDestinoId): Ruta
    {
        $activas = array_values($this->rutas->listarActivasPorZona((int) $zona->id()));

        if ($rutaDestinoId !== null) {
            foreach ($activas as $ruta) {
                if ($ruta->id() === $rutaDestinoId) {
                    return $ruta;
                }
            }

            throw new DomainException("La ruta elegida no es una ruta activa de {$zona->nombre()}.");
        }

        return match (count($activas)) {
            0 => throw new DomainException("{$zona->nombre()} no tiene rutas activas: crea una ruta en esa zona antes de mover al proveedor."),
            1 => $activas[0],
            default => throw new DomainException("{$zona->nombre()} tiene varias rutas activas: elige a cuál pasará el proveedor."),
        };
    }

    /** @return array{zona_id: int, zona: string, ruta_id: int, ruta: string, latitud: ?float, longitud: ?float} */
    private function estado(Ruta $ruta, string $zonaNombre, RutaDetalle $asignacion): array
    {
        return [
            'zona_id' => $ruta->zonaId(),
            'zona' => $zonaNombre,
            'ruta_id' => (int) $ruta->id(),
            'ruta' => $ruta->nombre(),
            'latitud' => $asignacion->latitud(),
            'longitud' => $asignacion->longitud(),
        ];
    }
}
