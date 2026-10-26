{{--
    PRODUCTOS: consulta de productos terminados + gestión de sus precios. El producto nace desde su
    RECETA y su stock lo aumenta la PRODUCCIÓN; aquí no se crea ni se mueve stock. Los precios solo se
    ven dentro de la fila desplegable (nunca en la tabla). Ventas los aplica según el tipo de cliente y,
    al público, el mayorista desde la cantidad mínima configurada.
--}}
@extends('layouts.admin')

@section('title', 'Productos')
@section('body-class', 'admin-fichas-page')

@php
    $filas = collect($datos->filas);
    $filtrosActivos = request()->anyFilled(['q', 'estado']);
    $numero = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $soles = fn ($v) => 'S/ '.number_format((float) $v, 2);
    // Fila abierta al volver de guardar/eliminar precios o de un error de validación.
    $abiertoId = (int) (old('producto_id') ?? session('productoAbierto'));
    $conError = old('producto_id') !== null && $errors->any();
@endphp

@section('content')
    <div class="fichas recetas productos" data-productos-page>
        <header class="fichas-header">
            <div class="min-w-0">
                <x-layout.breadcrumbs :items="[['Inventario', null], ['Productos', null]]" />
                <h1 class="fichas-title">Productos</h1>
                <p class="fichas-subtitle">Consulta tus productos y administra sus precios de venta.</p>
            </div>
        </header>

        <form method="GET" action="{{ route('admin.productos.index') }}" class="productos-filtros">
            <label class="fichas-search">
                <span class="sr-only">Buscar producto</span>
                <x-ui.icon name="magnifying-glass" class="h-5 w-5" />
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por nombre o categoría..." autocomplete="off">
            </label>
            <div class="productos-estado">
                <x-forms.select name="estado" label="Estado" :sticky="false" :options="['DISPONIBLE' => 'Disponible', 'SIN_STOCK' => 'Sin stock']" :value="request('estado')" placeholder="Todos" />
            </div>
            <div class="fichas-filtros-acciones">
                <button type="submit" class="fichas-btn is-dark">Filtrar</button>
                @if ($filtrosActivos)
                    <a href="{{ route('admin.productos.index') }}" class="fichas-btn is-outline">Limpiar</a>
                @endif
            </div>
        </form>

        @if ($filas->isEmpty())
            <div class="fichas-vacio">
                <x-ui.icon name="package" class="h-6 w-6" />
                <div>
                    <p class="font-semibold text-text">{{ $filtrosActivos ? 'Ningún producto coincide con los filtros' : 'Todavía no hay productos' }}</p>
                    <p>{{ $filtrosActivos ? 'Prueba con otra búsqueda o limpia los filtros.' : 'Los productos se crean al guardar una receta en Producción → Recetas.' }}</p>
                </div>
            </div>
        @else
            <div class="recetas-tabla">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Producto</th>
                            <th scope="col" class="hidden md:table-cell">Categoría</th>
                            <th scope="col" class="hidden sm:table-cell">Presentación</th>
                            <th scope="col">Stock actual</th>
                            <th scope="col" class="is-centro">Estado</th>
                            <th scope="col" class="is-derecha">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($filas as $fila)
                            @php
                                $abierto = $fila['id'] === $abiertoId;
                                $precios = $fila['precios'];
                                $tienePrecios = $precios['PROVEEDOR'] !== null || $precios['PUBLICO'] !== null || $precios['MAYORISTA'] !== null;
                                $editando = $abierto && $conError;
                                $stock = $numero($fila['stockActual']).' '.($fila['unidadMedida'] === 'unidad' ? 'unidades' : $fila['unidadMedida']);
                                $detalleId = 'producto-'.$fila['id'];
                            @endphp
                            <tr @class(['recetas-fila', 'productos-fila', 'is-expanded' => $abierto]) data-producto-fila>
                                <td>
                                    <div class="recetas-producto">
                                        <x-ui.producto-imagen :url="$fila['imagenUrl']" :alt="$fila['nombre']" />
                                        <p class="recetas-nombre">{{ $fila['nombreBase'] }}</p>
                                    </div>
                                </td>
                                <td class="hidden text-text-muted md:table-cell">{{ $fila['categoria'] ?? '—' }}</td>
                                <td class="hidden whitespace-nowrap sm:table-cell">{{ $fila['presentacion'] ?? '—' }}</td>
                                <td class="whitespace-nowrap font-medium">{{ $stock }}</td>
                                <td class="is-centro">
                                    @if ($fila['estado'] === 'DISPONIBLE')
                                        <x-ui.badge compact status="DISPONIBLE">Disponible</x-ui.badge>
                                    @elseif ($fila['estado'] === 'SIN_STOCK')
                                        <x-ui.badge compact status="SIN_STOCK">Sin stock</x-ui.badge>
                                    @else
                                        <x-ui.badge compact status="INACTIVO">Inactivo</x-ui.badge>
                                    @endif
                                </td>
                                <td>
                                    <div class="recetas-acciones">
                                        <button type="button" class="recetas-icono productos-chevron" title="Ver producto" aria-label="Ver {{ $fila['nombre'] }}" data-toggle-row="{{ $detalleId }}" aria-expanded="{{ $abierto ? 'true' : 'false' }}" aria-controls="{{ $detalleId }}">
                                            <x-ui.icon name="chevron-down" class="h-[1.125rem] w-[1.125rem]" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr id="{{ $detalleId }}" class="recetas-detalle" @if (! $abierto) hidden @endif>
                                <td colspan="6">
                                    <div class="productos-ficha">
                                        {{-- Izquierda: el producto (solo consulta) --}}
                                        <div class="productos-ficha-producto">
                                            <x-ui.producto-imagen :url="$fila['imagenUrl']" :alt="$fila['nombre']" size="lg" />
                                            <div class="min-w-0">
                                                <p class="text-base font-semibold text-text">{{ $fila['nombreBase'] }}</p>
                                                <dl class="productos-datos">
                                                    <div><dt>Categoría</dt><dd>{{ $fila['categoria'] ?? '—' }}</dd></div>
                                                    <div><dt>Presentación</dt><dd>{{ $fila['presentacion'] ?? '—' }}</dd></div>
                                                    <div><dt>Stock actual</dt><dd>{{ $stock }}</dd></div>
                                                    <div>
                                                        <dt>Receta</dt>
                                                        <dd>
                                                            @if ($fila['recetaEstado'] === 'CONFIGURADA')
                                                                <x-ui.badge compact status="ACTIVA">Receta activa</x-ui.badge>
                                                            @else
                                                                <x-ui.badge compact status="SIN_RECETA">Sin receta activa</x-ui.badge>
                                                                @if ($fila['recetaEstado'] === 'SIN_RECETA')
                                                                    <a href="{{ route('admin.recetas.index', ['configurar' => $fila['id']]) }}" class="ml-1 text-xs font-medium text-primary hover:underline">Configurar receta</a>
                                                                @endif
                                                            @endif
                                                        </dd>
                                                    </div>
                                                </dl>
                                            </div>
                                        </div>

                                        {{-- Derecha: gestión de precios (consulta / edición) --}}
                                        <section class="productos-precios" data-precios data-modo="{{ $editando ? 'editar' : 'ver' }}" aria-labelledby="{{ $detalleId }}-precios">
                                            <h2 id="{{ $detalleId }}-precios" class="productos-precios-titulo">Gestión de precios</h2>

                                            <div class="productos-modo-ver">
                                                @if ($tienePrecios)
                                                    <dl class="productos-lista-precios">
                                                        <div><dt>Precio para proveedor</dt><dd>{{ $precios['PROVEEDOR'] !== null ? $soles($precios['PROVEEDOR']) : '—' }}</dd></div>
                                                        <div><dt>Precio para público</dt><dd>{{ $precios['PUBLICO'] !== null ? $soles($precios['PUBLICO']) : '—' }}</dd></div>
                                                        <div>
                                                            <dt>Precio para mayorista</dt>
                                                            <dd>
                                                                {{ $precios['MAYORISTA'] !== null ? $soles($precios['MAYORISTA']) : '—' }}
                                                                @if ($precios['cantidadMinimaMayorista'] !== null)
                                                                    <span class="productos-umbral">desde {{ $numero($precios['cantidadMinimaMayorista']) }} unidades</span>
                                                                @endif
                                                            </dd>
                                                        </div>
                                                    </dl>
                                                    <div class="productos-precios-acciones">
                                                        <button type="button" class="fichas-btn is-outline is-sm" data-precios-editar>
                                                            <x-ui.icon name="pencil" class="h-4 w-4" /> Editar precios
                                                        </button>
                                                        <form method="POST" action="{{ route('admin.producto-precios.destroy', $fila['id']) }}" data-confirm="¿Eliminar los precios de {{ $fila['nombre'] }}? No se podrá vender hasta configurarlos de nuevo.">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="fichas-btn is-outline is-sm productos-btn-eliminar">
                                                                <x-ui.icon name="trash" class="h-4 w-4" /> Eliminar precio
                                                            </button>
                                                        </form>
                                                    </div>
                                                @else
                                                    <div class="productos-sin-precios">
                                                        <p class="font-medium text-text">Sin precios configurados</p>
                                                        <p>Mientras no tenga precios, este producto no se puede vender.</p>
                                                        <button type="button" class="fichas-btn is-primary is-sm" data-precios-editar>
                                                            <x-ui.icon name="plus" class="h-4 w-4" /> Configurar precios
                                                        </button>
                                                    </div>
                                                @endif
                                            </div>

                                            <form method="POST" action="{{ route('admin.producto-precios.update', $fila['id']) }}" class="productos-modo-editar" novalidate>
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="producto_id" value="{{ $fila['id'] }}">
                                                <div class="productos-campos">
                                                    @foreach (['precio_proveedor' => ['Precio para proveedor (S/)', $precios['PROVEEDOR']], 'precio_publico' => ['Precio para público (S/)', $precios['PUBLICO']], 'precio_mayorista' => ['Precio para mayorista (S/)', $precios['MAYORISTA']]] as $campo => [$etiqueta, $valor])
                                                        <x-forms.input :name="$campo" :id="$detalleId.'-'.$campo" type="number" step="0.01" min="0.01" inputmode="decimal" :label="$etiqueta" :sticky="$editando" :value="$valor !== null ? number_format($valor, 2, '.', '') : null" placeholder="0.00" />
                                                    @endforeach
                                                    <x-forms.input name="cantidad_minima_mayorista" :id="$detalleId.'-cantidad-minima'" type="number" step="1" min="1" inputmode="numeric" label="Mayorista desde (unidades)" :sticky="$editando" :value="$precios['cantidadMinimaMayorista'] !== null ? $numero($precios['cantidadMinimaMayorista']) : null" placeholder="Ej. 10" />
                                                </div>
                                                <p class="productos-ayuda">Al público que compre esta cantidad o más se le cobra el precio mayorista. Deja un precio vacío para quitarlo.</p>
                                                <div class="productos-precios-acciones">
                                                    <button type="submit" class="fichas-btn is-primary is-sm">Guardar precios</button>
                                                    <button type="button" class="fichas-btn is-outline is-sm" data-precios-cancelar>Cancelar</button>
                                                </div>
                                            </form>
                                        </section>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
