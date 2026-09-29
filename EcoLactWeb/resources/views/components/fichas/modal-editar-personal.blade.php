@props(['ficha', 'id', 'action', 'entidad'])

{{-- Único formulario de datos personales de una ficha (el DNI y la fecha de ingreso no se editan). --}}
<x-ui.modal :id="$id" title="Editar información personal" :open="$errors->any() && old('_modal') === $id">
    <form method="POST" action="{{ $action }}" class="space-y-4">
        @csrf
        @method('PATCH')
        <input type="hidden" name="_modal" value="{{ $id }}">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-forms.input name="nombres" :id="$id.'-nombres'" label="Nombres" :value="$ficha->nombres" required />
            <x-forms.input name="apellidos" :id="$id.'-apellidos'" label="Apellidos" :value="$ficha->apellidos" required />
            <x-forms.input name="telefono" :id="$id.'-telefono'" label="Teléfono" :value="$ficha->telefono" />
        </div>
        <p class="text-xs text-text-muted">El DNI es el identificador del {{ $entidad }} y no se edita.</p>
        <div class="flex justify-end gap-2 border-t border-text-muted/10 pt-4">
            <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
            <x-ui.button type="submit">Guardar cambios</x-ui.button>
        </div>
    </form>
</x-ui.modal>
