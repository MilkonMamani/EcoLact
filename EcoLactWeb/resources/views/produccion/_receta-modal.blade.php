{{--
    Modal único de Nueva / Editar receta (recetas.js cambia textos y campos según el modo).
    Estructura: PRODUCTO → FÓRMULA → INGREDIENTES → GUARDAR. Cabecera y pie fijos; solo el cuerpo hace scroll.
    En Editar el producto no se modifica (el servidor lo prohíbe): se muestra como información fija.
    Variables: $tiposProducto, $unidadesPresentacion.
--}}
<x-ui.modal id="modal-receta" size="receta" flush labelledby="modal-receta-titulo">
    <form method="POST" action="{{ route('admin.recetas.store') }}" enctype="multipart/form-data" class="recetas-modal" data-receta-form>
        @csrf
        <input type="hidden" name="_method" value="PATCH" data-metodo disabled>
        <input type="hidden" name="_modo" value="crear">
        <input type="hidden" name="_receta_id" value="">
        <input type="hidden" name="producto_existente_id" value="" data-producto-existente disabled>

        <header class="recetas-modal-cabecera">
            <div class="min-w-0">
                <h2 id="modal-receta-titulo" class="text-lg font-bold text-text" data-receta-titulo>Nueva receta</h2>
                <p class="mt-0.5 text-sm text-text-muted" data-nota-modo>Define el producto y su fórmula de producción.</p>
            </div>
            <button type="button" data-modal-close class="recetas-icono" aria-label="Cerrar" title="Cerrar">
                <x-ui.icon name="x-mark" class="h-5 w-5" />
            </button>
        </header>

        <div class="recetas-modal-cuerpo" data-modal-cuerpo>
            @if (session('error'))
                <p class="recetas-error-general" role="alert" data-error-servidor>{{ session('error') }}</p>
            @endif

            {{-- PRODUCTO --}}
            <section class="recetas-bloque" aria-labelledby="receta-seccion-producto">
                <h3 id="receta-seccion-producto" class="recetas-seccion">Información del producto</h3>

                <div data-datos-producto class="recetas-campos">
                    <div>
                        <x-forms.label for="tipo_producto" :required="true">Tipo de producto</x-forms.label>
                        <input id="tipo_producto" name="tipo_producto" list="tipos-producto" required autocomplete="off" placeholder="Queso, Yogurt, Mantequilla..." @class(['w-full rounded-lg border bg-surface px-3 py-2 text-sm text-text shadow-sm placeholder:text-text-muted/60 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary', 'border-danger' => $errors->has('tipo_producto'), 'border-text-muted/25' => ! $errors->has('tipo_producto')])>
                        <datalist id="tipos-producto">
                            @foreach ($tiposProducto as $tipo)
                                <option value="{{ $tipo }}"></option>
                            @endforeach
                        </datalist>
                        <x-forms.error name="tipo_producto" />
                    </div>
                    <x-forms.input name="nombre_producto" label="Nombre" required placeholder="Queso Paria" maxlength="100" />
                    <x-forms.input name="presentacion_cantidad" type="number" step="any" min="0" label="Presentación" required placeholder="1" />
                    <x-forms.select name="presentacion_unidad" label="Unidad de presentación" required :options="$unidadesPresentacion" placeholder="Elija la unidad" />
                </div>

                <dl class="recetas-producto-fijo" data-producto-fijo hidden>
                    <div data-fijo="tipo"><dt>Tipo</dt><dd></dd></div>
                    <div data-fijo="nombre"><dt>Nombre</dt><dd></dd></div>
                    <div data-fijo="presentacion"><dt>Presentación</dt><dd></dd></div>
                </dl>

                <x-forms.textarea name="descripcion" label="Descripción" :rows="2" placeholder="Opcional" />

                <div class="recetas-imagen">
                    <div class="recetas-imagen-vista" data-imagen-caja>
                        <img alt="Imagen del producto" class="hidden" data-imagen-vista>
                        <svg class="h-7 w-7" data-imagen-vacia viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.16-5.16a2.25 2.25 0 0 1 3.18 0l5.16 5.16m-1.5-1.5 1.41-1.41a2.25 2.25 0 0 1 3.18 0l2.25 2.25M3.75 19.5h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Z" /></svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-text" data-etiqueta-imagen>Imagen del producto</p>
                        <input id="imagen" name="imagen" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" data-imagen-input>
                        <label for="imagen" class="fichas-btn is-outline is-sm mt-1.5" data-imagen-boton>Seleccionar imagen</label>
                        <p class="mt-1 text-xs text-text-muted">JPG, PNG o WEBP · máximo 2 MB</p>
                        <p class="text-xs text-danger" role="alert" data-imagen-error>{{ $errors->first('imagen') }}</p>
                    </div>
                </div>
            </section>

            {{-- FÓRMULA E INGREDIENTES --}}
            <section class="recetas-bloque" aria-labelledby="receta-seccion-formula">
                <div>
                    <h3 id="receta-seccion-formula" class="recetas-seccion">Fórmula de producción</h3>
                    <p class="recetas-formula-producto" data-formula-producto>Completa el producto y la presentación para definir la fórmula.</p>
                    <p class="mt-0.5 text-sm text-text-muted" data-formula-ayuda>Define los insumos necesarios para producir una unidad.</p>
                </div>

                <div>
                    <div class="recetas-ingredientes-cabecera" aria-hidden="true">
                        <span>Insumo</span>
                        <span>Cantidad</span>
                        <span>Unidad</span>
                        <span>Observación</span>
                        <span class="text-center">Acción</span>
                    </div>
                    <div class="recetas-ingredientes" data-ingredientes></div>
                    <p class="mt-1.5 text-xs text-danger" role="alert" data-ingredientes-error>{{ $errors->first('ingredientes') }}</p>
                </div>

                <div>
                    <button type="button" class="fichas-btn is-outline is-sm" data-agregar-ingrediente>
                        <x-ui.icon name="plus" class="h-4 w-4" /> Agregar ingrediente
                    </button>
                    <p class="mt-2 text-xs text-text-muted">¿No encuentras un insumo?<br>Créalo desde Inventario &gt; Insumos.</p>
                </div>
            </section>
        </div>

        <footer class="recetas-modal-pie">
            <p class="recetas-nota-inventario">
                <x-ui.icon name="info" class="mt-px h-4 w-4 shrink-0" />
                <span><span data-nota-inventario>Guardar una receta no descuenta inventario.</span> Los insumos se consumen al iniciar una producción.</span>
            </p>
            <div class="flex shrink-0 justify-end gap-2">
                <button type="button" class="fichas-btn is-outline" data-modal-close>Cancelar</button>
                <button type="submit" class="fichas-btn is-primary" data-guardar>Guardar receta</button>
            </div>
        </footer>
    </form>
</x-ui.modal>
