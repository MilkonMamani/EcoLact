<?php

namespace App\Application\Compra;

use App\Application\Shared\TransactionManager;
use App\Domain\Auditoria\AuditoriaRepository;
use App\Domain\Compra\CategoriaCompra;
use App\Domain\Compra\CategoriaCompraRepository;
use App\Domain\Compra\ComprobanteCompra;
use App\Domain\Compra\ComprobanteCompraRepository;
use App\Domain\Compra\ProveedorComercialRepository;
use App\Domain\Produccion\CompraInsumo;
use App\Domain\Produccion\CompraInsumoDetalle;
use App\Domain\Produccion\CompraInsumoRepository;
use App\Domain\Shared\Enums\CondicionPagoCompra;
use App\Domain\Shared\Enums\EstadoRecepcionCompra;
use App\Domain\Shared\Enums\MetodoPago;
use App\Domain\Shared\Enums\TipoComprobanteCompra;
use App\Domain\Shared\Enums\TipoDocumentoCompra;
use App\Domain\Shared\Enums\TratamientoCompra;
use DateTimeImmutable;
use DomainException;

/**
 * CONFIRMAR una compra o gasto: el único flujo de registro de Compras y
 * gastos, venga de una boleta escaneada o del registro manual (y de la app
 * de planta, vía RegistrarCompraInsumos). Lo llama la persona después de
 * revisar el resumen; el OCR nunca llega aquí por su cuenta.
 *
 * Todo en UNA transacción (todo o nada):
 *   1. recalcula la compra con CalcularCompra (no confía en la pantalla),
 *   2. resuelve el tipo de adquisición y el proveedor comercial,
 *   3. crea la cabecera (C-0001) y sus líneas,
 *   4. si los artículos ya llegaron, RecibirCompra ingresa el stock (una vez),
 *   5. vincula los comprobantes ya guardados (un archivo, una fila),
 *   6. según la condición de pago, RegistrarPagoCompra registra el monto
 *      REALMENTE pagado y su única salida de caja; a crédito no sale nada.
 *
 * @phpstan-import-type LineaEntrada from CalcularCompra
 *
 * @phpstan-type Documento array{tipo: string, disco: string, ruta: string, nombre_original?: ?string, mime?: ?string, tamano?: ?int, procesado_ocr?: bool}
 * @phpstan-type DatosCompra array{categoria_compra_id?: int|string|null, proveedor_comercial?: ?string, ruc?: ?string, tipo_comprobante?: ?string, numero_comprobante?: ?string, fecha?: ?string, moneda?: ?string, concepto?: ?string, observacion?: ?string, impuesto?: float|string|null, total?: float|string|null, recepcion?: ?string, condicion_pago?: ?string, monto_pago?: float|string|null, metodo_pago?: ?string, numero_operacion?: ?string, imagen_boleta?: ?string, documentos?: list<Documento>}
 */
final class RegistrarCompra
{
    public const ACCION = 'COMPRA_REGISTRADA';

    public function __construct(
        private readonly CalcularCompra $calcular,
        private readonly CompraInsumoRepository $compras,
        private readonly CategoriaCompraRepository $categorias,
        private readonly ProveedorComercialRepository $proveedores,
        private readonly ComprobanteCompraRepository $comprobantes,
        private readonly RecibirCompra $recibir,
        private readonly RegistrarPagoCompra $pagar,
        private readonly TransactionManager $transacciones,
        private readonly AuditoriaRepository $auditorias,
    ) {}

