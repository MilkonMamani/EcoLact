@props(['items' => []])

{{-- Ruta de navegación: [['Producción', null], ['Recetas', null]] (el último es la página actual). --}}
<nav aria-label="Ruta de navegación" class="text-xs text-text-muted">
    <ol class="flex flex-wrap items-center gap-1.5">
        @foreach ($items as $indice => [$etiqueta, $enlace])
            <li class="flex items-center gap-1.5">
                @if ($indice > 0)
                    <span aria-hidden="true">›</span>
                @endif
                @if ($enlace && ! $loop->last)
                    <a href="{{ $enlace }}" class="hover:text-primary hover:underline">{{ $etiqueta }}</a>
                @else
                    <span @if ($loop->last) aria-current="page" class="font-medium text-text" @endif>{{ $etiqueta }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
