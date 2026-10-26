{{--
    RECETAS: define cómo se fabrica cada producto. La receta es el ORIGEN del producto: al guardar una
    receta nueva se crea su producto (stock 0). La imagen vive en el PRODUCTO y aquí solo se muestra.
    Guardar una receta NUNCA descuenta insumos. Cada producto muestra UNA receta (la vigente); el
    historial de versiones se conserva en el backend para las producciones, pero no se expone aquí.
    Variables: $datos (RecetasListadoData), $editable (bool), $rutaIndex.
    Solo si $editable: $insumos, $tiposProducto, $unidadesPresentacion, $productoPorConfigurar.
--}}
@php
    $filas = collect($datos->filas);
    $cantidad = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
@endphp

<div
    class="fichas recetas"
    @if ($editable)
        data-recetas-page
        data-insumos="{{ json_encode($insumos) }}"
        data-recetas-indice="{{ json_encode($filas->keyBy('id')) }}"
        data-url-store="{{ route('admin.recetas.store') }}"
        data-url-update="{{ route('admin.recetas.update', '__ID__') }}"
        data-errores="{{ json_encode($errors->getMessages()) }}"
        @if ($productoPorConfigurar) data-configurar="{{ json_encode($productoPorConfigurar) }}" @endif
        @if ($errors->any() || session('error'))
            data-old="{{ json_encode(old()) }}"
        @endif
    @endif
