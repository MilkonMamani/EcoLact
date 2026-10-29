<?php

namespace App\Application\Compra;

use App\Domain\Auditoria\AuditoriaRepository;
use App\Domain\Compra\ComprobanteCompra;
use App\Domain\Compra\ComprobanteCompraRepository;
use App\Domain\Produccion\CompraInsumoRepository;
use App\Domain\Shared\Enums\TipoDocumentoCompra;
use DomainException;

/**
 * Adjunta DESPUÉS un documento a una compra ya registrada (la factura que
 * llegó luego, la constancia de la transferencia del saldo...). El archivo ya
 * lo guardó la capa HTTP en el disco privado; aquí solo se vincula. No cambia
 * importes, pagos ni stock.
 */
final class AdjuntarComprobanteCompra
{
    public const ACCION = 'COMPROBANTE_COMPRA_ADJUNTADO';

    public function __construct(
        private readonly CompraInsumoRepository $compras,
        private readonly ComprobanteCompraRepository $comprobantes,
        private readonly AuditoriaRepository $auditorias,
    ) {}

    /**
     * @throws DomainException si la compra no existe.
     */
    public function execute(int $compraId, TipoDocumentoCompra $tipo, string $disco, string $ruta, ?string $nombreOriginal, ?string $mime, ?int $tamano, int $userId): ComprobanteCompra
    {
        $compra = $this->compras->buscarPorId($compraId) ?? throw new DomainException('La compra no existe.');

        $comprobante = $this->comprobantes->guardar(new ComprobanteCompra(
            id: null,
            compraInsumoId: $compraId,
            tipo: $tipo,
            disco: $disco,
            ruta: $ruta,
            subidoPorUserId: $userId,
            nombreOriginal: $nombreOriginal,
            mime: $mime,
            tamano: $tamano,
        ));

        $this->auditorias->registrar(
            userId: $userId,
            accion: self::ACCION,
            tablaAfectada: 'comprobantes_compra',
            registroId: $comprobante->id(),
            datosAnteriores: null,
            datosNuevos: ['compra' => $compra->codigo(), 'tipo' => $tipo->value, 'nombre' => $nombreOriginal],
            motivo: null,
        );

        return $comprobante;
    }
}
