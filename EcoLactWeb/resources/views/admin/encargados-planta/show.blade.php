@extends('layouts.admin')

@section('title', $encargado->nombreCompleto())

@section('content')
    <x-ui.button :href="route('admin.encargados-planta.index')" variant="ghost" size="sm" as="a">&larr; Volver a encargados de planta</x-ui.button>

    <x-ui.card :title="$encargado->nombreCompleto()" subtitle="Detalle del encargado de planta">
        <x-slot:actions>
            <x-ui.badge :status="$encargado->estado()->value" />
            <x-ui.action-button type="edit" :text="true" label="Editar" data-modal-open="modal-editar-encargado" />
            @if ($encargado->estado()->value === 'ACTIVO')
                <x-ui.action-button type="deactivate" :text="true" label="Desactivar" data-modal-open="modal-desactivar-encargado" />
            @else
                <x-ui.action-button type="activate" :text="true" label="Reactivar" data-modal-open="modal-reactivar-encargado" />
            @endif
        </x-slot:actions>

        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">DNI</dt>
                <dd class="mt-1 text-sm text-text">{{ $encargado->dni() }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Teléfono</dt>
                <dd class="mt-1 text-sm text-text">{{ $encargado->telefono() ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Fecha de ingreso</dt>
                <dd class="mt-1 text-sm text-text">{{ $encargado->fechaIngreso()?->format('d/m/Y') ?? 'Sin registrar' }}</dd>
            </div>
        </dl>
    </x-ui.card>

    <x-ui.card>
        <x-pagos.control-periodo :control-periodo="$controlPeriodo" :trabajador-id="$encargado->userId()" :trabajador-nombre="$encargado->nombreCompleto()" />
    </x-ui.card>

    <x-ui.modal id="modal-editar-encargado" title="Editar encargado de planta">
        <form method="POST" action="{{ route('admin.encargados-planta.update', $encargado->id()) }}" class="space-y-4">
            @csrf
            @method('PATCH')
            <x-forms.input name="nombres" label="Nombres" :value="$encargado->nombres()" required />
            <x-forms.input name="apellidos" label="Apellidos" :value="$encargado->apellidos()" required />
            <x-forms.input name="telefono" label="Teléfono" :value="$encargado->telefono()" />
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar cambios</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal id="modal-desactivar-encargado" title="Desactivar encargado de planta">
        <p class="text-sm text-text">¿Desactivar a <strong>{{ $encargado->nombreCompleto() }}</strong>?</p>
        <p class="mt-2 text-xs text-text-muted">No se elimina: el registro y su historial se conservan, pero dejará de estar disponible para operar. Podrás reactivarlo cuando quieras.</p>
        <form method="POST" action="{{ route('admin.encargados-planta.desactivar', $encargado->id()) }}" class="mt-4 flex justify-end gap-2">
            @csrf
            @method('PATCH')
            <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
            <x-ui.button type="submit" variant="danger">Sí, desactivar</x-ui.button>
        </form>
    </x-ui.modal>

    <x-ui.modal id="modal-reactivar-encargado" title="Reactivar encargado de planta">
        <p class="text-sm text-text">¿Reactivar a <strong>{{ $encargado->nombreCompleto() }}</strong>?</p>
<p class="mt-2 text-xs text-text-muted">Volverá a estar disponible para operar con normalidad.</p>
        <form method="POST" action="{{ route('admin.encargados-planta.reactivar', $encargado->id()) }}" class="mt-4 flex justify-end gap-2">
            @csrf
            @method('PATCH')
            <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
            <x-ui.button type="submit" variant="secondary">Reactivar</x-ui.button>
        </form>
    </x-ui.modal>
@endsection
