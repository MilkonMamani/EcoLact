@props(['paginator', 'noun' => 'registros'])

@php
    /*
     * Pie de tabla compacto: "Mostrando X a Y de Z" + « ‹ 1 2 3 4 5 … N › ».
     * Todo sale del paginador real; los enlaces conservan la query string
     * si el paginador se creó con withQueryString().
     */
    $actual = $paginator->currentPage();
    $ultima = max(1, $paginator->lastPage());
    $inicio = max(1, min($actual - 2, $ultima - 4));
    $fin = min($ultima, $inicio + 4);
    $paginas = range($inicio, $fin);

    if ($inicio > 1) {
        array_unshift($paginas, ...($inicio > 2 ? [1, null] : [1]));
    }

    if ($fin < $ultima) {
        array_push($paginas, ...($fin < $ultima - 1 ? [null, $ultima] : [$ultima]));
    }

    $controles = [
        ['«', 'Primera página', $actual > 1 ? $paginator->url(1) : null],
        ['‹', 'Página anterior', $paginator->previousPageUrl()],
    ];
    $siguientes = [
        ['›', 'Página siguiente', $paginator->nextPageUrl()],
        ['»', 'Última página', $actual < $ultima ? $paginator->url($ultima) : null],
    ];
@endphp

<div {{ $attributes->class(['ecolact-pager']) }}>
    <p class="ecolact-pager-info">
        @if ($paginator->total() === 0)
            Sin {{ $noun }}
        @else
            Mostrando {{ $paginator->firstItem() ?? 0 }} a {{ $paginator->lastItem() ?? 0 }} de {{ number_format($paginator->total()) }} {{ $noun }}
        @endif
    </p>

    <nav class="ecolact-pager-links" aria-label="Paginación">
        @foreach ($controles as [$simbolo, $etiqueta, $url])
            @if ($url)
                <a href="{{ $url }}" aria-label="{{ $etiqueta }}" data-pager-link>{{ $simbolo }}</a>
            @else
                <span aria-hidden="true" class="is-disabled">{{ $simbolo }}</span>
            @endif
        @endforeach

        @foreach ($paginas as $pagina)
            @if ($pagina === null)
                <span class="is-gap" aria-hidden="true">…</span>
            @elseif ($pagina === $actual)
                <span aria-current="page" class="is-active">{{ $pagina }}</span>
            @else
                <a href="{{ $paginator->url($pagina) }}" aria-label="Página {{ $pagina }}" data-pager-link>{{ $pagina }}</a>
            @endif
        @endforeach

        @foreach ($siguientes as [$simbolo, $etiqueta, $url])
            @if ($url)
                <a href="{{ $url }}" aria-label="{{ $etiqueta }}" data-pager-link>{{ $simbolo }}</a>
            @else
                <span aria-hidden="true" class="is-disabled">{{ $simbolo }}</span>
            @endif
        @endforeach
    </nav>
</div>
