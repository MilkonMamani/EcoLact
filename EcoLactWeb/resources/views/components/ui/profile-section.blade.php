@props(['title', 'icon'])

{{-- Grupo visual de una ficha de detalle: título con icono en círculo suave y su contenido debajo. --}}
<section {{ $attributes }}>
    <h3 class="mb-4 flex items-center gap-2.5 border-b border-text-muted/10 pb-2 text-xs font-semibold uppercase tracking-wide text-text-muted">
        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-primary-soft text-primary-dark">
            <x-ui.icon :name="$icon" class="h-4 w-4" />
        </span>
        {{ $title }}
    </h3>

    {{ $slot }}
</section>
