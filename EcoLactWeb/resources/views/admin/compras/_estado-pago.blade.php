{{-- Píldora del estado de pago de una compra. $estado: EstadoPagoCompra. --}}
<span @class(['compras-estado', 'is-'.strtolower(str_replace('_', '-', $estado->value))])>{{ $estado->etiqueta() }}</span>
