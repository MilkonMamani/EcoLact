<?php

namespace App\Application\Ruta;

use App\Application\Zona\LocalizadorZonas;
use App\Application\Zona\UbicacionFueraDeZona;
use App\Domain\Ruta\RutaDetalle;
use App\Domain\Ruta\RutaDetalleRepository;
use App\Domain\Ruta\RutaRepository;
use DomainException;

/**
 * Punto de entrada del modal "Asignar proveedor a zona y ruta" (módulo
 * Zonas). Cubre los dos casos con una sola operación:
 *
 * - El proveedor NO estaba en la ruta indicada (sin asignación, o en otra
 *   ruta/zona): lo agrega o lo traslada con su punto de recolección,
 *   delegando en AsignarProveedorARuta (historial, caché de zona y
 *   compactación de órdenes incluidos).
 * - El proveedor YA estaba en esa misma ruta ("Editar asignación" sin
 *   cambiar de ruta): solo actualiza su ubicación/referencia, sin tocar su
 *   orden de visita ni el historial.
 *
 * Así la restricción "un proveedor no se duplica en la misma ruta" sigue
 * viva en AsignarProveedorARuta (que sigue rechazando el duplicado) y el
 * flujo de edición no necesita una segunda vía de escritura.
 *
 * Con coordenadas, el servidor vuelve a calcular la zona del punto con las
 * delimitaciones guardadas (LocalizadorZonas, el mismo cálculo de "Mover
 * ubicación"): debe ser la zona de la ruta elegida, porque la zona del
 * proveedor se deriva de su ruta. Lo que detectó el navegador no basta.
 */
final class GuardarAsignacionProveedor
{
    public function __construct(
        private readonly RutaRepository $rutas,
        private readonly RutaDetalleRepository $rutaDetalles,
        private readonly AsignarProveedorARuta $asignarProveedorARuta,
        private readonly LocalizadorZonas $localizador,
    ) {}

    public function execute(
        int $proveedorId,
        int $rutaId,
        ?float $latitud = null,
        ?float $longitud = null,
        ?string $referenciaUbicacion = null,
        ?string $motivo = null,
    ): RutaDetalle {
        if ($latitud !== null && $longitud !== null) {
            $this->verificarZonaDelPunto($rutaId, $latitud, $longitud);
        }

        $vigente = $this->rutaDetalles->buscarPorProveedor($proveedorId);

        if ($vigente === null || $vigente->rutaId() !== $rutaId) {
            return $this->asignarProveedorARuta->execute(
                rutaId: $rutaId,
                proveedorId: $proveedorId,
                motivo: $motivo,
                latitud: $latitud,
                longitud: $longitud,
                referenciaUbicacion: $referenciaUbicacion,
            );
        }

        if ($this->rutas->buscarPorId($rutaId) === null) {
            throw new DomainException('La ruta indicada no existe.');
        }

        return $this->rutaDetalles->actualizarUbicacion(
            $vigente->conUbicacion($latitud, $longitud, $referenciaUbicacion),
        );
    }

    private function verificarZonaDelPunto(int $rutaId, float $latitud, float $longitud): void
    {
        $ruta = $this->rutas->buscarPorId($rutaId);

        if ($ruta === null) {
            throw new DomainException('La ruta indicada no existe.');
        }

        $zona = $this->localizador->zonaEn($latitud, $longitud, preferidaId: $ruta->zonaId());

        if ($zona === null) {
            throw new UbicacionFueraDeZona('La ubicación seleccionada no pertenece a ninguna zona operativa.');
        }

        if ($zona->id() !== $ruta->zonaId()) {
            throw new UbicacionFueraDeZona("La ubicación marcada está en {$zona->nombre()}: elige una ruta de {$zona->nombre()} o corrige el marcador.");
        }
    }
}