    /**
     * @param  list<LineaEntrada>  $lineas
     * @param  DatosCompra  $datos
     *
     * @throws DomainException si algo no es válido (no se registra nada).
     */
    public function execute(array $lineas, array $datos, int $registradoPorUserId): CompraInsumo
    {
        $texto = static fn (mixed $valor): ?string => is_string($valor) && trim($valor) !== '' ? trim($valor) : null;

        return $this->transacciones->run(function () use ($lineas, $datos, $registradoPorUserId, $texto): CompraInsumo {
            $calculo = $this->calcular->execute($lineas, $datos['impuesto'] ?? 0);
            $categoria = $this->categoria($datos['categoria_compra_id'] ?? null, $calculo['genera_stock'], $calculo['lineas']);

            // Un total escrito a mano solo lo envía la app de planta (sin precios por línea).
            $total = isset($datos['total']) && $datos['total'] !== '' && $datos['total'] !== null ? round((float) $datos['total'], 2) : $calculo['total'];
            $pago = $this->pagoInicial($datos, $total);

            $razonSocial = $texto($datos['proveedor_comercial'] ?? null);
            $ruc = $texto($datos['ruc'] ?? null);
            $proveedor = $razonSocial !== null ? $this->proveedores->buscarOCrear($razonSocial, $ruc) : null;

            $numero = $texto($datos['numero_comprobante'] ?? null);
            $imagen = $texto($datos['imagen_boleta'] ?? null);
            $documentos = $datos['documentos'] ?? [];
            $escaneada = $imagen !== null || array_filter($documentos, fn (array $d): bool => (bool) ($d['procesado_ocr'] ?? false)) !== [];
            $tipoComprobante = TipoComprobanteCompra::tryFrom((string) ($datos['tipo_comprobante'] ?? ''))
                ?? ($numero !== null ? TipoComprobanteCompra::BOLETA : TipoComprobanteCompra::SIN_COMPROBANTE);

            $compra = $this->compras->guardar(new CompraInsumo(
                id: null,
                origen: $escaneada ? CompraInsumo::ORIGEN_BOLETA : CompraInsumo::ORIGEN_MANUAL,
                fecha: isset($datos['fecha']) && $datos['fecha'] !== '' && $datos['fecha'] !== null ? new DateTimeImmutable($datos['fecha']) : new DateTimeImmutable,
                registradoPorUserId: $registradoPorUserId,
                proveedorComercial: $proveedor?->razonSocial() ?? $razonSocial,
                ruc: $ruc,
                numeroComprobante: $numero !== null ? mb_strtoupper($numero) : null,
                total: $total,
                imagenBoleta: $imagen,
                observacion: $texto($datos['observacion'] ?? null),
                categoriaCompraId: $categoria->id(),
                proveedorComercialId: $proveedor?->id(),
                tipoComprobante: $tipoComprobante,
                moneda: $texto($datos['moneda'] ?? null) ?? 'PEN',
                concepto: mb_substr($texto($datos['concepto'] ?? null) ?? $this->conceptoPorDefecto($calculo['lineas']), 0, 150),
                impuesto: $calculo['impuesto'],
                // Se crea por recibir: RecibirCompra la marca RECIBIDA al ingresar el stock.
                estadoRecepcion: $calculo['genera_stock'] ? EstadoRecepcionCompra::PENDIENTE : EstadoRecepcionCompra::NO_APLICA,
            ));
            $compraId = (int) $compra->id();

            foreach ($calculo['lineas'] as $linea) {
                $this->compras->guardarDetalle($this->detalle($linea, $compraId));
            }

            if ($calculo['genera_stock'] && ($datos['recepcion'] ?? EstadoRecepcionCompra::RECIBIDA->value) !== EstadoRecepcionCompra::PENDIENTE->value) {
                $this->recibir->execute($compraId, $registradoPorUserId);
            }

            if ($imagen !== null) {
                $documentos[] = ['tipo' => TipoDocumentoCompra::BOLETA->value, 'disco' => 'public', 'ruta' => $imagen, 'nombre_original' => basename($imagen), 'mime' => null, 'tamano' => null, 'procesado_ocr' => true];
            }
            foreach ($documentos as $documento) {
                $this->comprobantes->guardar(new ComprobanteCompra(
                    id: null,
                    compraInsumoId: $compraId,
                    tipo: TipoDocumentoCompra::tryFrom($documento['tipo']) ?? TipoDocumentoCompra::OTRO,
                    disco: $documento['disco'],
                    ruta: $documento['ruta'],
                    subidoPorUserId: $registradoPorUserId,
                    nombreOriginal: $documento['nombre_original'] ?? null,
                    mime: $documento['mime'] ?? null,
                    tamano: $documento['tamano'] ?? null,
                    procesadoOcr: (bool) ($documento['procesado_ocr'] ?? false),
                ));
            }

            if ($pago !== null) {
                $this->pagar->execute(
                    compraId: $compraId,
                    monto: $pago['monto'],
                    metodoPago: $pago['metodo'],
                    numeroOperacion: $texto($datos['numero_operacion'] ?? null),
                    observacion: $pago['condicion'] === CondicionPagoCompra::PARCIAL ? 'Pago inicial' : null,
                    registradoPorUserId: $registradoPorUserId,
                );
            }

            $this->auditorias->registrar(
                userId: $registradoPorUserId,
                accion: self::ACCION,
                tablaAfectada: 'compras_insumo',
                registroId: $compraId,
                datosAnteriores: null,
                datosNuevos: [
                    'codigo' => $compra->codigo(),
                    'categoria' => $categoria->codigo(),
                    'total' => $total,
                    'lineas' => count($calculo['lineas']),
                    'condicion_pago' => $pago['condicion']->value ?? ($total === null ? null : CondicionPagoCompra::CREDITO->value),
                    'pagado' => $pago['monto'] ?? 0.0,
                ],
                motivo: null,
            );

            return $this->compras->buscarPorId($compraId) ?? throw new DomainException('No se pudo registrar la compra.');
        });
    }

