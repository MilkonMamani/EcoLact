@extends('layouts.admin')

@section('title', $personal->nombreCompleto())

@section('content')
    <x-ui.button :href="route('admin.personal-ventas.index')" variant="ghost" size="sm" as="a">&larr; Volver a personal de ventas</x-ui.button>

    <x-ui.card :title="$personal->nombreCompleto()" subtitle="Detalle del personal de ventas">
        <x-slot:actions>
            <x-ui.badge :status="$personal->estado()->value" />
            <x-ui.action-button type="edit" :text="true" label="Editar" data-modal-open="modal-editar-personal" />
            @if ($personal->estado()->value === 'ACTIVO')
                <x-ui.action-button type="deactivate" :text="true" label="Desactivar" data-modal-open="modal-desactivar-personal" />
            @else
                <x-ui.action-button type="activate" :text="true" label="Reactivar" data-modal-open="modal-reactivar-personal" />
            @endif
        </x-slot:actions>

        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">DNI</dt>
                <dd class="mt-1 text-sm text-text">{{ $personal->dni() }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Teléfono</dt>
                <dd class="mt-1 text-sm text-text">{{ $personal->telefono() ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Fecha de ingreso</dt>
                <dd class="mt-1 text-sm text-text">{{ $personal->fechaIngreso()?->format('d/m/Y') ?? 'Sin registrar' }}</dd>
            </div>
        </dl>
    </x-ui.card>

    <x-ui.card>
        <x-pagos.control-periodo :control-periodo="$controlPeriodo" :trabajador-id="$personal->userId()" :trabajador-nombre="$personal->nombreCompleto()" />
    </x-ui.card>

    <x-ui.modal id="modal-editar-personal" title="Editar personal de ventas">
        <form method="POST" action="{{ route('admin.personal-ventas.update', $personal->id()) }}" class="space-y-4">
            @csrf
            @method('PATCH')
            <x-forms.input name="nombres" label="Nombres" :value="$personal->nombres()" required />
            <x-forms.input name="apellidos" label="Apellidos" :value="$personal->apellidos()" required />
            <x-forms.input name="telefono" label="Teléfono" :value="$personal->telefono()" />
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar cambios</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal id="modal-desactivar-personal" title="Desactivar personal de ventas">
        <p class="text-sm text-text">¿Desactivar a <strong>{{ $personal->nombreCompleto() }}</strong>?</p>
        <p class="mt-2 text-xs text-text-muted">No se elimina: el registro y su historial se conservan, pero dejará de estar disponible para operar. Podrás reactivarlo cuando quieras.</p>
        <form method="POST" action="{{ route('admin.personal-ventas.desactivar', $personal->id()) }}" class="mt-4 flex justify-end gap-2">
            @csrf
            @method('PATCH')
            <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
            <x-ui.button type="submit" variant="danger">Sí, desactivar</x-ui.button>
        </form>
    </x-ui.modal>

    <x-ui.modal id="modal-reactivar-personal" title="Reactivar personal de ventas">
        <p class="text-sm text-text">¿Reactivar a <strong>{{ $personal->nombreCompleto() }}</strong>?</p>
<p class="mt-2 text-xs text-text-muted">Volverá a estar disponible para operar con normalidad.</p>
        <form method="POST" action="{{ route('admin.personal-ventas.reactivar', $personal->id()) }}" class="mt-4 flex justify-end gap-2">
            @csrf
            @method('PATCH')
            <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
            <x-ui.button type="submit" variant="secondary">Reactivar</x-ui.button>
        </form>
    </x-ui.modal>
@endsection
