{{--
    Nueva venta: formulario (izquierda) + resumen (derecha). Los campos que se envían son solo
    cliente, comprador_id / proveedor_id, producto_id, cantidad y forma_pago: ni precio ni tipo
    de precio. Tras un error se restauran con old() para no perder lo ingresado.
    Una venta es UN producto (ventas_producto guarda producto_id en la propia fila).
--}}
@php
    $cliente = old('cliente', 'PUBLICO');
    $formaPago = $cliente === 'PROVEEDOR' ? old('forma_pago', 'CONTADO') : 'CONTADO';
    $compradorSel = collect($compradores)->firstWhere('id', (int) old('comprador_id'));
    $proveedorSel = collect($proveedores)->firstWhere('id', (int) old('proveedor_id'));
    $productoSel = (int) old('producto_id');
    $registrada = session('venta_registrada');
    $tiposCliente = [
        'PUBLICO' => ['Público general', 'users', 'Venta de mostrador'],
        'COMPRADOR' => ['Comprador registrado', 'user', 'Cliente comercial'],
        'PROVEEDOR' => ['Proveedor', 'milk-jar', 'Proveedor de leche'],
    ];
    // Indicador del catálogo: cada tipo de comprador ve solo su lista de precios.
    $tarifas = [
        'PUBLICO' => 'Precios para público general',
        'COMPRADOR' => 'Precios para compradores registrados',
        'PROVEEDOR' => 'Precios para proveedores',
    ];
    $configurarPrecios = $portal === 'admin'
        ? ['Configúralo en Productos', route('admin.productos.index')]
        : ['Pide al administrador que lo configure en Productos', null];
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
        {{-- Precio que vio quien vende: el backend NO lo cobra, solo rechaza la venta si ya no coincide con la tarifa vigente. --}}
        <input type="hidden" name="precio_mostrado" value="" data-vpos-precio-mostrado>

        <div class="vpos-form">
            {{-- ------------------------------------------------ 1. quién compra --}}
            <fieldset class="vpos-paso">
                <legend class="vpos-paso-titulo"><span class="vpos-paso-num">1</span> ¿Quién compra?</legend>

                <div class="vpos-segmento" role="radiogroup" aria-label="Tipo de comprador">
                    @foreach ($tiposCliente as $valor => [$etiqueta, $icono, $detalle])
                        <label class="vpos-segmento-opcion">
                            <input type="radio" name="cliente" value="{{ $valor }}" class="sr-only" @checked($cliente === $valor) data-vpos-cliente>
                            <x-ui.icon :name="$icono" class="h-[18px] w-[18px] shrink-0" />
                            <span class="vpos-segmento-texto">
                                <span>{{ $etiqueta }}</span>
                                <small>{{ $detalle }}</small>
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="vpos-sub" data-vpos-si-cliente="COMPRADOR" @if ($cliente !== 'COMPRADOR') hidden @endif>
                    <div class="vpos-sub-fila">
                        <span class="vpos-etiqueta">Comprador registrado</span>
                        <button type="button" class="vpos-link" data-modal-open="vpos-modal-comprador">+ Nuevo comprador</button>
                    </div>
                    @include('ventas.pos._buscador', [
                        'tipo' => 'comprador',
                        'campo' => 'comprador_id',
                        'opciones' => $compradores,
                        'seleccion' => $compradorSel,
                        'placeholder' => 'Buscar comprador por nombre, documento o RUC...',
                        'vacio' => 'No hay compradores con ese nombre o documento.',
                    ])
                </div>

                <div class="vpos-sub" data-vpos-si-cliente="PROVEEDOR" @if ($cliente !== 'PROVEEDOR') hidden @endif>
                    <span class="vpos-etiqueta">Proveedor de leche</span>
                    @include('ventas.pos._buscador', [
                        'tipo' => 'proveedor',
                        'campo' => 'proveedor_id',
                        'opciones' => $proveedores,
                        'seleccion' => $proveedorSel,
                        'placeholder' => 'Buscar proveedor por nombre o DNI...',
                        'vacio' => 'No hay proveedores activos con ese nombre o DNI.',
                    ])
                </div>
                <p class="vpos-error-campo" data-vpos-error="cliente" hidden></p>
            </fieldset>

            {{-- ------------------------------------------------ 2. producto --}}
            <fieldset class="vpos-paso">
                <legend class="vpos-paso-titulo"><span class="vpos-paso-num">2</span> Producto</legend>

                <p class="vpos-tarifa" data-vpos-tarifa data-textos='@json($tarifas)' aria-live="polite">
                    <x-ui.icon name="coins" class="h-3.5 w-3.5 shrink-0" />
                    <span data-vpos-tarifa-texto>{{ $tarifas[$cliente] ?? $tarifas['PUBLICO'] }}</span>
                </p>

                @if (count($catalogo) > 6)
                    <label class="fichas-search vpos-search vpos-search-productos">
                        <span class="sr-only">Filtrar productos</span>
                        <x-ui.icon name="magnifying-glass" class="h-[18px] w-[18px] shrink-0" />
                        <input type="search" placeholder="Filtrar productos..." autocomplete="off" data-vpos-producto-filtro>
                    </label>
                @endif

                <div class="vpos-productos" role="radiogroup" aria-label="Producto" data-vpos-productos>
                    @foreach ($catalogo as $producto)
                        <label class="vpos-producto" data-vpos-producto-item data-texto="{{ Str::lower(Str::ascii($producto['nombre'])) }}">
                            <input type="radio" name="producto_id" value="{{ $producto['id'] }}" class="sr-only" @checked($productoSel === $producto['id']) @disabled($producto['stock'] <= 0) data-vpos-producto>
                            <span class="vpos-producto-info">
                                <span class="vpos-producto-nombre">{{ $producto['nombreBase'] }}</span>
                                @if ($producto['presentacion'])
                                    <span class="vpos-producto-presentacion">{{ $producto['presentacion'] }}</span>
                                @endif
                                <span class="vpos-producto-nota" data-vpos-producto-nota="{{ $producto['id'] }}" hidden></span>
                            </span>
                            <span class="vpos-producto-datos">
                                <span class="vpos-producto-precio" data-vpos-producto-precio="{{ $producto['id'] }}">—</span>
                                <span @class(['vpos-producto-stock', 'is-agotado' => $producto['stock'] <= 0])>
                                    {{ $producto['stock'] <= 0 ? 'Sin stock' : 'Stock '.\App\Http\Support\PantallaVentas::cantidad($producto['stock']) }}
                                </span>
                            </span>
                        </label>
                    @endforeach
                    <p class="vpos-productos-vacio" data-vpos-productos-vacio hidden>Ningún producto coincide con la búsqueda.</p>
                </div>
                <p class="vpos-aviso" data-vpos-sin-tarifa hidden>
                    <x-ui.icon name="info" class="h-4 w-4 shrink-0" />
                    <span>
                        <span data-vpos-sin-tarifa-texto></span>
                        @if ($configurarPrecios[1])
                            <a href="{{ $configurarPrecios[1] }}" class="vpos-link">{{ $configurarPrecios[0] }}</a>.
                        @else
                            {{ $configurarPrecios[0] }}.
                        @endif
                    </span>
                </p>
                <p class="vpos-aviso" data-vpos-hint hidden></p>
                <p class="vpos-error-campo" data-vpos-error="producto" hidden></p>
            </fieldset>

            {{-- ------------------------------------------------ 3. cantidad --}}
            <div class="vpos-paso">
                <label for="vpos-cantidad" class="vpos-paso-titulo"><span class="vpos-paso-num">3</span> Cantidad</label>
                <div class="vpos-cantidad-fila">
                    <div class="vpos-stepper" data-vpos-stepper>
                        <button type="button" class="vpos-stepper-btn" data-vpos-paso="-1" aria-label="Restar una unidad">−</button>
                        <input id="vpos-cantidad" type="number" name="cantidad" min="0" step="1" inputmode="numeric" value="{{ old('cantidad', '1') }}" class="vpos-stepper-campo" aria-describedby="vpos-cantidad-ayuda" data-vpos-cantidad required>
                        <button type="button" class="vpos-stepper-btn" data-vpos-paso="1" aria-label="Sumar una unidad">+</button>
                    </div>
                    <p id="vpos-cantidad-ayuda" class="vpos-ayuda" data-vpos-stock>Elige un producto para ver el stock disponible.</p>
                </div>
                <div class="vpos-precio" data-vpos-precio hidden></div>
                <p class="vpos-error-campo" data-vpos-error="cantidad" hidden></p>
            </div>

            {{-- ------------------------------------------------ 4. forma de pago --}}
            <fieldset class="vpos-paso">
                <legend class="vpos-paso-titulo"><span class="vpos-paso-num">4</span> Forma de pago</legend>

                <div class="vpos-pagos" role="radiogroup" aria-label="Forma de pago">
                    <label class="vpos-pago-opcion">
                        <input type="radio" name="forma_pago" value="CONTADO" class="sr-only" @checked($formaPago !== 'DESCUENTO_PLANILLA') data-vpos-pago>
                        <span class="vpos-pago-icono" aria-hidden="true"><x-ui.icon name="banknotes" class="h-5 w-5" /></span>
                        <span class="vpos-pago-texto">
                            <span class="vpos-pago-titulo">Contado</span>
                            <span class="vpos-pago-desc">El cobro se registra como ingreso en Caja.</span>
                        </span>
                        <span class="vpos-pago-marca" aria-hidden="true"><x-ui.icon name="check" class="h-3.5 w-3.5" /></span>
                    </label>
                    <label class="vpos-pago-opcion" data-vpos-pago-descuento>
                        <input type="radio" name="forma_pago" value="DESCUENTO_PLANILLA" class="sr-only" @checked($formaPago === 'DESCUENTO_PLANILLA') @disabled($cliente !== 'PROVEEDOR' || ! $proveedorSel) data-vpos-pago>
                        <span class="vpos-pago-icono" aria-hidden="true"><x-ui.icon name="calendar-coin" class="h-5 w-5" /></span>
                        <span class="vpos-pago-texto">
                            <span class="vpos-pago-titulo">Descuento semanal</span>
                            <span class="vpos-pago-desc">El importe se descontará de la liquidación correspondiente del proveedor.</span>
                            <span class="vpos-pago-requisito" data-vpos-pago-requisito>Disponible solo al seleccionar un proveedor.</span>
                        </span>
                        <span class="vpos-pago-marca" aria-hidden="true"><x-ui.icon name="check" class="h-3.5 w-3.5" /></span>
                    </label>
                </div>

                <dl class="vpos-saldo" data-vpos-saldo hidden>
                    <div><dt>Saldo semanal estimado</dt><dd data-vpos-saldo-disponible>—</dd></div>
                    <div><dt>Esta compra</dt><dd data-vpos-saldo-compra>—</dd></div>
                    <div class="is-total"><dt>Saldo estimado después</dt><dd data-vpos-saldo-despues>—</dd></div>
                </dl>
                <p class="vpos-error-campo" data-vpos-saldo-error hidden></p>
            </fieldset>
        </div>

        @include('ventas.pos._venta-resumen')
    </form>

    <script type="application/json" data-vpos-catalogo>@json($catalogo)</script>
@endif
