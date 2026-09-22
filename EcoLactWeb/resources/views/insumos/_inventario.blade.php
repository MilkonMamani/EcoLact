{{--
    Inventario de insumos (ADMINISTRADOR y ENCARGADO_PLANTA). Toda la cantidad se guarda en la
    unidad base del insumo. La LECHE es un insumo del sistema: su stock sube solo con las
    entregas de los proveedores y no se puede desactivar.
    Variables: $insumos, $kpis, $movimientos (id => movimientos), $tipos, $unidades,
    $prefijo ('admin'|'planta'), $storeRoute, $updateRoute, $ajusteRoute, $compraRoute (nullable).
--}}
@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
    $modelo = \App\Infrastructure\Persistence\Models\InsumoModel::class;
@endphp

<div data-insumos-page data-url-update="{{ route($updateRoute, '__ID__') }}" @if (old('nombre') !== null && ($errors->any() || session('error'))) data-reabrir-nuevo @endif class="space-y-6">
<x-layout.breadcrumbs :items="[['Inventario', null], ['Insumos', null]]" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card label="Total de insumos" :value="$kpis['insumos']" accent="primary">
            <x-slot:icon><x-ui.icon name="archive-box" class="h-6 w-6" /></x-slot:icon>
        </x-ui.stat-card>
        <x-ui.stat-card label="Con stock" :value="$kpis['conStock']" accent="success">
            <x-slot:icon><x-ui.icon name="check-circle" class="h-6 w-6" /></x-slot:icon>
        </x-ui.stat-card>
        <x-ui.stat-card label="Stock bajo" :value="$kpis['stockBajo']" accent="warning" hint="Por debajo del mínimo">
            <x-slot:icon><x-ui.icon name="alert-circle" class="h-6 w-6" /></x-slot:icon>
        </x-ui.stat-card>
        <x-ui.stat-card label="Sin stock" :value="$kpis['sinStock']" accent="danger">
            <x-slot:icon><x-ui.icon name="x-circle" class="h-6 w-6" /></x-slot:icon>
        </x-ui.stat-card>
    </div>

    <p class="flex items-start gap-3 rounded-lg bg-info/10 px-4 py-3 text-sm text-primary-dark ring-1 ring-inset ring-info/20" role="note">
        <x-ui.icon name="droplet" class="mt-0.5 h-5 w-5 shrink-0" />
        <span>La leche se actualiza automáticamente con cada entrega de los proveedores y siempre aparece primero en la lista.</span>
    </p>

    <x-ui.card title="Insumos" subtitle="Materias primas, insumos y envases necesarios para producción.">
        <x-slot:actions>
            @can('create', $modelo)
                <x-ui.button type="button" size="sm" data-modal-open="modal-nuevo-insumo">+ Nuevo insumo</x-ui.button>
            @endcan
        </x-slot:actions>

        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
            <div class="w-56">
                <x-forms.input name="buscar" label="Buscar insumo" value="{{ request('buscar') }}" :sticky="false" />
            </div>
            <div class="w-44">
                <x-forms.select name="tipo" label="Tipo" :options="$tipos" :value="request('tipo')" placeholder="Todos" :sticky="false" />
            </div>
            <div class="w-44">
                <x-forms.select name="nivel" label="Nivel de stock" :options="['normal' => 'Normal', 'bajo' => 'Bajo el mínimo', 'sin_stock' => 'Sin stock']" :value="request('nivel')" placeholder="Todos" :sticky="false" />
            </div>
            <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
            @if (request()->anyFilled(['buscar', 'tipo', 'nivel']))
                <x-ui.button :href="route($prefijo.'.insumos.index')" variant="ghost" size="sm" as="a">Limpiar</x-ui.button>
            @endif
        </form>

        @if (count($insumos) === 0)
            <x-ui.empty-state title="No hay insumos para mostrar" description="Registre los insumos que usa producción (sal, cuajo, azúcar, envases...)." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Insumo</th>
                        <th class="py-2 pr-4">Tipo</th>
                        <th class="py-2 pr-4">Unidad</th>
                        <th class="py-2 pr-4">Stock actual</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pl-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($insumos as $insumo)
                        @php
                            $sinStock = $insumo->stockActual() <= 0.0;
                            $bajo = $insumo->stockMinimo() > 0.0 && $insumo->estaBajoMinimo() && ! $sinStock;
                            $datosEdicion = [
                                'id' => $insumo->id(),
                                'nombre' => $insumo->nombre(),
                                'descripcion' => $insumo->descripcion(),
                                'stock_minimo' => $insumo->stockMinimo(),
                                'tipo' => $insumo->tipo()->value,
                                'estado' => $insumo->estado(),
                                'del_sistema' => $insumo->esDelSistema(),
                                'unidad_etiqueta' => \App\Domain\Produccion\UnidadMedida::etiqueta($insumo->unidadBase()),
                            ];
                        @endphp
                        <tr class="border-b border-text-muted/5 {{ $insumo->esLeche() ? 'bg-info/10' : '' }}">
                            <td class="py-3 pr-4 text-text">
                                <span class="font-medium">{{ $insumo->nombre() }}</span>
                                @if ($insumo->esDelSistema())
                                    <span class="ml-1 rounded-full bg-info/10 px-2 py-0.5 text-xs font-medium text-info ring-1 ring-inset ring-info/20" title="Insumo del sistema: se alimenta solo con las entregas y no se puede desactivar.">Del sistema</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-text-muted">{{ $insumo->tipo()->etiqueta() }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $insumo->unidadBase() }}</td>
                            <td class="py-3 pr-4 whitespace-nowrap text-text">
                                <span class="font-medium">{{ $fmt($insumo->stockActual()) }} {{ $insumo->unidadBase() }}</span>
                                @if ($sinStock)
                                    <span class="ml-1 text-xs text-danger">Sin stock</span>
                                @elseif ($bajo)
                                    <span class="ml-1 text-xs text-warning" title="Mínimo: {{ $fmt($insumo->stockMinimo()) }} {{ $insumo->unidadBase() }}">Stock bajo</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4">
                                @if ($insumo->estado() !== 'ACTIVO')
                                    <x-ui.badge status="INACTIVO">Inactivo</x-ui.badge>
                                @elseif ($sinStock)
                                    <x-ui.badge variant="WARNING">Sin stock</x-ui.badge>
                                @else
                                    <x-ui.badge status="ACTIVO">Disponible</x-ui.badge>
                                @endif
                            </td>
                            <td class="py-3 pl-4">
                                <x-tables.actions>
                                    <x-ui.action-button type="view" label="Ver movimientos de" :subject="$insumo->nombre()" data-toggle-row="insumo-{{ $insumo->id() }}" aria-expanded="false" aria-controls="insumo-{{ $insumo->id() }}" />
                                    @can('create', $modelo)
                                        <x-ui.action-button type="edit" label="Editar" :subject="$insumo->nombre()" data-modal-open="modal-editar-insumo" data-insumo-editar data-insumo="{{ json_encode($datosEdicion) }}" />
                                    @endcan
                                    @if (! $insumo->esDelSistema())
                                        @can('create', $modelo)
                                            <form method="POST" action="{{ route($updateRoute, $insumo->id()) }}" @if ($insumo->estado() === 'ACTIVO') data-confirm="¿Desactivar {{ $insumo->nombre() }}? Ya no se podrá usar en recetas nuevas." @endif>
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="nombre" value="{{ $insumo->nombre() }}">
                                                <input type="hidden" name="descripcion" value="{{ $insumo->descripcion() }}">
                                                <input type="hidden" name="stock_minimo" value="{{ $insumo->stockMinimo() }}">
                                                <input type="hidden" name="tipo" value="{{ $insumo->tipo()->value }}">
                                                <input type="hidden" name="estado" value="{{ $insumo->estado() === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO' }}">
                                                @if ($insumo->estado() === 'ACTIVO')
                                                    <x-ui.action-button type="deactivate" label="Desactivar" :subject="$insumo->nombre()" submit />
                                                @else
                                                    <x-ui.action-button type="activate" label="Activar" :subject="$insumo->nombre()" submit />
                                                @endif
                                            </form>
                                        @endcan
                                    @endif
                                </x-tables.actions>
                            </td>
                        </tr>
                        <tr id="insumo-{{ $insumo->id() }}" hidden class="border-b border-text-muted/10 bg-primary-soft/30">
                            <td colspan="6" class="px-4 py-4">
                                <div class="grid gap-6 rounded-lg border border-text-muted/15 bg-surface p-4 lg:grid-cols-3">
                                    <div class="space-y-2 text-sm lg:col-span-2">
                                        <p class="text-xs uppercase tracking-wide text-text-muted">Últimos movimientos de {{ $insumo->nombre() }}</p>
                                        @if (count($movimientos[$insumo->id()] ?? []) === 0)
                                            <p class="text-text-muted">Todavía no tiene movimientos.</p>
                                        @else
                                            <ul class="divide-y divide-text-muted/10">
                                                @foreach ($movimientos[$insumo->id()] as $movimiento)
                                                    <li class="flex flex-wrap items-baseline justify-between gap-2 py-1.5">
                                                        <span class="text-text-muted">{{ $movimiento->fecha()->format('d/m/Y H:i') }} · {{ $movimiento->motivoEtiqueta() }}@if ($movimiento->observacion()) · {{ $movimiento->observacion() }}@endif</span>
                                                        <span class="font-semibold {{ $movimiento->esEntrada() ? 'text-success' : 'text-danger' }}">{{ $movimiento->esEntrada() ? '+' : '−' }}{{ $fmt($movimiento->cantidad()) }} {{ $insumo->unidadBase() }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                        @if ($insumo->descripcion())
                                            <p class="pt-2 text-text-muted">{{ $insumo->descripcion() }}</p>
                                        @endif
                                        <p class="text-xs text-text-muted">Stock mínimo: {{ $fmt($insumo->stockMinimo()) }} {{ $insumo->unidadBase() }}.</p>
                                    </div>

                                    <div class="space-y-4 text-sm">
                                        @if ($compraRoute && ! $insumo->esLeche())
                                            <form method="POST" action="{{ route($compraRoute, $insumo->id()) }}" class="space-y-2">
                                                @csrf
                                                <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Registrar compra</p>
                                                <div class="grid grid-cols-2 gap-2">
                                                    <x-forms.input name="cantidad" type="number" step="any" min="0" :id="'compra_cantidad_'.$insumo->id()" label="Cantidad" required :sticky="false" />
                                                    <x-forms.select name="unidad" :id="'compra_unidad_'.$insumo->id()" label="Unidad" :options="\App\Domain\Produccion\UnidadMedida::opcionesCompatiblesCon($insumo->unidadBase())" :value="$insumo->unidadBase()" :placeholder="false" :sticky="false" />
                                                </div>
                                                <x-ui.button type="submit" size="sm">Registrar compra</x-ui.button>
                                            </form>
                                        @elseif ($insumo->esLeche())
                                            <p class="rounded-lg bg-info/10 p-3 text-xs text-info">La leche no se compra: su stock aumenta automáticamente con cada entrega de los proveedores.</p>
                                        @endif

                                        @can('create', $modelo)
                                            <form method="POST" action="{{ route($ajusteRoute, $insumo->id()) }}" class="space-y-2">
                                                @csrf
                                                <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Ajustar stock (conteo físico)</p>
                                                <div class="grid grid-cols-2 gap-2">
                                                    <x-forms.select name="tipo" :id="'ajuste_tipo_'.$insumo->id()" label="Movimiento" :options="['ENTRADA' => 'Suma', 'SALIDA' => 'Resta']" value="ENTRADA" :placeholder="false" :sticky="false" />
                                                    <x-forms.input name="cantidad" type="number" step="any" min="0" :id="'ajuste_cantidad_'.$insumo->id()" :label="'Cantidad ('.$insumo->unidadBase().')'" required :sticky="false" />
                                                </div>
                                                <x-forms.input name="motivo" :id="'ajuste_motivo_'.$insumo->id()" label="Motivo" required maxlength="30" :sticky="false" />
                                                <x-ui.button type="submit" size="sm" variant="secondary">Registrar ajuste</x-ui.button>
                                            </form>
                                        @endcan
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>

    <x-ui.modal id="modal-nuevo-insumo" title="Nuevo insumo" size="lg">
        <form method="POST" action="{{ route($storeRoute) }}" class="space-y-5" data-insumo-nuevo>
            @csrf

            <section class="space-y-4">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-text-muted">Información del insumo</h3>

                <x-forms.input name="nombre" label="Nombre del insumo" required placeholder="Ej. Cuajo" maxlength="120" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-forms.select name="tipo" label="Tipo" :options="$tipos" value="INSUMO" :placeholder="false" required />
                    <x-forms.select name="unidad_base" label="Unidad base" :options="$unidades" :placeholder="false" value="ml" required data-unidad-base-select />
                </div>

                <x-forms.textarea name="descripcion" label="Descripción (opcional)" :rows="2" placeholder="Coagulante utilizado para quesos..." />
            </section>

            <hr class="border-text-muted/10">

            <section class="space-y-4">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-text-muted">Control de inventario</h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-forms.label for="stock_inicial">Stock inicial</x-forms.label>
                        <div class="flex items-center gap-2">
                            <input id="stock_inicial" name="stock_inicial" type="number" step="any" min="0" value="{{ old('stock_inicial') }}" placeholder="0" class="w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2 text-sm text-text shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary {{ $errors->has('stock_inicial') ? 'border-danger' : '' }}">
                            <span class="w-8 shrink-0 text-sm text-text-muted" data-sufijo-unidad>ml</span>
                        </div>
                        <x-forms.error name="stock_inicial" />
                    </div>
                    <div>
                        <x-forms.label for="stock_minimo" :required="true">Stock mínimo</x-forms.label>
                        <div class="flex items-center gap-2">
                            <input id="stock_minimo" name="stock_minimo" type="number" step="any" min="0" required value="{{ old('stock_minimo', 0) }}" class="w-full rounded-lg border border-text-muted/25 bg-surface px-3 py-2 text-sm text-text shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary {{ $errors->has('stock_minimo') ? 'border-danger' : '' }}">
                            <span class="w-8 shrink-0 text-sm text-text-muted" data-sufijo-unidad>ml</span>
                        </div>
                        <x-forms.error name="stock_minimo" />
                    </div>
                </div>

                <p class="flex items-start gap-2 rounded-lg bg-info/10 px-3 py-2 text-xs text-primary-dark">
                    <x-ui.icon name="alert-circle" class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>Se mostrará “Stock bajo” cuando el stock llegue al mínimo configurado. Todo el stock se controla en la unidad base; las recetas pueden usar otra unidad compatible y se convierte sola.</span>
                </p>
            </section>

            <div class="flex justify-end gap-2 border-t border-text-muted/10 pt-4">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar insumo</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal id="modal-editar-insumo" title="Editar insumo">
        <form method="POST" action="#" class="space-y-4">
            @csrf
            @method('PATCH')
            <x-forms.input name="nombre" label="Nombre" required :sticky="false" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-forms.select name="tipo" label="Tipo" :options="$tipos" :placeholder="false" :sticky="false" />
                <x-forms.select name="estado" label="Estado" :options="['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo']" :placeholder="false" :sticky="false" />
            </div>
            <p class="-mt-2 text-xs text-info" data-aviso-sistema hidden>Este es un insumo del sistema: no se puede cambiar su tipo ni desactivarlo.</p>
            <p class="text-xs text-text-muted">Unidad base: <strong data-unidad-base></strong> (no se puede cambiar: cambiarla reinterpretaría todo el historial).</p>
            <x-forms.textarea name="descripcion" label="Descripción (opcional)" :rows="2" :sticky="false" />
            <x-forms.input name="stock_minimo" type="number" step="any" min="0" label="Stock mínimo" required :sticky="false" />
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar cambios</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
