{{--
    Panel de un ROL laboral: su sueldo base, el personal que lo hereda (solo informativo: no hay
    sueldos individuales) y el historial real de versiones salariales. Nada de asistencia ni pagos.
    "Configurar sueldo" abre el formulario del rol, que vive en index (_form-sueldo-rol).
    $rol: SueldosPorRolQuery::detalle(), o null (ningún rol elegido / rol no remunerable).
--}}
@php
    $puedeConfigurar = auth()->user()->can('create', \App\Infrastructure\Persistence\Models\ConfiguracionPagoModel::class);
    $fecha = fn (?string $valor): string => $valor ? \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y') : '—';
    $soles = fn (?float $valor): string => 'S/ '.number_format((float) $valor, 2);
@endphp

@if ($rol === null)
    <div class="fichas-panel-vacio">
        <span class="fichas-panel-vacio-icono"><x-ui.icon name="users" class="h-6 w-6" /></span>
        <p class="font-semibold text-text">Selecciona un rol</p>
        <p>Haz clic en una fila para ver su sueldo, su personal y el historial.</p>
    </div>
@else
    @php
        $configurado = $rol['configuracionId'] !== null;
        $anteriores = $rol['sueldosIndividualesAnteriores'] ?? [];
        $distintos = count(array_unique(array_column($anteriores, 'sueldo'))) > 1;
    @endphp

    <div class="fichas-panel-contenido" data-fichas-contenido data-ficha-id="{{ $rol['rolId'] }}">
        <div class="fichas-panel-head">
            <div class="min-w-0 flex-1">
                <p class="config-roles-sobretitulo">Rol laboral</p>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="fichas-panel-nombre mt-0">{{ $rol['rol'] }}</h2>
                    @include('admin.configuracion-pagos._estado', ['estado' => $rol['estado']])
                </div>
                <p class="config-pagos-panel-rol">{{ $rol['personal'] }} {{ $rol['personal'] === 1 ? 'trabajador activo' : 'trabajadores activos' }}</p>
            </div>
            <button type="button" class="fichas-cerrar" data-fichas-cerrar aria-label="Cerrar detalle">
                <x-ui.icon name="x-mark" class="h-5 w-5" />
            </button>
        </div>

        <div class="fichas-panel-cuerpo">
            <section class="fichas-seccion is-primera">
                <div class="fichas-seccion-head">
                    <h3>Información salarial</h3>
                </div>

                @if ($configurado)
                    <div class="config-roles-sueldo">
                        <p>Sueldo base mensual</p>
                        <strong class="is-num">{{ $soles($rol['sueldo']) }}</strong>
                    </div>
                    <dl class="fichas-datos mt-4">
                        <div>
                            <dt>Valor diario referencial</dt>
                            <dd class="is-num">{{ $soles($rol['valorDia']) }}</dd>
                        </div>
                        <div>
                            <dt>Vigente desde</dt>
                            <dd class="is-num">{{ $fecha($rol['vigenteDesde']) }}</dd>
                        </div>
                        @if ($rol['proxima'])
                            <div class="is-ancho">
                                <dt>Próximo sueldo</dt>
                                <dd class="is-num">{{ $soles($rol['proxima']['sueldo']) }} desde {{ $fecha($rol['proxima']['desde']) }}</dd>
                            </div>
                        @endif
                        @if ($rol['observacion'])
                            <div class="is-ancho">
                                <dt>Observación</dt>
                                <dd>{{ $rol['observacion'] }}</dd>
                            </div>
                        @endif
                    </dl>
                @else
                    <div class="fichas-panel-sin-datos config-pagos-sin-sueldo">
                        <p class="font-semibold text-text">Sin sueldo configurado</p>
                        <p>Mientras el rol no tenga sueldo, su personal no genera pagos.</p>
                    </div>
                @endif

                @if ($puedeConfigurar)
                    <button type="button" class="fichas-btn is-primary is-sm mt-4" data-modal-open="modal-sueldo-rol-{{ $rol['rolId'] }}">
                        <x-ui.icon name="{{ $configurado ? 'pencil' : 'plus' }}" class="h-3.5 w-3.5" /> Configurar sueldo
                    </button>
                @endif
            </section>

            @if (! $configurado && $anteriores !== [])
                <section class="fichas-seccion">
                    <div class="config-roles-aviso {{ $distintos ? 'is-conflicto' : '' }}" role="note">
                        <x-ui.icon name="{{ $distintos ? 'triangle-alert' : 'info' }}" class="h-4 w-4 shrink-0" />
                        <div>
                            <p class="font-semibold">{{ $distintos ? 'Sueldos individuales distintos' : 'Sueldo individual anterior' }}</p>
                            <p>{{ $distintos
                                ? 'En el modelo anterior este personal tenía sueldos diferentes. Decide el sueldo base del rol: no se elige automáticamente.'
                                : 'Referencia del modelo anterior por trabajador. Puedes usarlo como sueldo base del rol.' }}</p>
                            <ul class="config-roles-anteriores">
                                @foreach ($anteriores as $anterior)
                                    <li><span>{{ $anterior['nombre'] }}</span><span class="is-num">{{ $soles($anterior['sueldo']) }} · {{ $fecha($anterior['desde']) }}</span></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </section>
            @endif

            <section class="fichas-seccion">
                <div class="fichas-seccion-head">
                    <h3>Personal asociado a este rol</h3>
                    <span class="config-roles-contador">{{ $rol['personal'] }}</span>
                </div>
                @if ($rol['trabajadores'] === [])
                    <p class="config-roles-vacio">Todavía no hay personal activo con este rol.</p>
                @else
                    <ul class="config-roles-personas">
                        @foreach ($rol['trabajadores'] as $persona)
                            <li>
                                <span class="config-roles-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($persona['nombre'], 0, 1)) }}</span>
                                <span class="min-w-0">
                                    <span class="block truncate font-medium text-text">{{ $persona['nombre'] }}</span>
                                    @if ($persona['dni'])
                                        <span class="block text-xs text-text-muted">DNI {{ $persona['dni'] }}</span>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="config-pagos-nota mt-2">Lista informativa: todos heredan el sueldo del rol. No se configuran sueldos individuales.</p>
                @endif
            </section>

            <section class="fichas-seccion">
                <div class="fichas-seccion-head">
                    <h3>Historial de configuraciones</h3>
                </div>
                @if ($rol['historial'] === [])
                    <p class="config-roles-vacio">Sin versiones salariales registradas.</p>
                @else
                    <table class="config-roles-historial">
                        <thead>
                            <tr>
                                <th scope="col">Vigencia</th>
                                <th scope="col" class="is-num">Sueldo mensual</th>
                                <th scope="col">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rol['historial'] as $version)
                                <tr>
                                    <td>
                                        <span class="is-num">{{ $fecha($version['desde']) }}</span>
                                        <span class="block text-xs text-text-muted">{{ $version['hasta'] ? 'hasta '.$fecha($version['hasta']) : 'sin fecha de fin' }}@if ($version['registradoPor']) · {{ $version['registradoPor'] }}@endif</span>
                                    </td>
                                    <td class="is-num font-medium">{{ $soles($version['sueldo']) }}</td>
                                    <td>
                                        <span @class(['config-roles-version', 'is-vigente' => $version['estado'] === 'VIGENTE', 'is-programado' => $version['estado'] === 'PROGRAMADO'])>
                                            {{ ['VIGENTE' => 'Vigente', 'PROGRAMADO' => 'Programado', 'ANTERIOR' => 'Anterior'][$version['estado']] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        </div>
    </div>
@endif
