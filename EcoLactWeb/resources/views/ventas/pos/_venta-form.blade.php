{{--
    Nueva venta: formulario (izquierda) + resumen (derecha). Los campos que se envían son solo
    cliente, comprador_id / proveedor_id, producto_id, cantidad y forma_pago: ni precio ni tipo
    de precio. Tras un error se restauran con old() para no perder lo ingresado.
--}}
@php
    $cliente = old('cliente', 'PUBLICO');
    $formaPago = old('forma_pago', 'CONTADO');
    $compradorSel = collect($compradores)->firstWhere('id', (int) old('comprador_id'));
    $proveedorSel = collect($proveedores)->firstWhere('id', (int) old('proveedor_id'));
    $registrada = session('venta_registrada');
@endphp

@if ($registrada)
    <div class="vpos-ok" role="status" data-vpos-ok>
        <div class="vpos-ok-cabecera">
            <span class="vpos-ok-icono" aria-hidden="true"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <p class="vpos-ok-titulo">Venta registrada correctamente</p>
            <button type="button" class="caja-cerrar" data-vpos-ok-cerrar aria-label="Cerrar aviso"><x-ui.icon name="x-mark" class="h-4 w-4" /></button>
        </div>
        <dl class="vpos-ok-datos">
            @if ($registrada['esProveedor'] || $registrada['cliente'] !== 'Público general')
                <div><dt>Cliente</dt><dd>{{ $registrada['cliente'] }}</dd></div>
            @endif
            <div><dt>Producto</dt><dd>{{ $registrada['producto'] }} · {{ $registrada['cantidad'] }} {{ $registrada['cantidad'] === '1' ? 'unidad' : 'unidades' }}</dd></div>
            <div><dt>Total</dt><dd>S/ {{ number_format($registrada['total'], 2) }}</dd></div>
            <div><dt>Stock</dt><dd>{{ $registrada['stockAntes'] }} → {{ $registrada['stockDespues'] }}</dd></div>
            @if ($registrada['descuento'])
                <div><dt>Pago</dt><dd>Descuento semanal</dd></div>
            @else
                <div><dt>Caja</dt><dd class="is-entrada">+ S/ {{ number_format($registrada['total'], 2) }}</dd></div>
            @endif
        </dl>
        <p class="vpos-ok-nota">
            @if ($registrada['descuento'])
                Este importe será descontado de su liquidación.
            @endif
            <a href="{{ route($portal.'.ventas.show', $registrada['id']) }}" data-vpos-ver="{{ $registrada['id'] }}" data-vpos-ver-boleta>Ver boleta {{ $registrada['codigo'] }}</a>
        </p>
    </div>
@endif

@if (session('venta_error'))
    <p class="vpos-alerta is-error" role="alert"><x-ui.icon name="triangle-alert" class="h-4 w-4 shrink-0" /> {{ session('venta_error') }}</p>
@endif

@if (count($catalogo) === 0)
    <div class="vpos-vacio">
        <strong>No hay productos para vender</strong>
        Solo aparecen los productos activos con precio configurado en Productos.
    </div>
