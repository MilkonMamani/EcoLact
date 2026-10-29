<?php

namespace App\Domain\Shared\Enums;

/**
 * Archivos adjuntos a una compra. Los comprobantes de COMPRA (boleta,
 * factura, recibo, nota de crédito) se distinguen de la constancia de PAGO
 * (transferencia), que prueba un desembolso y no la adquisición.
 */
enum TipoDocumentoCompra: string
{
    case BOLETA = 'BOLETA';
    case FACTURA = 'FACTURA';
    case RECIBO = 'RECIBO';
    case CONSTANCIA_TRANSFERENCIA = 'CONSTANCIA_TRANSFERENCIA';
    case NOTA_CREDITO = 'NOTA_CREDITO';
    case OTRO = 'OTRO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::BOLETA => 'Boleta',
            self::FACTURA => 'Factura',
            self::RECIBO => 'Recibo',
            self::CONSTANCIA_TRANSFERENCIA => 'Constancia de transferencia',
            self::NOTA_CREDITO => 'Nota de crédito',
            self::OTRO => 'Otro documento',
        };
    }

    public function esDePago(): bool
    {
        return $this === self::CONSTANCIA_TRANSFERENCIA;
    }

    /**
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        return array_column(array_map(fn (self $caso): array => ['v' => $caso->value, 'e' => $caso->etiqueta()], self::cases()), 'e', 'v');
    }
}