>
    <header class="fichas-header">
        <div class="min-w-0">
            <x-layout.breadcrumbs :items="[['Producción', null], ['Recetas', null]]" />
            <h1 class="fichas-title">Recetas</h1>
            <p class="fichas-subtitle">Define cómo se fabrica cada producto.</p>
        </div>
        @if ($editable)
            <button type="button" class="fichas-btn is-primary" data-receta-accion="crear">
                <x-ui.icon name="plus" class="h-4 w-4" /> Nueva receta
            </button>
        @endif
    </header>

    @if ($filas->isEmpty())
        <x-ui.empty-state class="mt-6 bg-surface" title="No hay recetas registradas." description="Crea una receta para definir cómo se fabrica un producto.">
            @if ($editable)
                <x-slot:action>
                    <button type="button" class="fichas-btn is-primary" data-receta-accion="crear">
                        <x-ui.icon name="plus" class="h-4 w-4" /> Nueva receta
                    </button>
                </x-slot:action>
            @endif
        </x-ui.empty-state>
    @else
        <div class="recetas-tabla">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Producto</th>
                        <th scope="col">Presentación</th>
                        <th scope="col" class="hidden md:table-cell">Ingredientes</th>
                        <th scope="col" class="is-centro">Estado</th>
                        <th scope="col" class="hidden lg:table-cell">Actualizada</th>
                        <th scope="col" class="is-derecha">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($filas as $fila)
                        @php
                            $nombreProducto = $fila['productoNombreBase'];
                            $totalIngredientes = count($fila['ingredientes']);
                            $activa = $fila['estado'] === 'ACTIVA';
                        @endphp
                        <tr class="recetas-fila">
                            <td>
                                <div class="recetas-producto">
                                    <x-ui.producto-imagen :url="$fila['imagenUrl']" :alt="$fila['producto']" />
                                    <div class="min-w-0">
                                        <p class="recetas-nombre">{{ $nombreProducto }}</p>
                                        @if ($fila['tipo'])
                                            <p class="recetas-tipo">{{ $fila['tipo'] }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap">{{ $fila['presentacion'] ?? '—' }}</td>
                            <td class="hidden whitespace-nowrap text-text-muted md:table-cell">{{ $totalIngredientes }} {{ $totalIngredientes === 1 ? 'ingrediente' : 'ingredientes' }}</td>
                            <td class="is-centro">
                                <x-ui.badge compact :status="$fila['estado']">{{ $activa ? 'Receta activa' : 'Receta inactiva' }}</x-ui.badge>
                            </td>
                            <td class="hidden whitespace-nowrap text-text-muted lg:table-cell">{{ $fila['actualizada'] }}</td>
                            <td>
                                <div class="recetas-acciones">
                                    <button type="button" class="recetas-icono" title="Ver receta" aria-label="Ver receta de {{ $fila['producto'] }}" data-toggle-row="receta-{{ $fila['id'] }}" aria-expanded="false" aria-controls="receta-{{ $fila['id'] }}">
                                        <x-ui.icon name="eye" class="h-[1.125rem] w-[1.125rem]" />
                                    </button>

                                    @if ($editable)
                                        <button type="button" class="recetas-icono" title="Editar receta" aria-label="Editar receta de {{ $fila['producto'] }}" data-receta-accion="editar" data-receta="{{ json_encode($fila) }}">
                                            <x-ui.icon name="pencil" class="h-[1.125rem] w-[1.125rem]" />
                                        </button>

                                        <details class="asistencia-acciones" data-recetas-menu>
                                            <summary title="Más acciones" aria-label="Más acciones para {{ $fila['producto'] }}">
                                                <x-ui.icon name="ellipsis" class="h-4 w-4 rotate-90" />
                                            </summary>
                                            <div class="asistencia-menu" role="menu">
                                                @if ($activa)
                                                    <form method="POST" action="{{ route('admin.recetas.desactivar', $fila['id']) }}" data-confirm="¿Desactivar esta receta? El producto no se podrá producir hasta activarla.">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" role="menuitem" class="text-danger">
                                                            <x-ui.icon name="minus-circle" class="h-4 w-4" /> Desactivar receta
                                                        </button>
                                                    </form>
                                                @else
                                                    <form method="POST" action="{{ route('admin.recetas.activar', $fila['id']) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" role="menuitem">
                                                            <x-ui.icon name="check-circle" class="h-4 w-4" /> Activar receta
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </details>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        <tr id="receta-{{ $fila['id'] }}" class="recetas-detalle" hidden>
                            <td colspan="6">
                                <div class="recetas-ficha">
                                    <div class="recetas-ficha-producto">
                                        <x-ui.producto-imagen :url="$fila['imagenUrl']" :alt="$fila['producto']" size="lg" />
                                        <div>
                                            <p class="text-base font-semibold text-text">{{ $nombreProducto }}</p>
                                            @if ($fila['tipo'])
                                                <p class="recetas-tipo">{{ $fila['tipo'] }}</p>
                                            @endif
                                            @if ($fila['presentacion'])
                                                <p class="mt-3 text-sm text-text"><span class="text-text-muted">Presentación:</span> {{ $fila['presentacion'] }}</p>
                                            @endif
                                            @if ($fila['descripcion'])
                                                <p class="recetas-etiqueta mt-3">Descripción</p>
                                                <p class="text-sm text-text">{{ $fila['descripcion'] }}</p>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="recetas-ficha-formula">
                                        <p class="text-sm font-semibold text-text">
                                            Fórmula para producir {{ $cantidad($fila['rendimientoCantidad']) }} {{ $fila['rendimientoUnidad'] }} de {{ $fila['presentacion'] ?? $fila['producto'] }}
                                        </p>
                                        <table class="recetas-formula">
                                            <thead>
                                                <tr>
                                                    <th scope="col">Ingrediente</th>
                                                    <th scope="col" class="is-derecha">Cantidad</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($fila['ingredientes'] as $ingrediente)
                                                    <tr>
                                                        <td>
                                                            {{ $ingrediente['insumo'] }}
                                                            @if ($ingrediente['observacion'])
                                                                <span class="recetas-observacion">{{ $ingrediente['observacion'] }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="is-derecha whitespace-nowrap font-medium">{{ $cantidad($ingrediente['cantidad']) }} {{ $ingrediente['unidad'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                        <p class="recetas-nota">Guardar o editar una receta no descuenta insumos. Los insumos se consumen al iniciar una producción.</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($editable)
        @include('produccion._receta-modal')
    @endif
</div>
