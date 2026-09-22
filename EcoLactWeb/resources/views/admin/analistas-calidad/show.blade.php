@extends('layouts.admin')

@section('title', $analista->nombreCompleto())

@section('content')
    <x-ui.button :href="route('admin.analistas-calidad.index')" variant="ghost" size="sm" as="a">&larr; Volver a analistas de calidad</x-ui.button>

    <x-ui.card :title="$analista->nombreCompleto()" subtitle="Detalle del analista de calidad">
        <x-slot:actions>
            <x-ui.badge :status="$analista->estado()->value" />
            <x-ui.action-button type="edit" :text="true" label="Editar" data-modal-open="modal-editar-analista" />
            @if ($analista->estado()->value === 'ACTIVO')
                <x-ui.action-button type="deactivate" :text="true" label="Desactivar" data-modal-open="modal-desactivar-analista" />
            @else
                <x-ui.action-button type="activate" :text="true" label="Reactivar" data-modal-open="modal-reactivar-analista" />
            @endif
        </x-slot:actions>

        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">DNI</dt>
                <dd class="mt-1 text-sm text-text">{{ $analista->dni() }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Teléfono</dt>
                <dd class="mt-1 text-sm text-text">{{ $analista->telefono() ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Fecha de ingreso</dt>
                <dd class="mt-1 text-sm text-text">{{ $analista->fechaIngreso()?->format('d/m/Y') ?? 'Sin registrar' }}</dd>
            </div>
        </dl>
    </x-ui.card>

    <x-ui.card>
        <x-pagos.control-periodo :control-periodo="$controlPeriodo" :trabajador-id="$analista->userId()" :trabajador-nombre="$analista->nombreCompleto()" />
    </x-ui.card>

    <x-ui.modal id="modal-editar-analista" title="Editar analista de calidad">
        <form method="POST" action="{{ route('admin.analistas-calidad.update', $analista->id()) }}" class="space-y-4">
            @csrf
            @method('PATCH')
            <x-forms.input name="nombres" label="Nombres" :value="$analista->nombres()" required />
            <x-forms.input name="apellidos" label="Apellidos" :value="$analista->apellidos()" required />
            <x-forms.input name="telefono" label="Teléfono" :value="$analista->telefono()" />
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar cambios</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal id="modal-desactivar-analista" title="Desactivar analista de calidad">
        <p class="text-sm text-text">¿Desactivar a <strong>{{ $analista->nombreCompleto() }}</strong>?</p>
        <p class="mt-2 text-xs text-text-muted">No se elimina: el registro y su historial se conservan, pero dejará de estar disponible para operar. Podrás reactivarlo cuando quieras.</p>
        <form method="POST" action="{{ route('admin.analistas-calidad.desactivar', $analista->id()) }}" class="mt-4 flex justify-end gap-2">
            @csrf
            @method('PATCH')
            <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
            <x-ui.button type="submit" variant="danger">Sí, desactivar</x-ui.button>
        </form>
    </x-ui.modal>

    <x-ui.modal id="modal-reactivar-analista" title="Reactivar analista de calidad">
        <p class="text-sm text-text">¿Reactivar a <strong>{{ $analista->nombreCompleto() }}</strong>?</p>
<p class="mt-2 text-xs text-text-muted">Volverá a estar disponible para operar con normalidad.</p>
        <form method="POST" action="{{ route('admin.analistas-calidad.reactivar', $analista->id()) }}" class="mt-4 flex justify-end gap-2">
            @csrf
            @method('PATCH')
            <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
            <x-ui.button type="submit" variant="secondary">Reactivar</x-ui.button>
        </form>
    </x-ui.modal>
@endsection
