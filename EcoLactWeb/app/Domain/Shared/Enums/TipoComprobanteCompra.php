<?php

namespace App\Domain\Shared\Enums;

/**
 * Comprobante que respalda una compra (el de la cabecera). No confundir con
 * TipoDocumentoCompra: los archivos adjuntos, que incluyen constancias de pago.
 */
enum TipoComprobanteCompra: string
{
    case BOLETA = 'BOLETA';
    case FACTURA = 'FACTURA';
    case RECIBO = 'RECIBO';
    case TICKET = 'TICKET';
    case SIN_COMPROBANTE = 'SIN_COMPROBANTE';

    public function etiqueta(): string
    {
        return match ($this) {
            self::BOLETA => 'Boleta',
            self::FACTURA => 'Factura',
            self::RECIBO => 'Recibo',
            self::TICKET => 'Ticket',
            self::SIN_COMPROBANTE => 'Sin comprobante',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        return array_column(array_map(fn (self $caso): array => ['v' => $caso->value, 'e' => $caso->etiqueta()], self::cases()), 'e', 'v');
    }
}
