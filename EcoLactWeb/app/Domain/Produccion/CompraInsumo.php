<?php

namespace App\Domain\Produccion;

use App\Domain\Shared\Enums\EstadoPagoCompra;
use App\Domain\Shared\Enums\EstadoRecepcionCompra;
use App\Domain\Shared\Enums\TipoComprobanteCompra;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Cabecera de una adquisición de Compras y gastos: insumos de producción,
 * limpieza, refrigerios, equipos, servicios... desde una boleta escaneada
 * (BOLETA) o registrada a mano (MANUAL). Conserva el nombre histórico
 * (tabla compras_insumo) porque Inventario y Caja ya la referencian por id.
 *
 * El usuario que la registra es quien la CONFIRMÓ en EcoLact, no el
 * proveedor comercial. `estadoPago` y `montoPagado` resumen sus pagos
 * (PagoCompra); el saldo pendiente es la "obligación" de la compra: no se
 * duplica en obligaciones_pago, que es de proveedores de leche y personal.
 */
final class CompraInsumo
{
    public const ORIGEN_BOLETA = 'BOLETA';

    public const ORIGEN_MANUAL = 'MANUAL';

    public function __construct(
        private readonly ?int $id,
        private readonly string $origen,
        private readonly DateTimeImmutable $fecha,
        private readonly int $registradoPorUserId,
        private readonly ?string $proveedorComercial = null,
        private readonly ?string $ruc = null,
        private readonly ?string $numeroComprobante = null,
        private readonly ?float $total = null,
        private readonly ?string $imagenBoleta = null,
        private readonly ?string $observacion = null,
        private readonly ?DateTimeImmutable $registradoEn = null,
        private readonly ?string $codigo = null,
        private readonly ?int $categoriaCompraId = null,
        private readonly ?int $proveedorComercialId = null,
        private readonly ?TipoComprobanteCompra $tipoComprobante = null,
        private readonly string $moneda = 'PEN',
        private readonly ?string $concepto = null,
        private readonly float $impuesto = 0.0,
        private readonly EstadoRecepcionCompra $estadoRecepcion = EstadoRecepcionCompra::RECIBIDA,
        private readonly ?DateTimeImmutable $recibidaEn = null,
        private readonly float $montoPagado = 0.0,
    ) {
        if (! in_array($origen, [self::ORIGEN_BOLETA, self::ORIGEN_MANUAL], true)) {
            throw new InvalidArgumentException('El origen de la compra debe ser BOLETA o MANUAL.');
        }

        if ($total !== null && $total < 0.0) {
            throw new InvalidArgumentException('El total de la compra no puede ser negativo.');
        }

        if ($impuesto < 0.0) {
            throw new InvalidArgumentException('El impuesto no puede ser negativo.');
        }

        if ($montoPagado < 0.0 || ($total !== null && round($montoPagado, 2) > round($total, 2))) {
            throw new InvalidArgumentException('Lo pagado no puede superar el total de la compra.');
        }
    }

    /** "C-0001": el código que muestran Compras, Caja e Inventario. */
    public static function codigoPara(int $id): string
    {
        return 'C-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function codigo(): ?string
    {
        return $this->codigo ?? ($this->id !== null ? self::codigoPara($this->id) : null);
    }

    public function origen(): string
    {
        return $this->origen;
    }

    public function fecha(): DateTimeImmutable
    {
        return $this->fecha;
    }

    public function registradoPorUserId(): int
    {
        return $this->registradoPorUserId;
    }

    public function proveedorComercial(): ?string
    {
        return $this->proveedorComercial;
    }

    public function ruc(): ?string
    {
        return $this->ruc;
    }

    public function numeroComprobante(): ?string
    {
        return $this->numeroComprobante;
    }

    public function total(): ?float
    {
        return $this->total;
    }

    public function imagenBoleta(): ?string
    {
        return $this->imagenBoleta;
    }

    public function observacion(): ?string
    {
        return $this->observacion;
    }

    public function registradoEn(): ?DateTimeImmutable
    {
        return $this->registradoEn;
    }

    public function categoriaCompraId(): ?int
    {
        return $this->categoriaCompraId;
    }

    public function proveedorComercialId(): ?int
    {
        return $this->proveedorComercialId;
    }

    public function tipoComprobante(): ?TipoComprobanteCompra
    {
        return $this->tipoComprobante;
    }

    public function moneda(): string
    {
        return $this->moneda;
    }

    public function concepto(): ?string
    {
        return $this->concepto;
    }

    public function impuesto(): float
    {
        return round($this->impuesto, 2);
    }

    public function estadoRecepcion(): EstadoRecepcionCompra
    {
        return $this->estadoRecepcion;
    }

    public function recibidaEn(): ?DateTimeImmutable
    {
        return $this->recibidaEn;
    }

    public function montoPagado(): float
    {
        return round($this->montoPagado, 2);
    }

    public function estadoPago(): EstadoPagoCompra
    {
        return EstadoPagoCompra::desde($this->total, $this->montoPagado);
    }

    /** Lo que falta pagar; 0 si no tiene total. */
    public function saldoPendiente(): float
    {
        return $this->total !== null ? max(0.0, round($this->total - $this->montoPagado, 2)) : 0.0;
    }

    /**
     * Referencia corta para movimientos e historial: "Boleta B001-203" o "Compra C-0058".
     */
    public function referencia(): string
    {
        if ($this->numeroComprobante !== null) {
            return ($this->tipoComprobante?->etiqueta() ?? 'Boleta').' '.$this->numeroComprobante;
        }

        return 'Compra '.($this->codigo() ?? '#'.$this->id);
    }
}
