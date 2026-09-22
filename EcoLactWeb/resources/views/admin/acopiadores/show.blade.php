@extends('layouts.admin')

@section('title', $acopiador->nombreCompleto())

@section('content')
    <x-ui.button :href="route('admin.acopiadores.index')" variant="ghost" size="sm" as="a">&larr; Acopiadores</x-ui.button>

    <x-ui.card :title="$acopiador->nombreCompleto()" :subtitle="'DNI: '.$acopiador->dni()">
        <x-slot:actions>
            <x-ui.badge :status="$acopiador->estado()->value" />
            <x-ui.action-button type="edit" :text="true" label="Editar" data-modal-open="modal-editar-acopiador" />
            @if ($acopiador->estado()->value === 'ACTIVO')
                <x-ui.action-button type="deactivate" :text="true" label="Desactivar" data-modal-open="modal-desactivar-acopiador" />
            @else
                <x-ui.action-button type="activate" :text="true" label="Reactivar" data-modal-open="modal-reactivar-acopiador" />
            @endif
        </x-slot:actions>

        <div class="grid gap-8 lg:grid-cols-2">
            <x-ui.profile-section title="Datos personales" icon="user">
                <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                    <x-ui.data-field label="Nombres">{{ $acopiador->nombres() }}</x-ui.data-field>
                    <x-ui.data-field label="Apellidos">{{ $acopiador->apellidos() }}</x-ui.data-field>
                    <x-ui.data-field label="DNI" icon="id-card">{{ $acopiador->dni() }}</x-ui.data-field>
                    <x-ui.data-field label="Teléfono" icon="phone" :muted="! $acopiador->telefono()">{{ $acopiador->telefono() ?? 'No registrado' }}</x-ui.data-field>
                </dl>
            </x-ui.profile-section>

            <x-ui.profile-section title="Zona de acopio e ingreso" icon="map-pin">
                <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                    <x-ui.data-field label="Zona" icon="map">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-soft px-3 py-1 text-sm font-semibold text-primary-dark">{{ $zonas->get($acopiador->zonaId(), 'Zona #'.$acopiador->zonaId()) }}</span>
                    </x-ui.data-field>
                    <x-ui.data-field label="Fecha de ingreso" icon="calendar" :muted="! $acopiador->fechaIngreso()">{{ $acopiador->fechaIngreso()?->format('d/m/Y') ?? 'Sin registrar' }}</x-ui.data-field>
                </dl>
            </x-ui.profile-section>
        </div>
    </x-ui.card>

    <x-ui.card :padded="false" class="mt-4">
        <div class="flex flex-wrap gap-1 border-b border-text-muted/10 px-3 pt-3" role="tablist">
            @foreach (['resumen' => 'Resumen', 'entregas' => 'Entregas', 'zona' => 'Zona', 'pagos' => 'Pagos'] as $clave => $etiqueta)
                <button type="button" data-perfil-tab="{{ $clave }}" class="rounded-t-lg px-4 py-2 text-sm font-medium text-text-muted transition-colors hover:bg-text-muted/5 border-b-2 border-transparent data-[active=true]:border-primary data-[active=true]:bg-primary-soft/60 data-[active=true]:font-semibold data-[active=true]:text-primary-dark" data-active="{{ $clave === 'resumen' ? 'true' : 'false' }}">{{ $etiqueta }}</button>
            @endforeach
        </div>

        <div class="p-5">
            <div data-perfil-panel="resumen">
                <p class="text-sm text-text-muted">Entregas registradas: {{ count($entregas) }}</p>
                <p class="mt-1 text-sm text-text-muted">Litros acopiados: {{ number_format(collect($entregas)->sum(fn ($e) => $e->litros()), 2) }} L</p>
            </div>

            <div data-perfil-panel="entregas" hidden>
                @if (count($entregas) === 0)
                    <x-ui.empty-state title="Sin entregas registradas" :icon="false" />
                @else
                    <x-tables.table>
                        <thead>
                            <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                                <th class="py-2 pr-4">Fecha</th>
                                <th class="py-2 pr-4">Proveedor</th>
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
                                    <td class="py-2 pr-4 text-text-muted">Proveedor #{{ $entrega->proveedorId() }}</td>
                                    <td class="py-2 pr-4 text-text-muted">{{ $zonas->get($entrega->zonaId(), 'Zona #'.$entrega->zonaId()) }}</td>
                                    <td class="py-2 pr-4 text-text">{{ number_format($entrega->litros(), 2) }} L</td>
                                    <td class="py-2 pr-4 text-text-muted">{{ $entrega->metodoIdentificacion() }}</td>
                                    <td class="py-2 pr-4"><x-ui.badge :status="$entrega->estado()->value" /></td>
                                    <td class="py-2 pl-4 text-right"><a class="text-primary hover:underline" href="{{ route('admin.entregas.show', $entrega->id()) }}">Ver</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-tables.table>
                @endif
            </div>

            <div data-perfil-panel="zona" hidden>
                <dl class="mb-5"><x-ui.data-field label="Zona" icon="map">{{ $zonas->get($acopiador->zonaId(), 'Zona #'.$acopiador->zonaId()) }}</x-ui.data-field></dl>

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
                                    <td class="py-2 pr-4 text-text">{{ $zonas->get($historial->zonaId(), 'Zona #'.$historial->zonaId()) }}</td>
                                    <td class="py-2 pr-4 text-text-muted">{{ $historial->fechaInicio()->format('d/m/Y') }}</td>
                                    <td class="py-2 pr-4 text-text-muted">{{ $historial->fechaFin()?->format('d/m/Y') ?? 'Actual' }}</td>
                                    <td class="py-2 pr-4 text-text-muted">{{ $historial->motivo() ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-tables.table>
                @endif
            </div>

            <div data-perfil-panel="pagos" hidden>
                <x-pagos.control-periodo :control-periodo="$controlPeriodo" :trabajador-id="$acopiador->userId()" :trabajador-nombre="$acopiador->nombreCompleto()" />
            </div>
        </div>
    </x-ui.card>

    <x-ui.modal id="modal-editar-acopiador" title="Editar acopiador">
        <form method="POST" action="{{ route('admin.acopiadores.update', $acopiador->id()) }}" class="space-y-4">
            @csrf
            @method('PATCH')
            <x-forms.input name="nombres" label="Nombres" :value="$acopiador->nombres()" required />
            <x-forms.input name="apellidos" label="Apellidos" :value="$acopiador->apellidos()" required />
            <x-forms.input name="telefono" label="Teléfono" :value="$acopiador->telefono()" />
            <x-forms.select
    name="zona_id"
    label="Zona"
    required
    :options="$zonas->all()"
/>
            <x-forms.input name="motivo_cambio_zona" label="Motivo del cambio de zona (si aplica)" />
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar cambios</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal id="modal-desactivar-acopiador" title="Desactivar acopiador">
        <p class="text-sm text-text">¿Desactivar a <strong>{{ $acopiador->nombreCompleto() }}</strong>?</p>
        <p class="mt-2 text-xs text-text-muted">No se elimina: el registro y su historial se conservan, pero dejará de estar disponible para operar. Podrás reactivarlo cuando quieras.</p>
        <p class="mt-1 text-sm text-text-muted">No podrá registrar nuevas entregas. Su información histórica se conservará.</p>
        <form method="POST" action="{{ route('admin.acopiadores.desactivar', $acopiador->id()) }}" class="mt-4 flex justify-end gap-2">
            @csrf
            @method('PATCH')
            <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
            <x-ui.button type="submit" variant="danger">Sí, desactivar</x-ui.button>
        </form>
    </x-ui.modal>

    <x-ui.modal id="modal-reactivar-acopiador" title="Reactivar acopiador">
        <p class="text-sm text-text">Selecciona la zona a la que se reincorpora <strong>{{ $acopiador->nombreCompleto() }}</strong>.</p>
        <form method="POST" action="{{ route('admin.acopiadores.reactivar', $acopiador->id()) }}" class="mt-4 space-y-4">
            @csrf
            @method('PATCH')
            <x-forms.select
    name="zona_id"
    label="Zona"
    required
    :options="$zonas->all()"
/>
            <div class="flex justify-end gap-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit" variant="secondary">Reactivar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

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
