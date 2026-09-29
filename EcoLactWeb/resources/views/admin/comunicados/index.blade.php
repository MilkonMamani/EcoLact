@extends('layouts.admin')

@section('title', 'Comunicados')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card label="Total" :value="$datos->kpis['total']" />
        <x-ui.stat-card label="Publicados" :value="$datos->kpis['publicados']" accent="success" />
        <x-ui.stat-card label="Borradores" :value="$datos->kpis['borradores']" accent="warning" />
        <x-ui.stat-card label="Destinatarios alcanzados" :value="$datos->kpis['destinatariosAlcanzados']" hint="Según filtros aplicados" />
    </div>

    <x-ui.card title="Comunicados" subtitle="Gestiona la información y avisos dirigidos a proveedores y usuarios.">
        <x-slot:actions>
            @can('create', \App\Infrastructure\Persistence\Models\ComunicadoModel::class)
                <x-ui.button data-modal-open="modal-nuevo-comunicado" size="sm" as="button" type="button">+ Nuevo comunicado</x-ui.button>
            @endcan
        </x-slot:actions>

        <form method="GET" class="mb-4 space-y-3">
            <x-forms.input name="q" label="Buscar por título" value="{{ request('q') }}" />

            <div class="flex flex-wrap items-end gap-3">
                <div class="w-44">
                    <x-forms.select name="tipo" label="Tipo" :options="[
                        'INFORMACION' => 'Información',
                        'AVISO' => 'Aviso',
                        'CAPACITACION' => 'Capacitación',
                    ]" :value="request('tipo')" placeholder="Todos" />
                </div>

                <div class="w-44">
                    <x-forms.select name="estado" label="Estado" :options="['BORRADOR' => 'Borrador', 'PUBLICADO' => 'Publicado']" :value="request('estado')" placeholder="Todos" />
                </div>

                <div class="w-44">
                    <x-forms.select name="periodo" label="Fecha" :options="[
                        'hoy' => 'Hoy',
                        'esta_semana' => 'Esta semana',
                        'este_mes' => 'Este mes',
                        'personalizado' => 'Personalizado',
                    ]" :value="request('periodo')" placeholder="Todas" />
                </div>

                @if (request('periodo') === 'personalizado')
                    <div class="w-40">
                        <x-forms.input name="desde" type="date" label="Desde" value="{{ request('desde') }}" />
                    </div>
                    <div class="w-40">
                        <x-forms.input name="hasta" type="date" label="Hasta" value="{{ request('hasta') }}" />
                    </div>
                @endif

                <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
                @if (request()->anyFilled(['q', 'tipo', 'estado', 'periodo']))
                    <x-ui.button :href="route('admin.comunicados.index')" variant="ghost" size="sm" as="a">Limpiar filtros</x-ui.button>
                @endif
            </div>
        </form>

        @if (count($datos->filas) === 0)
            <x-ui.empty-state title="No hay comunicados" description="Publica el primer comunicado para informar a tu equipo." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Comunicado</th>
                        <th class="py-2 pr-4">Tipo</th>
                        <th class="py-2 pr-4">Destinatarios</th>
                        <th class="py-2 pr-4">Fecha</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pl-4 text-right">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($datos->filas as $fila)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 font-medium text-text">{{ $fila['titulo'] }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $fila['tipo'] }}</td>
                            <td class="py-3 pr-4 text-text">{{ $fila['destinatarios'] }}</td>
                            <td class="py-3 pr-4 whitespace-nowrap text-text-muted">{{ $fila['fecha'] }}</td>
                            <td class="py-3 pr-4"><x-ui.badge compact :status="$fila['estado']" /></td>
                            <td class="py-3 pl-4">
                                <x-tables.actions>
                                    <x-ui.action-button type="view" label="Ver comunicado" :href="route('admin.comunicados.show', $fila['id'])" />
                                </x-tables.actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>

    <x-ui.modal id="modal-nuevo-comunicado" title="Nuevo comunicado">
        <form method="POST" action="{{ route('admin.comunicados.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium text-text">Alcance *</label>
                <div class="space-y-1 text-sm text-text">
                    <label class="flex items-center gap-2"><input type="radio" name="alcance" value="INDIVIDUAL" checked class="text-primary focus:ring-primary" data-alcance-radio> Proveedores seleccionados</label>
                    <label class="flex items-center gap-2"><input type="radio" name="alcance" value="ZONA" class="text-primary focus:ring-primary" data-alcance-radio> Zona</label>
                    <label class="flex items-center gap-2"><input type="radio" name="alcance" value="GENERAL" class="text-primary focus:ring-primary" data-alcance-radio> Todos los proveedores</label>
                </div>
            </div>

            <div data-alcance-panel="INDIVIDUAL">
                <label class="mb-1 block text-sm font-medium text-text">Destinatarios *</label>
                <select name="destinatarios[]" multiple size="5" class="w-full rounded-lg border border-text-muted/25 px-3 py-2 text-sm">
                    @foreach ($proveedores as $proveedor)
                        <option value="{{ $proveedor->userId() }}">{{ $proveedor->nombreCompleto() }} — {{ $proveedor->dni() }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-text-muted">Mantén Ctrl (o Cmd) presionado para seleccionar varios proveedores.</p>
            </div>

            <div data-alcance-panel="ZONA" hidden>
                <x-forms.select name="zona_id" label="Zona" :options="$zonas" placeholder="Selecciona una zona" />
            </div>

            <x-forms.select name="tipo" label="Tipo" required :options="[
                'INFORMACION' => 'Información',
                'AVISO' => 'Aviso',
                'CAPACITACION' => 'Capacitación',
            ]" />
            <x-forms.input name="titulo" label="Título" required />
            <x-forms.textarea name="contenido" label="Mensaje" :rows="4" required />
            <x-forms.input name="fecha_evento" type="date" label="Fecha del evento (opcional)" />

            <div>
                <label class="mb-1 block text-sm font-medium text-text">Publicación</label>
                <div class="space-y-1 text-sm text-text">
                    <label class="flex items-center gap-2"><input type="radio" name="publicacion" value="ahora" checked class="text-primary focus:ring-primary" data-publicacion-radio> Publicar ahora</label>
                    <label class="flex items-center gap-2"><input type="radio" name="publicacion" value="programar" class="text-primary focus:ring-primary" data-publicacion-radio> Programar</label>
                </div>
            </div>

            <div data-publicacion-panel="programar" hidden class="grid grid-cols-2 gap-3">
                <x-forms.input name="fecha_publicacion_fecha" type="date" label="Fecha de publicación" />
                <x-forms.input name="fecha_publicacion_hora" type="time" label="Hora" />
            </div>
            <input type="hidden" name="fecha_publicacion" data-fecha-publicacion-final>
            <input type="hidden" name="estado" value="PUBLICADO" data-estado-final>

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit" variant="secondary" data-guardar-borrador>Guardar borrador</x-ui.button>
                <x-ui.button type="submit">Publicar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <script>
        (function () {
            const form = document.querySelector('#modal-nuevo-comunicado form');
            if (!form) {
                return;
            }

            const alcanceRadios = form.querySelectorAll('[data-alcance-radio]');
            const alcancePaneles = form.querySelectorAll('[data-alcance-panel]');
            alcanceRadios.forEach((radio) => {
                radio.addEventListener('change', () => {
                    alcancePaneles.forEach((panel) => {
                        panel.hidden = panel.getAttribute('data-alcance-panel') !== radio.value;
                    });
                });
            });

            const publicacionRadios = form.querySelectorAll('[data-publicacion-radio]');
            const publicacionPanel = form.querySelector('[data-publicacion-panel="programar"]');
            publicacionRadios.forEach((radio) => {
                radio.addEventListener('change', () => {
                    publicacionPanel.hidden = radio.value !== 'programar';
                });
            });

            const estadoFinal = form.querySelector('[data-estado-final]');
            const fechaPublicacionFinal = form.querySelector('[data-fecha-publicacion-final]');
            const botonBorrador = form.querySelector('[data-guardar-borrador]');

            form.addEventListener('submit', (evento) => {
                const submitter = evento.submitter;
                estadoFinal.value = submitter === botonBorrador ? 'BORRADOR' : 'PUBLICADO';

                const programar = form.querySelector('[data-publicacion-radio]:checked')?.value === 'programar';
                if (programar) {
                    const fecha = form.querySelector('[name="fecha_publicacion_fecha"]').value;
                    const hora = form.querySelector('[name="fecha_publicacion_hora"]').value || '00:00';
                    fechaPublicacionFinal.value = fecha ? `${fecha} ${hora}` : '';
                } else {
                    fechaPublicacionFinal.value = '';
                }
            });
        })();
    </script>
@endsection
