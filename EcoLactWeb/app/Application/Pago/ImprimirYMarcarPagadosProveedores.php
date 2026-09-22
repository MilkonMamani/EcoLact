<?php

namespace App\Application\Pago;

use App\Application\Shared\TransactionManager;
use App\Domain\Pago\ObligacionPagoRepository;
use App\Domain\Shared\Enums\MetodoPago;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use DateTimeImmutable;
use DomainException;

/**
 * Confirma, en una sola acción, la impresión de los comprobantes y el pago en
 * EFECTIVO de varios proveedores. Es todo o nada: si un solo pago no se puede
 * registrar (ya pagado, monto que cambió desde la vista previa, etc.), no se
 * marca ninguno y no queda un lote a medias.
 *
 * Por cada pago se guarda una copia del comprobante tal como se imprimió, el
 * usuario que confirmó, la fecha y hora y el código del lote.
 */
final class ImprimirYMarcarPagadosProveedores
{
    public function __construct(
        private readonly ObligacionPagoRepository $obligaciones,
        private readonly ObtenerComprobantesPagoProveedor $comprobantes,
        private readonly RegistrarPagoObligacion $registrarPago,
        private readonly TransactionManager $transacciones,
    ) {}

    /**
     * @param  int[]  $obligacionIds
     * @param  array<int, float|null>  $montosVistos  monto que el administrador vio en la vista previa, por id de pago;
     *                                                si cambió (llegó otra entrega o un descuento) se rechaza el lote
     *
     * @throws DomainException
     */
    public function execute(array $obligacionIds, array $montosVistos, int $registradoPorUserId, ?DateTimeImmutable $ahora = null): ResultadoPagoEnLote
    {
        $ids = array_values(array_unique(array_map('intval', $obligacionIds)));

        if ($ids === []) {
            throw new DomainException('Selecciona al menos un proveedor para imprimir y marcar como pagado.');
        }

        $ahora ??= DateTimeImmutable::createFromInterface(now());
        $lote = 'PP-'.$ahora->format('Ymd-His').'-'.strtoupper(bin2hex(random_bytes(2)));

        return $this->transacciones->run(function () use ($ids, $montosVistos, $registradoPorUserId, $ahora, $lote): ResultadoPagoEnLote {
            $total = 0.0;

            foreach ($ids as $id) {
                $obligacion = $this->obligaciones->buscarPorIdBloqueando($id);

                if ($obligacion === null || $obligacion->tipoBeneficiario() !== TipoBeneficiarioPago::PROVEEDOR) {
                    throw new DomainException('Uno de los pagos seleccionados ya no existe. Actualiza la pantalla e inténtalo de nuevo.');
                }

                if (! $obligacion->estaPendiente()) {
                    throw new DomainException('El pago de '.$obligacion->beneficiarioNombre().' ya fue registrado. Actualiza la pantalla e inténtalo de nuevo.');
                }

                $comprobante = $this->comprobantes->execute([$id])[0]
                    ?? throw new DomainException('No se pudo generar el comprobante del pago #'.$id.'.');

                $this->registrarPago->execute(
                    obligacionId: $id,
                    metodoPago: MetodoPago::EFECTIVO,
                    numeroOperacion: null,
                    fechaPago: $ahora,
                    observacion: null,
                    comprobante: null,
                    registradoPorUserId: $registradoPorUserId,
                    montoDeclarado: $montosVistos[$id] ?? null,
                    loteCodigo: $lote,
                    impresoAt: $ahora,
                    comprobanteSnapshot: $comprobante->toArray(),
                );

                $total += $comprobante->totalFinal;
            }

            return new ResultadoPagoEnLote($lote, count($ids), round($total, 2));
        });
    }
}