    /**
     * Sin tipo elegido (app de planta o web sin elegirlo): se deduce de las
     * líneas. Todo inventario: insumos de producción; todo bienes: equipos y
     * herramientas; todo servicios: servicios; lo demás (incluidas las mixtas):
     * otros gastos.
     *
     * @param  list<array<string, mixed>>  $lineas
     */
    private function categoria(int|string|null $id, bool $generaStock, array $lineas): CategoriaCompra
    {
        if ($id !== null && $id !== '') {
            $categoria = $this->categorias->buscarPorId((int) $id);

            if ($categoria === null || ! $categoria->activa()) {
                throw new DomainException('Elige un tipo de compra válido.');
            }

            return $categoria;
        }

        $tratamientos = array_values(array_unique(array_map(fn (array $l): string => (string) $l['tratamiento'], $lineas)));
        $unico = count($tratamientos) === 1 ? TratamientoCompra::tryFrom($tratamientos[0]) : null;

        $codigo = match (true) {
            $generaStock && $unico === TratamientoCompra::INVENTARIO => CategoriaCompra::INSUMOS_PRODUCCION,
            $unico === TratamientoCompra::BIEN => 'EQUIPOS_HERRAMIENTAS',
            $unico === TratamientoCompra::SERVICIO => 'SERVICIOS',
            default => 'OTROS_GASTOS',
        };

        return ($this->categorias->buscarPorCodigo($codigo) ?? $this->categorias->buscarPorCodigo('OTROS_GASTOS'))
            ?? throw new DomainException('No están configurados los tipos de compra.');
    }

    /**
     * Lo que se paga al registrar. Sin condición explícita (app de planta),
     * una compra con importe se paga al contado, como siempre.
     *
     * @param  DatosCompra  $datos
     * @return array{condicion: CondicionPagoCompra, monto: float, metodo: ?MetodoPago}|null
     */
    private function pagoInicial(array $datos, ?float $total): ?array
    {
        $explicita = CondicionPagoCompra::tryFrom((string) ($datos['condicion_pago'] ?? ''));
        $metodo = MetodoPago::tryFrom((string) ($datos['metodo_pago'] ?? ''));

        if ($total === null || $total <= 0.0) {
            if ($explicita !== null && $explicita !== CondicionPagoCompra::CREDITO) {
                throw new DomainException('Indica el precio de cada artículo: sin importe no se puede registrar el pago.');
            }

            return null;
        }

        $condicion = $explicita ?? CondicionPagoCompra::CONTADO;

        if ($condicion === CondicionPagoCompra::CREDITO) {
            return null;
        }

        if ($condicion === CondicionPagoCompra::CONTADO) {
            return ['condicion' => $condicion, 'monto' => $total, 'metodo' => $metodo];
        }

        $monto = round((float) ($datos['monto_pago'] ?? 0), 2);
        if ($monto <= 0.0 || $monto >= $total) {
            throw new DomainException('El pago inicial debe ser mayor que cero y menor que el total (S/ '.number_format($total, 2).'). Si pagas todo, elige «Pago total».');
        }

        return ['condicion' => $condicion, 'monto' => $monto, 'metodo' => $metodo];
    }

    /**
     * "Cuajo" o "Gaseosa 3 L y 2 más".
     *
     * @param  list<array<string, mixed>>  $lineas
     */
    private function conceptoPorDefecto(array $lineas): string
    {
        $primera = (string) $lineas[0]['descripcion'];

        return count($lineas) > 1 ? $primera.' y '.(count($lineas) - 1).' más' : $primera;
    }

    /**
     * @param  array<string, mixed>  $linea  una línea de CalcularCompra
     */
    private function detalle(array $linea, int $compraId): CompraInsumoDetalle
    {
        $inventario = $linea['inventario'];

        if ($inventario !== null) {
            return new CompraInsumoDetalle(
                id: null,
                compraInsumoId: $compraId,
                insumoId: $inventario['insumo_id'],
                presentacionInsumoId: $inventario['presentacion_id'],
                presentacionNombre: $inventario['presentacion_nombre'],
                presentacionCantidad: $inventario['presentacion_cantidad'],
                presentacionUnidad: $inventario['presentacion_unidad'],
                cantidadPresentaciones: $inventario['cantidad'],
                cantidadBase: $inventario['total'],
                unidadBase: $inventario['unidad_base'],
                precioUnitario: $inventario['precio_unitario'],
                tratamiento: TratamientoCompra::INVENTARIO,
                descripcion: $inventario['insumo'],
            );
        }

        return new CompraInsumoDetalle(
            id: null,
            compraInsumoId: $compraId,
            insumoId: null,
            presentacionInsumoId: null,
            presentacionNombre: null,
            presentacionCantidad: null,
            presentacionUnidad: null,
            cantidadPresentaciones: $linea['cantidad'],
            cantidadBase: null,
            unidadBase: null,
            precioUnitario: $linea['precio_unitario'],
            tratamiento: TratamientoCompra::from($linea['tratamiento']),
            descripcion: $linea['descripcion'],
            unidad: $linea['unidad'],
        );
    }
}