@else
    <form method="POST" action="{{ route($portal.'.ventas.store') }}" class="vpos-pos" data-vpos-form novalidate>
        @csrf

        <div class="vpos-form">
            {{-- ------------------------------------------------ 1. quién compra --}}
            <fieldset class="vpos-paso">
                <legend class="vpos-paso-titulo">¿Quién compra?</legend>

                <div class="vpos-opciones" role="radiogroup">
                    @foreach (['PUBLICO' => 'Público general', 'COMPRADOR' => 'Comprador registrado', 'PROVEEDOR' => 'Proveedor'] as $valor => $etiqueta)
                        <label class="vpos-opcion">
                            <input type="radio" name="cliente" value="{{ $valor }}" @checked($cliente === $valor) data-vpos-cliente>
                            <span>{{ $etiqueta }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="vpos-sub" data-vpos-si-cliente="COMPRADOR" @if ($cliente !== 'COMPRADOR') hidden @endif>
                    <div class="vpos-sub-fila">
                        <span class="vpos-etiqueta">Comprador</span>
                        <button type="button" class="vpos-link" data-modal-open="vpos-modal-comprador">+ Nuevo comprador</button>
                    </div>
                    @include('ventas.pos._buscador', [
                        'tipo' => 'comprador',
                        'campo' => 'comprador_id',
                        'opciones' => $compradores,
                        'seleccion' => $compradorSel,
                        'placeholder' => 'Buscar por nombre, documento o RUC...',
                        'vacio' => 'No hay compradores con ese nombre o documento.',
                    ])
                </div>

                <div class="vpos-sub" data-vpos-si-cliente="PROVEEDOR" @if ($cliente !== 'PROVEEDOR') hidden @endif>
                    <span class="vpos-etiqueta">Proveedor</span>
                    @include('ventas.pos._buscador', [
                        'tipo' => 'proveedor',
                        'campo' => 'proveedor_id',
                        'opciones' => $proveedores,
                        'seleccion' => $proveedorSel,
                        'placeholder' => 'Buscar proveedor por nombre o DNI...',
                        'vacio' => 'No hay proveedores activos con ese nombre o DNI.',
                    ])
                </div>
            </fieldset>

            {{-- ------------------------------------------------ 2. producto --}}
            <div class="vpos-paso">
                <label for="vpos-producto" class="vpos-paso-titulo">Producto</label>
                <select id="vpos-producto" name="producto_id" class="pt-campo vpos-campo" data-vpos-producto required>
                    <option value="">Selecciona un producto</option>
                    @foreach ($catalogo as $producto)
                        <option value="{{ $producto['id'] }}" @selected((int) old('producto_id') === $producto['id'])>{{ $producto['nombre'] }}</option>
                    @endforeach
                </select>
                <p class="vpos-ayuda" data-vpos-stock hidden></p>
                <p class="vpos-aviso" data-vpos-hint hidden></p>
            </div>

            {{-- ------------------------------------------------ 3. cantidad --}}
            <div class="vpos-paso">
                <label for="vpos-cantidad" class="vpos-paso-titulo">Cantidad</label>
                <input id="vpos-cantidad" type="number" name="cantidad" min="0" step="any" inputmode="decimal" value="{{ old('cantidad') }}" class="pt-campo vpos-campo vpos-cantidad" placeholder="0" data-vpos-cantidad required>
                <div class="vpos-precio" data-vpos-precio hidden></div>
                <p class="vpos-alerta is-error" data-vpos-sin-stock hidden></p>
            </div>

            {{-- ------------------------------------------------ 4. forma de pago --}}
            <fieldset class="vpos-paso">
                <legend class="vpos-paso-titulo">Forma de pago</legend>

                <p class="vpos-fijo" data-vpos-pago-fijo @if ($cliente === 'PROVEEDOR') hidden @endif>Contado</p>

                <div data-vpos-si-cliente="PROVEEDOR" @if ($cliente !== 'PROVEEDOR') hidden @endif>
                    <div class="vpos-opciones" role="radiogroup">
                        <label class="vpos-opcion">
                            <input type="radio" name="forma_pago" value="CONTADO" @checked($formaPago !== 'DESCUENTO_PLANILLA') data-vpos-pago>
                            <span>Contado</span>
                        </label>
                        <label class="vpos-opcion">
                            <input type="radio" name="forma_pago" value="DESCUENTO_PLANILLA" @checked($formaPago === 'DESCUENTO_PLANILLA' && $cliente === 'PROVEEDOR') data-vpos-pago>
                            <span>Descontar de pago semanal</span>
                        </label>
                    </div>

                    <dl class="vpos-saldo" data-vpos-saldo hidden>
                        <div><dt>Saldo estimado disponible</dt><dd data-vpos-saldo-disponible>—</dd></div>
                        <div><dt>Compra</dt><dd data-vpos-saldo-compra>—</dd></div>
                        <div class="is-total"><dt>Saldo estimado después</dt><dd data-vpos-saldo-despues>—</dd></div>
                    </dl>
                    <p class="vpos-alerta is-error" data-vpos-saldo-error hidden></p>
                </div>
            </fieldset>
        </div>

        @include('ventas.pos._venta-resumen')
    </form>

    <script type="application/json" data-vpos-catalogo>@json($catalogo)</script>
@endif
