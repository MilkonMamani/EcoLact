<?php

namespace App\Application\Calidad;

use App\Application\Pago\SincronizarPagosProveedor;
use App\Application\Shared\TransactionManager;
use App\Domain\Calidad\AnalisisCalidad;
use App\Domain\Calidad\AnalisisCalidadRepository;
use App\Domain\Comunicacion\Notificacion;
use App\Domain\Comunicacion\Repositories\NotificacionRepository;
use App\Domain\Entrega\Entrega;
use App\Domain\Entrega\EntregaRepository;
use App\Domain\Proveedor\ProveedorRepository;
use App\Domain\Shared\Enums\EstadoEntrega;
use App\Domain\Shared\Enums\ResultadoCalidad;
use DomainException;

/**
 * Confirma un análisis de calidad BORRADOR: calcula el resultado según las
 * reglas vigentes y lo deja inmutable (CONFIRMADO). Después recalcula el pago
 * pendiente del proveedor con el ajuste de calidad correspondiente. Un análisis ya
 * confirmado no puede volver a finalizarse (AnalisisCalidad::confirmar()
 * lanza la excepción de dominio correspondiente).
 */
final class FinalizarAnalisisCalidad
{
    private const TIPOS_NOTIFICACION = [
        ResultadoCalidad::ACEPTADA->value => 'ANALISIS_ACEPTADO',
        ResultadoCalidad::OBSERVADA->value => 'ANALISIS_OBSERVADO',
        ResultadoCalidad::RECHAZADA->value => 'ANALISIS_RECHAZADO',
    ];

    public function __construct(
        private readonly AnalisisCalidadRepository $analisisCalidad,
        private readonly CalcularResultadoCalidad $calcularResultado,
        private readonly EntregaRepository $entregas,
        private readonly ProveedorRepository $proveedores,
        private readonly NotificacionRepository $notificaciones,
        private readonly RegistrarAdvertenciaCalidad $registrarAdvertencia,
        private readonly SincronizarPagosProveedor $pagosProveedor,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(int $analisisId, int $confirmadoPorUserId): AnalisisCalidad
    {
        $analisis = $this->analisisCalidad->buscarPorId($analisisId);

        if ($analisis === null) {
            throw new DomainException('El análisis de calidad indicado no existe.');
        }

        if ($analisis->estaConfirmado()) {
            throw new DomainException('Un análisis de calidad confirmado es inmutable y no puede modificarse.');
        }

        $resultado = $this->calcularResultado->execute($analisisId);

        return $this->transacciones->run(function () use ($analisis, $resultado, $confirmadoPorUserId): AnalisisCalidad {
            $analisis->confirmar($resultado, $confirmadoPorUserId);

            $analisisConfirmado = $this->analisisCalidad->actualizar($analisis);

            $entrega = $this->entregas->buscarPorId($analisisConfirmado->entregaId());

            if ($entrega !== null) {
                $this->entregas->actualizar(new Entrega(
                    id: $entrega->id(),
                    proveedorId: $entrega->proveedorId(),
                    acopiadorId: $entrega->acopiadorId(),
                    zonaId: $entrega->zonaId(),
                    registradoPorUserId: $entrega->registradoPorUserId(),
                    fechaHora: $entrega->fechaHora(),
                    litros: $entrega->litros(),
                    metodoIdentificacion: $entrega->metodoIdentificacion(),
                    estado: EstadoEntrega::ANALIZADA,
                    observacion: $entrega->observacion(),
                    recorridoDetalleId: $entrega->recorridoDetalleId(),
                    tokenIdempotencia: $entrega->tokenIdempotencia(),
                ));

                // El análisis confirmado recalcula el pago PENDIENTE del proveedor
                // (ajuste de calidad). Un pago ya PAGADO no se toca. Nunca bloquea la
                // confirmación, aunque falte configuración de pago o precio de leche.
                $this->pagosProveedor->execute($entrega->proveedorId());

                $proveedor = $this->proveedores->buscarPorId($entrega->proveedorId());

                if ($proveedor !== null) {
                    $this->notificaciones->guardar(new Notificacion(
                        id: null,
                        userId: $proveedor->userId(),
                        tipo: self::TIPOS_NOTIFICACION[$resultado->value],
                        titulo: 'Resultado de análisis de calidad',
                        mensaje: sprintf('El análisis de tu entrega #%d fue confirmado como %s.', $entrega->id(), $resultado->value),
                        referenciaTipo: 'ANALISIS_CALIDAD',
                        referenciaId: $analisisConfirmado->id(),
                        leido: false,
                    ));

                    if ($resultado !== ResultadoCalidad::ACEPTADA) {
                        $this->registrarAdvertencia->execute(
                            proveedorId: $proveedor->id() ?? throw new DomainException('El proveedor no tiene identificador válido.'),
                            analisisId: $analisisConfirmado->id() ?? throw new DomainException('El análisis confirmado no tiene identificador válido.'),
                            resultado: $resultado,
                            creadoPorUserId: $confirmadoPorUserId,
                        );
                    }
                }
            }

            return $analisisConfirmado;
        });
    }
}
