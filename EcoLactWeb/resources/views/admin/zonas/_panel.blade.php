{{--
    Panel derecho de Zonas: se pinta en la carga inicial (index) y como fragmento
    (admin.zonas.panel / admin.zonas.panel.sin-asignar) al elegir otra fila o tras el switch.
    Mismo patrón que Acopiadores: cabecera mínima, pestañas Resumen / Proveedores / Acopiadores
    y la edición solo desde la sección dueña del dato (Resumen). El mapa vive en el Resumen.

    @var ?\App\Application\Zona\ZonaPanelData $panel
    @var ?list<\App\Application\Zona\ProveedorAsignadoFila> $proveedoresSinZona
--}}
@if ($panel)
    @php
        $pestanas = ['resumen' => 'Resumen', 'proveedores' => 'Proveedores', 'acopiadores' => 'Acopiadores'];
        $modalEditar = 'modal-editar-zona-'.$panel->id;
        $modalRuta = 'modal-nueva-ruta-'.$panel->id;
        $modalActivo = old('_modal');
        $rutasActivas = $panel->rutasActivas();
    @endphp

    <div class="fichas-panel-contenido" data-fichas-contenido data-ficha-id="{{ $panel->id }}" data-estado="{{ $panel->estado }}" data-zona-id="{{ $panel->id }}">
        <header class="fichas-panel-head">
            <span class="fichas-avatar" aria-hidden="true"><x-ui.icon name="map-pin" class="h-6 w-6" /></span>
            <div class="min-w-0 flex-1">
                <x-ui.badge :status="$panel->estado">{{ $panel->estaActiva() ? 'Activa' : 'Inactiva' }}</x-ui.badge>
                <h2 class="fichas-panel-nombre">{{ $panel->nombre }}</h2>
                <p class="fichas-panel-dni">{{ $panel->descripcion ?? 'Sin descripción' }}</p>
            </div>
            <button type="button" class="fichas-cerrar" data-fichas-cerrar aria-label="Cerrar panel" title="Cerrar panel">
                <x-ui.icon name="x-mark" class="h-5 w-5" />
            </button>
        </header>

        <x-fichas.panel-tabs :pestanas="$pestanas" etiqueta="Información de la zona" />

        <div class="fichas-panel-cuerpo">
            {{-- ------------------------------------------------------------ Resumen --}}
            <section id="ficha-panel-resumen" role="tabpanel" aria-labelledby="ficha-tab-resumen" data-fichas-tab-panel="resumen">
                <div class="fichas-seccion is-primera">
                    <div class="fichas-seccion-head">
                        <h3>Información de la zona</h3>
                        <div class="flex items-center gap-1.5">
                            <button type="button" class="fichas-editar" data-modal-open="{{ $modalEditar }}" aria-label="Editar zona {{ $panel->nombre }}">
                                <x-ui.icon name="pencil" class="h-3.5 w-3.5" /> Editar
                            </button>
                            <details class="asistencia-acciones">
                                <summary aria-label="Más acciones para la zona {{ $panel->nombre }}" title="Más acciones">
                                    <x-ui.icon name="ellipsis" class="h-4 w-4 rotate-90" />
                                </summary>
                                <div class="asistencia-menu" role="menu">
                                    <form method="POST" action="{{ route('admin.zonas.update', $panel->id) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="estado" value="{{ $panel->estaActiva() ? 'INACTIVO' : 'ACTIVO' }}">
                                        <button type="submit" role="menuitem">
                                            <x-ui.icon name="{{ $panel->estaActiva() ? 'x-mark' : 'check' }}" class="h-4 w-4" /> {{ $panel->estaActiva() ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </form>
                                        <form method="POST" action="{{ route('admin.zonas.destroy', $panel->id) }}" data-confirm="¿Eliminar la zona {{ $panel->nombre }}?&#10;&#10;Esta acción eliminará permanentemente la zona. Solo es posible si no tiene rutas, proveedores, acopiadores ni historial asociados.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" role="menuitem" class="text-danger" aria-label="Eliminar zona {{ $panel->nombre }}">
                                                <x-ui.icon name="trash" class="h-4 w-4" /> Eliminar
                                            </button>
                                        </form>
                                </div>
                            </details>
                        </div>
                    </div>
                    <dl class="fichas-datos">
                        <div><dt>Nombre</dt><dd>{{ $panel->nombre }}</dd></div>
                        <div><dt>Estado</dt><dd>{{ $panel->estaActiva() ? 'Activa' : 'Inactiva' }}</dd></div>
                        <div class="is-ancho"><dt>Descripción</dt><dd @class(['is-muted' => ! $panel->descripcion])>{{ $panel->descripcion ?? 'Sin descripción' }}</dd></div>
                        <div><dt>Creada el</dt><dd @class(['is-muted' => ! $panel->creadaEl])>{{ $panel->creadaEl ?? 'Sin registrar' }}</dd></div>
                    </dl>
                    @if ($panel->estaActiva() && (count($panel->proveedores) > 0 || count($panel->acopiadores) > 0))
                        <p class="fichas-nota">Para desactivarla, primero reasigna sus proveedores y acopiadores activos a otra zona.</p>
                    @endif
                </div>

                <dl class="fichas-resumen">
                    <div><dt>Proveedores</dt><dd class="is-num">{{ count($panel->proveedores) }}</dd></div>
                    <div><dt>Acopiadores</dt><dd class="is-num">{{ count($panel->acopiadores) }}</dd></div>
                    <div><dt>Litros (semana)</dt><dd class="is-num">{{ number_format($panel->litrosSemana, 0) }} L</dd></div>
                </dl>

                <div class="fichas-seccion">
                    <div class="fichas-seccion-head">
                        <h3>Rutas</h3>
                        <button type="button" class="fichas-editar" data-modal-open="{{ $modalRuta }}">
                            <x-ui.icon name="plus" class="h-3.5 w-3.5" /> Nueva ruta
                        </button>
                    </div>
                    @if ($panel->rutas === [])
                        <p class="zonas-rutas-resumen">Esta zona todavía no tiene rutas.</p>
                    @else
                        <p class="zonas-rutas-resumen">{{ count($rutasActivas) }} {{ count($rutasActivas) === 1 ? 'ruta activa' : 'rutas activas' }}</p>
                        <ul class="zonas-rutas">
                            @foreach ($panel->rutas as $ruta)
                                <li>
                                    <span @class(['is-muted' => $ruta['estado'] !== 'ACTIVO'])>{{ $ruta['nombre'] }}@if ($ruta['estado'] !== 'ACTIVO') · inactiva @endif</span>
                                    <a href="{{ route('admin.rutas.show', $ruta['id']) }}">Gestionar ruta <x-ui.icon name="chevron-right" class="h-3.5 w-3.5" /></a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                @include('admin.zonas._mapa', ['panel' => $panel])
            </section>

            {{-- ------------------------------------------------------------ Proveedores --}}
            <section id="ficha-panel-proveedores" role="tabpanel" aria-labelledby="ficha-tab-proveedores" data-fichas-tab-panel="proveedores" hidden>
                <div class="zonas-proveedores-barra">
                    <label class="zonas-proveedores-buscar">
                        <span class="sr-only">Buscar proveedor por nombre o DNI</span>
                        <x-ui.icon name="magnifying-glass" class="h-4 w-4" />
                        <input type="search" placeholder="Buscar proveedor por nombre o DNI..." autocomplete="off" data-zona-proveedores-filtro>
                    </label>
                    @if ($panel->estaActiva())
                        <button type="button" class="fichas-btn is-primary is-sm" data-modal-open="modal-buscar-proveedor">
                            <x-ui.icon name="plus" class="h-4 w-4" /> Asignar proveedor
                        </button>
                    @endif
                </div>
                @include('admin.zonas._tabla-proveedores', ['proveedores' => $panel->proveedores, 'esSinAsignar' => false])
            </section>

            {{-- ------------------------------------------------------------ Acopiadores --}}
            <section id="ficha-panel-acopiadores" role="tabpanel" aria-labelledby="ficha-tab-acopiadores" data-fichas-tab-panel="acopiadores" hidden>
                @if ($panel->acopiadores === [])
                    <p class="fichas-panel-sin-datos">Esta zona no tiene acopiadores asignados.</p>
                @else
                    <div class="fichas-mini-tabla">
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">Acopiador</th>
                                    <th scope="col">DNI</th>
                                    <th scope="col">Teléfono</th>
                                    <th scope="col">Estado</th>
                                    <th scope="col" class="is-num">Entregas (semana)</th>
                                    <th scope="col" class="is-num">Litros (semana)</th>
                                    <th scope="col" class="is-accion"><span class="sr-only">Ver</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($panel->acopiadores as $acopiador)
                                    <tr>
                                        <td class="fichas-mini-producto font-semibold" title="{{ $acopiador['nombre'] }}">{{ $acopiador['nombre'] }}</td>
                                        <td class="is-muted">{{ $acopiador['dni'] }}</td>
                                        <td class="is-muted">{{ $acopiador['telefono'] ?? '—' }}</td>
                                        <td><x-ui.badge compact :status="$acopiador['estado']">{{ $acopiador['estado'] === 'ACTIVO' ? 'Activo' : 'Inactivo' }}</x-ui.badge></td>
                                        <td class="is-num">{{ $acopiador['entregasSemana'] }}</td>
                                        <td class="is-num">{{ number_format($acopiador['litrosSemana'], 0) }} L</td>
                                        <td class="is-accion"><a href="{{ route('admin.acopiadores.index', ['acopiador' => $acopiador['id']]) }}" class="fichas-ver" aria-label="Ver acopiador {{ $acopiador['nombre'] }}" title="Ver acopiador"><x-ui.icon name="chevron-right" class="h-4 w-4" /></a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>

    {{-- Formularios de la zona seleccionada (fuera del contenido animado para que el modal quede fijo a la ventana). --}}
    <x-ui.modal :id="$modalEditar" title="Editar zona" :open="$errors->any() && $modalActivo === $modalEditar">
        <form method="POST" action="{{ route('admin.zonas.update', $panel->id) }}" class="space-y-4">
            @csrf
            @method('PATCH')
            <input type="hidden" name="_modal" value="{{ $modalEditar }}">
            <x-forms.input name="nombre" :id="$modalEditar.'-nombre'" :sticky="$modalActivo === $modalEditar" label="Nombre" :value="$panel->nombre" required />
            <x-forms.textarea name="descripcion" :id="$modalEditar.'-descripcion'" :sticky="$modalActivo === $modalEditar" label="Descripción" :value="$panel->descripcion" :rows="3" />

            {{--
                Delimitación operativa: los campos van deshabilitados (no se envían) hasta pulsar
                "Editar delimitación", así guardar solo el nombre nunca pisa el polígono. Tras un error
                de validación del polígono, el editor vuelve abierto con lo que se había dibujado.
            --}}
            @php
                $reabrirDelimitacion = $modalActivo === $modalEditar && (old('color_mapa') !== null || $errors->hasAny(['poligono_geojson', 'color_mapa']));
                $geojsonActual = $reabrirDelimitacion ? old('poligono_geojson') : ($panel->delimitacion ? json_encode($panel->delimitacion->aGeoJson()) : '');
            @endphp
            <div class="zonas-delimitacion" data-delimitacion data-delimitacion-zona="{{ $panel->id }}" data-delimitacion-abierta="{{ $reabrirDelimitacion ? 'true' : 'false' }}">
                <div class="zonas-delimitacion-head">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-text">Delimitación operativa</p>
                        <p class="text-xs text-text-muted" data-delimitacion-resumen>{{ $panel->delimitacion ? count($panel->delimitacion->vertices).' puntos dibujados' : 'Delimitación pendiente' }}</p>
                    </div>
                    <button type="button" class="fichas-editar" data-delimitacion-activar @if ($reabrirDelimitacion) hidden @endif>
                        <x-ui.icon name="map" class="h-3.5 w-3.5" /> Editar delimitación
                    </button>
                </div>

                <div class="zonas-delimitacion-editor" data-delimitacion-editor @if (! $reabrirDelimitacion) hidden @endif>
                    <div class="zonas-delimitacion-barra" role="toolbar" aria-label="Herramientas de delimitación">
                        <button type="button" class="fichas-btn is-outline is-sm" data-delimitacion-modo="dibujar" aria-pressed="false">
                            <x-ui.icon name="pen-line" class="h-3.5 w-3.5" /> Dibujar delimitación
                        </button>
                        <button type="button" class="fichas-btn is-outline is-sm" data-delimitacion-modo="editar" aria-pressed="false">
                            <x-ui.icon name="pencil" class="h-3.5 w-3.5" /> Editar vértices
                        </button>
                        <button type="button" class="fichas-btn is-outline is-sm" data-delimitacion-finalizar hidden disabled>
                            <x-ui.icon name="check" class="h-3.5 w-3.5" /> Finalizar delimitación
                        </button>
                        <button type="button" class="fichas-btn is-outline is-sm" data-delimitacion-deshacer>Deshacer punto</button>
                        <button type="button" class="fichas-btn is-outline is-sm" data-delimitacion-borrar>
                            <x-ui.icon name="trash" class="h-3.5 w-3.5" /> Borrar delimitación
                        </button>
                        <button type="submit" class="fichas-btn is-primary is-sm ml-auto">Guardar</button>
                    </div>
                    <p class="text-xs text-text-muted" data-delimitacion-ayuda></p>
                    <div class="zonas-delimitacion-mapa" data-delimitacion-mapa></div>
                    <div class="zonas-delimitacion-barra">
                        <label class="zonas-delimitacion-color">
                            <input type="color" name="color_mapa" value="{{ old('color_mapa', $panel->colorMapa) }}" data-delimitacion-color @disabled(! $reabrirDelimitacion)>
                            Color en el mapa
                        </label>
                        <span class="text-xs text-text-muted" data-delimitacion-puntos></span>
                        <span class="ml-auto text-xs text-text-muted">Las demás zonas se muestran como referencia y no se pueden editar aquí.</span>
                    </div>
                    <input type="hidden" name="poligono_geojson" value="{{ $geojsonActual }}" data-delimitacion-geojson @disabled(! $reabrirDelimitacion)>
                    <p class="text-sm text-danger" role="alert" data-delimitacion-error @if (! $errors->hasAny(['poligono_geojson', 'color_mapa'])) hidden @endif>{{ $errors->first('poligono_geojson') ?: $errors->first('color_mapa') }}</p>
                    <p class="text-xs text-text-muted" data-delimitacion-aviso hidden></p>
                    <p class="text-xs text-text-muted">Delimitación operativa referencial de acopio: no es un límite político ni catastral.</p>
                </div>
            </div>

            <p class="text-xs text-text-muted">El estado se cambia con el switch del listado o desde el menú de acciones.</p>
            <div class="flex justify-end gap-2 border-t border-text-muted/10 pt-4">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar cambios</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal :id="$modalRuta" title="Nueva ruta">
        <form method="POST" action="{{ route('admin.zonas.rutas.store', $panel->id) }}" class="space-y-4">
            @csrf
            <input type="hidden" name="estado" value="ACTIVO">
            <x-forms.input name="nombre" :id="$modalRuta.'-nombre'" label="Nombre de la ruta" :placeholder="'Ej. Ruta '.$panel->nombre" required />
            <x-forms.textarea name="descripcion" :id="$modalRuta.'-descripcion'" label="Descripción" :rows="2" />
            <div class="flex justify-end gap-2 border-t border-text-muted/10 pt-4">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Crear ruta</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@elseif ($proveedoresSinZona !== null)
    <div class="fichas-panel-contenido" data-fichas-contenido data-ficha-id="sin-asignar">
        <header class="fichas-panel-head">
            <span class="fichas-avatar is-aviso" aria-hidden="true"><x-ui.icon name="map-pin" class="h-6 w-6" /></span>
            <div class="min-w-0 flex-1">
                <h2 class="fichas-panel-nombre">Proveedores sin zona</h2>
                <p class="fichas-panel-dni">{{ count($proveedoresSinZona) }} {{ count($proveedoresSinZona) === 1 ? 'proveedor activo pendiente' : 'proveedores activos pendientes' }} de asignación.</p>
            </div>
            <button type="button" class="fichas-cerrar" data-fichas-cerrar aria-label="Cerrar panel" title="Cerrar panel">
                <x-ui.icon name="x-mark" class="h-5 w-5" />
            </button>
        </header>

        <div class="fichas-panel-cuerpo">
            @include('admin.zonas._tabla-proveedores', [
                'proveedores' => array_map(fn ($fila) => ['fila' => $fila, 'telefono' => null, 'ultimaEntrega' => null, 'litrosSemana' => 0.0], $proveedoresSinZona),
                'esSinAsignar' => true,
            ])
        </div>
    </div>
@else
    <x-fichas.panel-vacio entidad="zona">Haz click en una fila del listado para ver su resumen, proveedores y acopiadores.</x-fichas.panel-vacio>
@endif
