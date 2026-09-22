@extends('layouts.admin')

@section('title', $detalle->comunicado->titulo())

@section('content')
    <x-ui.button :href="route('admin.comunicados.index')" variant="ghost" size="sm" as="a">&larr; Comunicados</x-ui.button>

    <x-ui.card :title="$detalle->comunicado->titulo()" :subtitle="'Publicado el ' . $detalle->comunicado->fechaPublicacion()->format('d/m/Y')">
        <x-slot:actions>
            <x-ui.badge variant="info">{{ $detalle->comunicado->tipo() }}</x-ui.badge>
            <x-ui.badge :status="$detalle->comunicado->estado()" />
            @if ($detalle->comunicado->estado() === 'BORRADOR')
                <x-ui.action-button type="edit" :text="true" label="Editar" data-modal-open="modal-editar-comunicado" />
                <x-ui.button data-modal-open="modal-publicar-comunicado" size="sm" as="button" type="button">Publicar</x-ui.button>
            @endif
        </x-slot:actions>

        <p class="whitespace-pre-line text-sm text-text">{{ $detalle->comunicado->contenido() }}</p>

        <dl class="mt-6 grid gap-4 border-t border-text-muted/10 pt-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Alcance</dt>
                <dd class="mt-1 text-sm text-text">{{ $detalle->comunicado->alcance() }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Zona</dt>
                <dd class="mt-1 text-sm text-text">{{ $detalle->comunicado->zonaId() ? 'Zona #' . $detalle->comunicado->zonaId() : 'No aplica' }}</dd>
            </div>
            @if ($detalle->comunicado->fechaEvento())
                <div>
                    <dt class="text-xs uppercase tracking-wide text-text-muted">Fecha del evento</dt>
                    <dd class="mt-1 text-sm text-text">{{ $detalle->comunicado->fechaEvento()->format('d/m/Y') }}</dd>
                </div>
            @endif
        </dl>
    </x-ui.card>

    <x-ui.card title="Destinatarios" :subtitle="count($detalle->destinatarios).' destinatario(s)'" class="mt-4">
        <div class="mb-4 flex gap-6 text-sm">
            <span class="text-success">✓ Leídos: <strong>{{ $detalle->leidos }}</strong></span>
            <span class="text-text-muted">○ No leídos: <strong>{{ $detalle->noLeidos }}</strong></span>
        </div>

        @if (count($detalle->destinatarios) === 0)
            <x-ui.empty-state title="Sin destinatarios registrados" :icon="false" />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Proveedor</th>
                        <th class="py-2 pr-4">DNI</th>
                        <th class="py-2 pr-4">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($detalle->destinatarios as $destinatario)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-2 pr-4 text-text">{{ $destinatario['nombre'] }}</td>
                            <td class="py-2 pr-4 text-text-muted">{{ $destinatario['dni'] ?? '—' }}</td>
                            <td class="py-2 pr-4">
                                @if ($destinatario['leido'])
                                    <x-ui.badge variant="success">Leído</x-ui.badge>
                                @else
                                    <x-ui.badge variant="neutral">No leído</x-ui.badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>

    @if ($detalle->comunicado->estado() === 'BORRADOR')
        <x-ui.modal id="modal-editar-comunicado" title="Editar comunicado">
            <form method="POST" action="{{ route('admin.comunicados.update', $detalle->comunicado->id()) }}" class="space-y-4">
                @csrf
                @method('PATCH')
                <x-forms.input name="titulo" label="Título" :value="$detalle->comunicado->titulo()" required />
                <x-forms.textarea name="contenido" label="Mensaje" :rows="4" :value="$detalle->comunicado->contenido()" required />
                <x-forms.select name="tipo" label="Tipo" :value="$detalle->comunicado->tipo()" :options="[
                    'INFORMACION' => 'Información',
                    'AVISO' => 'Aviso',
                    'CAPACITACION' => 'Capacitación',
                ]" />
                <x-forms.input name="fecha_evento" type="date" label="Fecha del evento (opcional)" :value="$detalle->comunicado->fechaEvento()?->format('Y-m-d')" />
                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                    <x-ui.button type="submit">Guardar cambios</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.modal id="modal-publicar-comunicado" title="Publicar comunicado">
            <p class="text-sm text-text">¿Deseas publicar este comunicado para {{ count($detalle->destinatarios) }} destinatario(s)?</p>
            <form method="POST" action="{{ route('admin.comunicados.publicar', $detalle->comunicado->id()) }}" class="mt-4 flex justify-end gap-2">
                @csrf
                @method('PATCH')
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Confirmar publicación</x-ui.button>
            </form>
        </x-ui.modal>
    @endif
@endsection
