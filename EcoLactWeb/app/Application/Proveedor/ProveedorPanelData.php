<?php

namespace App\Application\Proveedor;

/**
 * Panel lateral de un proveedor en la pantalla de Proveedores (admin):
 * cabecera, Resumen (QR, datos personales, ubicación y zona) y los
 * historiales de las pestañas Entregas, Calidad y Compras.
 *
 * Los historiales traen solo los registros más recientes
 * (EloquentProveedorPanelQuery::LIMITE); los contadores *Total dicen
 * cuántos hay en realidad.
 *
 * Compras solo incluye las ventas con forma de pago DESCUENTO_PLANILLA no
 * anuladas: son las que el proveedor pidió cargar a su pago semanal y, en
 * el flujo del negocio, quedan descontadas en el momento de la compra
 * (RegistrarVentaProducto ya valida el importe contra su pago estimado y
 * las ata al periodo). Las compras al contado no se descuentan de nada.
 */
final readonly class ProveedorPanelData
{
    /**
     * @param  list<array{id: int, fecha: string, zona: string, litros: float, identificacion: string, estado: string}>  $entregas
     * @param  list<array{id: int, fecha: string, entregaId: int, resultado: ?string, estado: string}>  $analisis
     * @param  list<array{id: int, fecha: string, producto: string, cantidad: float, total: float}>  $compras
     */
    public function __construct(
        public int $id,
        public string $nombres,
        public string $apellidos,
        public string $dni,
        public ?string $telefono,
        public string $estado,
        public string $fechaRegistro,
        public string $qrToken,
        public ?string $zona,
        public ?int $zonaId,
        public ?string $ruta,
        public ?string $latitud,
        public ?string $longitud,
        public ?string $referencia,
        public array $entregas,
        public int $entregasTotal,
        public array $analisis,
        public int $analisisTotal,
        public array $compras,
        public int $comprasTotal,
        public float $totalDescontado,
    ) {}

    public function nombreCompleto(): string
    {
        return trim($this->nombres.' '.$this->apellidos);
    }

    public function iniciales(): string
    {
        $primera = fn (string $texto): string => mb_strtoupper(mb_substr(trim($texto), 0, 1));

        return $primera($this->nombres).$primera($this->apellidos);
    }

    public function estaActivo(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    public function tieneRuta(): bool
    {
        return $this->ruta !== null;
    }

    public function tieneCoordenadas(): bool
    {
        return $this->latitud !== null && $this->longitud !== null;
    }
}
