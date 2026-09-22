@props(['paginator' => null])

@if ($paginator && method_exists($paginator, 'hasPages') && $paginator->hasPages())
    <nav class="mt-4 flex items-center justify-between gap-3 border-t border-text-muted/10 pt-4 text-sm" aria-label="Paginación">
        <p class="text-text-muted">
            Mostrando {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }}
        </p>

        <div class="flex items-center gap-2">
            @if ($paginator->onFirstPage())
                <span class="rounded-lg px-3 py-1.5 text-text-muted/50">Anterior</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="rounded-lg px-3 py-1.5 text-text hover:bg-text-muted/10">Anterior</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="rounded-lg px-3 py-1.5 text-text hover:bg-text-muted/10">Siguiente</a>
            @else
                <span class="rounded-lg px-3 py-1.5 text-text-muted/50">Siguiente</span>
            @endif
        </div>
    </nav>
@endif
