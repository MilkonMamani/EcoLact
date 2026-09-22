{{--
    RECETAS: define cómo se fabrica cada producto. La receta es el ORIGEN del producto: al guardar una
    receta nueva se crea su producto (stock 0). La imagen vive en el PRODUCTO y aquí solo se muestra.
    Guardar una receta NUNCA descuenta insumos.
    Variables: $datos (RecetasListadoData), $editable (bool), $rutaIndex.
    Solo si $editable: $insumos, $tiposProducto, $unidadesPresentacion, $productoPorConfigurar.
--}}
@php
    $filas = collect($datos->filas);
    $cantidad = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
@endphp

<div
    class="space-y-6"
    @if ($editable)
        data-recetas-page
        data-insumos="{{ json_encode($insumos) }}"
        data-recetas-indice="{{ json_encode($filas->keyBy('id')) }}"
        data-url-store="{{ route('admin.recetas.store') }}"
        data-url-update="{{ route('admin.recetas.update', '__ID__') }}"
        @if ($productoPorConfigurar) data-configurar="{{ json_encode($productoPorConfigurar) }}" @endif
        @if ($errors->any() || session('error'))
            data-old="{{ json_encode(old()) }}"
        @endif
    @endif
>
    <x-layout.breadcrumbs :items="[['Producción', null], ['Recetas', null]]" />

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat-card label="Recetas activas" :value="$datos->kpis['activas']" accent="success" />
    </div>

    <x-ui.card title="Recetas" subtitle="Define cómo se fabrica cada producto.">
        @if ($editable)
            <x-slot:actions>
                <x-ui.button type="button" size="sm" data-receta-accion="crear">+ Nueva receta</x-ui.button>
            </x-slot:actions>
        @endif

        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
            @if (request('producto'))
                <input type="hidden" name="producto" value="{{ request('producto') }}">
            @endif
            <div class="w-64">
                <x-forms.input name="q" label="Buscar producto" value="{{ request('q') }}" :sticky="false" />
            </div>
            <div class="w-44">
                <x-forms.select name="estado" label="Estado" :options="['ACTIVA' => 'Activa', 'INACTIVA' => 'Inactiva']" :value="request('estado')" placeholder="Todas" :sticky="false" />
            </div>
            <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
            @if (request()->anyFilled(['q', 'estado', 'producto']))
                <x-ui.button :href="route($rutaIndex)" variant="ghost" size="sm" as="a">Limpiar</x-ui.button>
            @endif
        </form>

        @if ($filas->isEmpty())
            <x-ui.empty-state title="Todavía no hay recetas" description="Cree la primera receta: al guardarla se crea también su producto." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Imagen</th>
                        <th class="py-2 pr-4">Producto</th>
                        <th class="hidden py-2 pr-4 md:table-cell">Presentación</th>
                        <th class="py-2 pr-4">Receta</th>
                        <th class="hidden py-2 pr-4 lg:table-cell">Ingredientes</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="hidden py-2 pr-4 md:table-cell">Actualizada</th>
                        <th class="py-2 pl-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($filas as $fila)
                        <tr class="border-b border-text-muted/5">
                            <td class="py-3 pr-4"><x-ui.producto-imagen :url="$fila['imagenUrl']" :alt="$fila['producto']" /></td>
                            <td class="py-3 pr-4">
                                <p class="font-medium text-text">{{ $fila['productoNombreBase'] }}</p>
                                @if ($fila['tipo'])
                                    <p class="text-xs text-text-muted">{{ $fila['tipo'] }}</p>
                                @endif
                            </td>
                            <td class="hidden py-3 pr-4 whitespace-nowrap text-text md:table-cell">{{ $fila['presentacion'] ?? '—' }}</td>
                            <td class="py-3 pr-4 text-text">{{ $fila['nombre'] }} <span class="text-xs text-text-muted">v{{ $fila['version'] }}</span></td>
                            <td class="hidden py-3 pr-4 text-text-muted lg:table-cell">{{ count($fila['ingredientes']) }} {{ count($fila['ingredientes']) === 1 ? 'ingrediente' : 'ingredientes' }}</td>
                            <td class="py-3 pr-4"><x-ui.badge :status="$fila['estado']" /></td>
                            <td class="hidden py-3 pr-4 whitespace-nowrap text-text-muted md:table-cell">{{ $fila['actualizada'] }}</td>
                            <td class="py-3 pl-4">
                                <x-tables.actions>
                                    <x-ui.action-button type="view" label="Ver receta" :subject="$fila['nombre']" data-toggle-row="receta-{{ $fila['id'] }}" aria-expanded="false" aria-controls="receta-{{ $fila['id'] }}" />

                                    @if ($editable)
                                        <x-ui.action-button type="edit" label="Editar receta" :subject="$fila['nombre']" data-receta-accion="editar" data-receta="{{ json_encode($fila) }}" />

                                        @if ($fila['estado'] === 'ACTIVA')
                                            <form method="POST" action="{{ route('admin.recetas.desactivar', $fila['id']) }}" data-confirm="¿Desactivar esta receta? El producto no se podrá producir hasta activar una receta.">
                                                @csrf
                                                @method('PATCH')
                                                <x-ui.action-button type="deactivate" label="Desactivar receta" :subject="$fila['nombre']" submit />
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.recetas.activar', $fila['id']) }}">
                                                @csrf
                                                @method('PATCH')
                                                <x-ui.action-button type="activate" label="Activar receta" :subject="$fila['nombre']" submit />
                                            </form>
                                        @endif
                                    @endif
                                </x-tables.actions>
                            </td>
                        </tr>
                        <tr id="receta-{{ $fila['id'] }}" hidden class="border-b border-text-muted/10 bg-primary-soft/30">
                            <td colspan="8" class="px-4 py-4">
                                <div class="flex flex-col gap-5 rounded-lg border border-text-muted/15 bg-surface p-4 sm:flex-row">
                                    <x-ui.producto-imagen :url="$fila['imagenUrl']" :alt="$fila['producto']" size="lg" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs uppercase tracking-wide text-text-muted">{{ $fila['nombre'] }} · versión {{ $fila['version'] }}</p>
                                        <p class="mt-1 text-sm font-semibold text-text">
                                            Para producir {{ $cantidad($fila['rendimientoCantidad']) }} {{ $fila['rendimientoUnidad'] }} de {{ $fila['producto'] }} necesitas:
                                        </p>
                                        <table class="mt-2 w-full text-sm">
                                            <tbody>
                                                @foreach ($fila['ingredientes'] as $ingrediente)
                                                    <tr class="border-b border-text-muted/10 last:border-0">
                                                        <td class="py-1.5 pr-3 font-semibold text-text">{{ $cantidad($ingrediente['cantidad']) }} {{ $ingrediente['unidad'] }}</td>
                                                        <td class="py-1.5 pr-3 text-text">{{ $ingrediente['insumo'] }}</td>
                                                        <td class="py-1.5 text-text-muted">{{ $ingrediente['observacion'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                        @if ($fila['descripcion'])
                                            <p class="mt-3 text-sm text-text-muted">{{ $fila['descripcion'] }}</p>
                                        @endif
                                        @if ($fila['enUso'])
                                            <p class="mt-3 text-xs text-text-muted">Esta versión ya se usó en una producción: al editarla se crea una nueva versión y esta se conserva sin cambios.</p>
                                        @endif
                                        <p class="mt-3 text-xs text-text-muted">Guardar una receta no descuenta insumos. Los insumos se consumen únicamente al iniciar la producción.</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>

    @if ($editable)
        <x-ui.modal id="modal-receta" title="Nueva receta" size="xl">
            <form method="POST" action="{{ route('admin.recetas.store') }}" enctype="multipart/form-data" class="space-y-6" data-receta-form>
                @csrf
                <input type="hidden" name="_method" value="PATCH" data-metodo disabled>
                <input type="hidden" name="_modo" value="crear">
                <input type="hidden" name="_receta_id" value="">
                <input type="hidden" name="producto_existente_id" value="" data-producto-existente disabled>

                <div>
                    <h3 class="text-base font-semibold text-text" data-receta-titulo>Nueva receta</h3>
                    <p class="mt-1 text-sm text-text-muted" data-nota-modo>Define el producto y los ingredientes necesarios para producir una unidad base.</p>
                </div>

                <x-ui.alert variant="info">
                    <strong>Guardar esta receta no descuenta insumos.</strong>
                    Los insumos se consumen únicamente al iniciar la producción.
                </x-ui.alert>

                {{-- Información general --}}
                <section class="space-y-4">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-text-muted">Información general</h4>

                    <div data-datos-producto class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-forms.label for="tipo_producto" :required="true">Tipo de producto</x-forms.label>
                            <input id="tipo_producto" name="tipo_producto" list="tipos-producto" required autocomplete="off" placeholder="Queso, Yogurt, Mantequilla..." class="w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2 text-sm text-text shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                            <datalist id="tipos-producto">
                                @foreach ($tiposProducto as $tipo)
                                    <option value="{{ $tipo }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <x-forms.input name="nombre_producto" label="Nombre del producto" required placeholder="Queso Paria" :sticky="false" />
                        <x-forms.input name="presentacion_cantidad" type="number" step="any" min="0" label="Presentación" required placeholder="1" :sticky="false" />
                        <x-forms.select name="presentacion_unidad" label="Unidad de presentación" required :options="$unidadesPresentacion" placeholder="Elija la unidad" :sticky="false" />
                    </div>

                    <div class="hidden rounded-lg border border-text-muted/15 bg-primary-soft/40 p-3 text-sm" data-producto-fijo>
                        <p class="text-xs uppercase tracking-wide text-text-muted">Producto</p>
                        <p class="font-semibold text-text" data-producto-fijo-nombre></p>
                    </div>

                    <x-forms.textarea name="descripcion" label="Descripción" :rows="2" :sticky="false" />

                    <div>
                        <x-forms.label for="imagen" data-etiqueta-imagen>Imagen del producto</x-forms.label>
                        <div class="mt-1 flex flex-wrap items-center gap-4">
                            <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-dashed border-text-muted/30 bg-primary-soft/40 text-text-muted" data-imagen-caja>
                                <img alt="Previsualización de la imagen" class="hidden h-full w-full object-cover" data-imagen-vista>
                                <span class="px-2 text-center text-xs" data-imagen-vacia>Sin imagen</span>
                            </div>
                            <div class="min-w-0 flex-1 space-y-1">
                                <input id="imagen" name="imagen" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-text file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-primary-dark" data-imagen-input>
                                <p class="text-xs text-text-muted">Subir imagen · JPG, PNG o WEBP · máximo 2 MB. Se guarda una sola vez y se muestra también en Productos.</p>
                                <p class="text-xs text-danger" data-imagen-error role="alert"></p>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Resultado --}}
                <section class="rounded-lg border border-text-muted/15 bg-primary-soft/40 p-4" data-resultado aria-live="polite">
                    <p class="text-xs uppercase tracking-wide text-text-muted">Esta receta produce</p>
                    <p class="text-sm font-semibold text-text">1 unidad</p>
                    <p class="mt-2 text-xs uppercase tracking-wide text-text-muted">Resultado</p>
                    <p class="text-sm font-semibold text-text" data-resultado-texto>—</p>
                    <p class="mt-2 text-xs uppercase tracking-wide text-text-muted">Receta generada automáticamente</p>
                    <p class="text-sm font-semibold text-primary-dark" data-receta-nombre>—</p>
                </section>

                {{-- Ingredientes --}}
                <section class="space-y-3">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-text-muted">Ingredientes de la receta</h4>
                    <p class="text-xs text-text-muted">La leche se agrega como un insumo más. Solo se pueden elegir insumos existentes; si falta uno, créelo primero en Inventario → Insumos.</p>

                    <div class="hidden grid-cols-12 gap-2 text-xs uppercase tracking-wide text-text-muted sm:grid">
                        <span class="col-span-3">Insumo</span>
                        <span class="col-span-2">Cantidad</span>
                        <span class="col-span-2">Unidad</span>
                        <span class="col-span-4">Observación</span>
                        <span class="col-span-1 text-right">Acción</span>
                    </div>
                    <div class="space-y-3 sm:space-y-2" data-ingredientes></div>
                    <x-ui.button type="button" variant="secondary" size="sm" data-agregar-ingrediente>+ Agregar ingrediente</x-ui.button>
                </section>

                <div class="flex justify-end gap-2 border-t border-text-muted/10 pt-4">
                    <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                    <x-ui.button type="submit" data-guardar>Guardar receta</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
