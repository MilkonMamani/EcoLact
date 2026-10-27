<?php

namespace App\Application\Calidad;

use App\Application\Calidad\Contracts\FirmaPropuestaMuestra;
use App\Domain\Calidad\MuestraYaSeleccionadaException;
use App\Domain\Calidad\MuestreoCalidad;
use App\Domain\Calidad\MuestreoCalidadRepository;
use App\Domain\Entrega\EntregaRepository;
use App\Domain\Proveedor\ProveedorRepository;
use App\Domain\Shared\Enums\EstadoEntrega;
use App\Domain\Shared\Enums\EstadoMuestreo;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Domain\Shared\Enums\MetodoSeleccionMuestreo;
use App\Domain\Shared\Enums\TipoControlMuestreo;
use DateTimeImmutable;
use DomainException;

/**
 * Selecciona una entrega YA REGISTRADA por el acopiador para control de
 * calidad (flujo principal del analista). Solo las entregas seleccionadas
 * aparecen en "Muestras pendientes"; el resto nunca se marca como pendiente.
 *
 * Reglas:
 * - la entrega existe, no está anulada ni analizada y su proveedor está activo;
 * - una entrega se selecciona una sola vez: si otro analista ya la eligió se
 *   lanza MuestraYaSeleccionadaException (conflicto, nunca se duplica). Si es
 *   el MISMO analista reenviando la misma selección (reintento offline tras
 *   perder la respuesta) se devuelve la muestra existente;
 * - no hay límite de tiempo: los 10 minutos de Entrega::MINUTOS_EDICION_LITROS
 *   solo limitan la corrección de litros, no el muestreo ni el análisis;
 * - fechaSeleccion es la hora real en el teléfono (puede venir de una
 *   operación offline); nunca se acepta una hora futura;
 * - una selección ALEATORIA solo se acepta con la firma del sorteo del
 *   servidor (ElegirMuestraAleatoria): el cliente no decide el azar.
 */
final class SeleccionarMuestraCalidad
{
    public function __construct(
        private readonly EntregaRepository $entregas,
        private readonly ProveedorRepository $proveedores,
        private readonly MuestreoCalidadRepository $muestreos,
        private readonly FirmaPropuestaMuestra $firma,
    ) {}

    public function execute(
        int $entregaId,
        int $analistaUserId,
        MetodoSeleccionMuestreo $metodo,
        ?TipoControlMuestreo $tipoControl = null,
        ?DateTimeImmutable $fechaSeleccion = null,
        ?string $observacion = null,
        ?string $propuestaToken = null,
    ): MuestreoCalidad {
        if ($metodo === MetodoSeleccionMuestreo::DIRECTA) {
            throw new DomainException('El muestreo directo se registra junto con la entrega del proveedor.');
        }

        $entrega = $this->entregas->buscarPorId($entregaId)
            ?? throw new DomainException('La entrega indicada no existe.');

        $existente = $this->muestreos->buscarPorEntregaId($entregaId);

        if ($existente !== null) {
            if ($existente->seleccionadoPorUserId() === $analistaUserId && $existente->metodoSeleccion() === $metodo) {
                return $existente;
            }

            throw new MuestraYaSeleccionadaException;
        }

        if ($metodo === MetodoSeleccionMuestreo::ALEATORIA && ! $this->firma->esValida($propuestaToken, $entregaId, $analistaUserId)) {
            throw new DomainException('El sorteo venció o no corresponde a esta entrega. Vuelve a elegir una muestra al azar.');
        }

        match ($entrega->estado()) {
            EstadoEntrega::ANALIZADA => throw new DomainException('Esta entrega ya fue analizada.'),
            EstadoEntrega::ANULADA => throw new DomainException('Esta entrega fue anulada y no se puede muestrear.'),
            EstadoEntrega::PENDIENTE_ANALISIS => null,
        };

        $proveedor = $this->proveedores->buscarPorId($entrega->proveedorId());

        if ($proveedor === null || $proveedor->estado() !== EstadoUsuario::ACTIVO) {
            throw new DomainException('El proveedor de esta entrega no está activo.');
        }

        $ahora = new DateTimeImmutable;

        return $this->muestreos->guardar(new MuestreoCalidad(
            id: null,
            entregaId: $entregaId,
            seleccionadoPorUserId: $analistaUserId,
            metodoSeleccion: $metodo,
            tipoControl: $metodo === MetodoSeleccionMuestreo::MANUAL ? ($tipoControl ?? TipoControlMuestreo::RUTINARIO) : null,
            fechaSeleccion: $fechaSeleccion !== null && $fechaSeleccion < $ahora ? $fechaSeleccion : $ahora,
            estado: EstadoMuestreo::SELECCIONADA,
            observacion: $observacion !== null && trim($observacion) !== '' ? trim($observacion) : null,
        ));
    }
}
