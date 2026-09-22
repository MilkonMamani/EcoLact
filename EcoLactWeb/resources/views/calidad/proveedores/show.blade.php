@extends('layouts.calidad')

@section('title', $proveedor->nombreCompleto())

@section('content')
    <x-ui.button :href="route('calidad.proveedores.index')" variant="ghost" size="sm" as="a">&larr; Proveedores</x-ui.button>

    <x-ui.card :title="$proveedor->nombreCompleto()" :subtitle="'DNI: '.$proveedor->dni()">
        <x-slot:actions>
            <x-ui.badge :status="$proveedor->estado()->value" />
        </x-slot:actions>

        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-text-muted">Datos personales</h3>
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div><dt class="text-xs text-text-muted">Nombres</dt><dd class="text-sm text-text">{{ $proveedor->nombres() }}</dd></div>
                    <div><dt class="text-xs text-text-muted">Apellidos</dt><dd class="text-sm text-text">{{ $proveedor->apellidos() }}</dd></div>
                    <div><dt class="text-xs text-text-muted">DNI</dt><dd class="text-sm text-text">{{ $proveedor->dni() }}</dd></div>
                    <div><dt class="text-xs text-text-muted">Teléfono</dt><dd class="text-sm text-text">{{ $proveedor->telefono() ?? '—' }}</dd></div>
                </dl>
            </div>
            <div>
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-text-muted">Zona y seguimiento</h3>
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div><dt class="text-xs text-text-muted">Zona actual</dt><dd class="text-sm text-text">Zona #{{ $proveedor->zonaId() }}</dd></div>
                    <div><dt class="text-xs text-text-muted">Fecha de registro</dt><dd class="text-sm text-text">{{ $proveedor->fechaRegistro()->format('d/m/Y') }}</dd></div>
                    <div><dt class="text-xs text-text-muted">Advertencias</dt><dd class="text-sm text-text">{{ $proveedor->advertenciasCount() }}</dd></div>
                </dl>
            </div>
        </div>
    </x-ui.card>

    <x-ui.card :padded="false" class="mt-4">
        <div class="flex flex-wrap gap-1 border-b border-text-muted/10 px-3 pt-3" role="tablist">
            @foreach (['resumen' => 'Resumen', 'entregas' => 'Entregas', 'calidad' => 'Calidad', 'zona' => 'Zona'] as $clave => $etiqueta)
                <button type="button" data-perfil-tab="{{ $clave }}" class="rounded-t-lg px-3 py-2 text-sm font-medium text-text-muted hover:bg-text-muted/5 data-[active=true]:border-b-2 data-[active=true]:border-primary data-[active=true]:text-primary" data-active="{{ $clave === 'resumen' ? 'true' : 'false' }}">{{ $etiqueta }}</button>
            @endforeach
        </div>

        <div class="p-5">
            <div data-perfil-panel="resumen">
                <p class="text-sm text-text-muted">Último análisis: {{ $analisis === [] ? 'Sin análisis registrados.' : $analisis[0]->fechaAnalisis()->format('d/m/Y H:i').' — '.($analisis[0]->resultado()?->value ?? 'Sin resultado') }}</p>
                <p class="mt-1 text-sm text-text-muted">Seguimiento de calidad: {{ $proveedor->advertenciasCount() > 0 ? $proveedor->advertenciasCount().' advertencia(s) registrada(s).' : 'Sin advertencias.' }}</p>
            </div>

            <div data-perfil-panel="entregas" hidden>
                @if (count($entregas) === 0)
                    <x-ui.empty-state title="Sin entregas registradas" :icon="false" />
                @else
                    <x-tables.table>
                        <thead>
                            <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                                <th class="py-2 pr-4">Fecha</th>
                                <th class="py-2 pr-4">Zona</th>
                                <th class="py-2 pr-4">Litros</th>
                                <th class="py-2 pr-4">Identificación</th>
                                <th class="py-2 pr-4">Estado</th>
                                <th class="py-2 pl-4 text-right">Ver</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entregas as $entrega)
                                <tr class="border-b border-text-muted/5 last:border-0">
                                    <td class="py-2 pr-4 text-text">{{ $entrega->fechaHora()->format('d/m/Y H:i') }}</td>
                                    <td class="py-2 pr-4 text-text-muted">Zona #{{ $entrega->zonaId() }}</td>
                                    <td class="py-2 pr-4 text-text">{{ number_format($entrega->litros(), 2) }} L</td>
                                    <td class="py-2 pr-4 text-text-muted">{{ $entrega->metodoIdentificacion() }}</td>
                                    <td class="py-2 pr-4"><x-ui.badge :status="$entrega->estado()->value" /></td>
                                    <td class="py-2 pl-4 text-right"><a class="text-primary hover:underline" href="{{ route('calidad.entregas.show', $entrega->id()) }}">Ver</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-tables.table>
                @endif
            </div>

            <div data-perfil-panel="calidad" hidden>
                @if (count($analisis) === 0)
                    <x-ui.empty-state title="Sin análisis de calidad registrados" :icon="false" />
                @else
                    <x-tables.table>
                        <thead>
                            <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                                <th class="py-2 pr-4">Fecha</th>
                                <th class="py-2 pr-4">Entrega</th>
                                <th class="py-2 pr-4">Resultado</th>
                                <th class="py-2 pr-4">Estado</th>
                                <th class="py-2 pl-4 text-right">Ver</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($analisis as $item)
                                <tr class="border-b border-text-muted/5 last:border-0">
                                    <td class="py-2 pr-4 text-text">{{ $item->fechaAnalisis()->format('d/m/Y H:i') }}</td>
                                    <td class="py-2 pr-4 text-text-muted">#{{ $item->entregaId() }}</td>
                                    <td class="py-2 pr-4">@if ($item->resultado())<x-ui.badge :status="$item->resultado()->value" />@else <span class="text-xs text-text-muted">Sin resultado</span>@endif</td>
                                    <td class="py-2 pr-4"><x-ui.badge :status="$item->estado()->value" /></td>
                                    <td class="py-2 pl-4 text-right"><a class="text-primary hover:underline" href="{{ route('calidad.analisis-calidad.show', $item->id()) }}">Ver</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-tables.table>
                @endif
            </div>

            <div data-perfil-panel="zona" hidden>
                <p class="mb-3 text-sm text-text">Zona actual: <strong>Zona #{{ $proveedor->zonaId() }}</strong></p>

                @if (count($zonaHistorial) === 0)
                    <x-ui.empty-state title="Sin historial de zonas" :icon="false" />
                @else
                    <x-tables.table>
                        <thead>
                            <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                                <th class="py-2 pr-4">Zona</th>
                                <th class="py-2 pr-4">Desde</th>
                                <th class="py-2 pr-4">Hasta</th>
                                <th class="py-2 pr-4">Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($zonaHistorial as $historial)
                                <tr class="border-b border-text-muted/5 last:border-0">
                                    <td class="py-2 pr-4 text-text">Zona #{{ $historial->zonaId() }}</td>
                                    <td class="py-2 pr-4 text-text-muted">{{ $historial->fechaInicio()->format('d/m/Y') }}</td>
                                    <td class="py-2 pr-4 text-text-muted">{{ $historial->fechaFin()?->format('d/m/Y') ?? 'Actual' }}</td>
                                    <td class="py-2 pr-4 text-text-muted">{{ $historial->motivo() ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-tables.table>
                @endif
            </div>
        </div>
    </x-ui.card>

    <script>
        (function () {
            const botones = document.querySelectorAll('[data-perfil-tab]');
            const paneles = document.querySelectorAll('[data-perfil-panel]');

            botones.forEach((boton) => {
                boton.addEventListener('click', () => {
                    const clave = boton.getAttribute('data-perfil-tab');
                    botones.forEach((b) => b.setAttribute('data-active', b === boton ? 'true' : 'false'));
                    paneles.forEach((panel) => {
                        panel.hidden = panel.getAttribute('data-perfil-panel') !== clave;
                    });
                });
            });
        })();
    </script>
@endsection
