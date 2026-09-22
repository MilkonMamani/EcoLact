@props([
    'type',
    'label' => null,
    'subject' => null,
    'href' => null,
    'text' => false,
    'submit' => false,
])

@php
    /*
     * Lenguaje visual ÚNICO de acciones de EcoLact (CRUD y afines): cada acción tiene un icono
     * y un color fijos, iguales en todas las pantallas. Si necesitas una acción nueva, agrégala
     * aquí y (si hace falta) su icono en x-ui.icon; no repitas estilos en las vistas.
     *
     *   create ........ + turquesa          location ...... pin turquesa
     *   view .......... ojo azul            download ...... descarga azul
     *   edit .......... lápiz amarillo      print ......... impresora gris
     *   delete ........ papelera roja       pay ........... billetes verde
     *   deactivate .... user-x rojo         activate ...... user-check verde
     *   unlink ........ user-minus rojo suave (quita una relación; NO elimina el registro)
     *   register-payment  tarjeta turquesa (confirma la entrega del dinero de un pago pendiente)
     *   detail ........ ojo turquesa con borde (ver detalle de un registro que ya no se edita)
     */
    $definiciones = [
        'create' => ['icon' => 'plus', 'label' => 'Nuevo', 'color' => 'bg-primary text-white'],
        'view' => ['icon' => 'eye', 'label' => 'Ver', 'color' => 'bg-info text-white'],
        'edit' => ['icon' => 'pencil', 'label' => 'Editar', 'color' => 'bg-warning text-text'],
        'delete' => ['icon' => 'trash', 'label' => 'Eliminar', 'color' => 'bg-danger text-white'],
        'deactivate' => ['icon' => 'user-x', 'label' => 'Desactivar', 'color' => 'bg-danger text-white'],
        'activate' => ['icon' => 'user-check', 'label' => 'Activar', 'color' => 'bg-success text-white'],
        'unlink' => ['icon' => 'user-minus', 'label' => 'Quitar', 'color' => 'bg-danger/10 text-danger ring-1 ring-inset ring-danger/30'],
        'location' => ['icon' => 'map-pin', 'label' => 'Ver ubicación', 'color' => 'bg-primary text-white'],
        'download' => ['icon' => 'download', 'label' => 'Descargar', 'color' => 'bg-info text-white'],
        'print' => ['icon' => 'printer', 'label' => 'Imprimir', 'color' => 'bg-text-muted text-white'],
        'pay' => ['icon' => 'banknotes', 'label' => 'Pagar', 'color' => 'bg-success text-white'],
        'detail' => ['icon' => 'eye', 'label' => 'Ver detalle', 'color' => 'bg-surface text-primary ring-1 ring-inset ring-primary hover:bg-primary-soft'],
        'register-payment' => ['icon' => 'credit-card', 'label' => 'Registrar pago', 'color' => 'bg-primary text-white'],
        // Acciones de navegación propias de un módulo (mismo tamaño y tooltip, color de "ver").
        'analyze' => ['icon' => 'beaker', 'label' => 'Analizar', 'color' => 'bg-primary text-white'],
        'continue' => ['icon' => 'play', 'label' => 'Continuar', 'color' => 'bg-primary text-white'],
        'manage' => ['icon' => 'external-link', 'label' => 'Gestionar', 'color' => 'bg-info text-white'],
        'prices' => ['icon' => 'currency', 'label' => 'Precios', 'color' => 'bg-info text-white'],
        'recipe' => ['icon' => 'clipboard-list', 'label' => 'Receta', 'color' => 'bg-info text-white'],
    ];

    $definicion = $definiciones[$type] ?? throw new InvalidArgumentException("Tipo de acción desconocido: {$type}");

    $etiqueta = $label ?? $definicion['label'];
    $etiquetaAccesible = trim($etiqueta.' '.($subject ?? ''));

    $forma = $text
        ? 'gap-2 rounded-lg px-3 py-1.5 text-xs font-medium'
        : 'h-8 w-8 shrink-0 rounded-lg';

    $clases = "inline-flex items-center justify-center transition duration-150 hover:brightness-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:brightness-100 {$forma} {$definicion['color']}";

    $etiquetaTarget = $attributes->get('target') === '_blank' ? 'noopener noreferrer' : null;
@endphp

@if ($href)
    <a
        href="{{ $href }}"
        title="{{ $etiquetaAccesible }}"
        aria-label="{{ $etiquetaAccesible }}"
        @if ($etiquetaTarget) rel="{{ $etiquetaTarget }}" @endif
        {{ $attributes->class($clases) }}
    >
        <x-ui.icon :name="$definicion['icon']" class="{{ $text ? 'h-4 w-4' : 'h-[1.125rem] w-[1.125rem]' }}" />
        @if ($text)
            <span>{{ $etiqueta }}</span>
        @endif
    </a>
@else
    <button
        type="{{ $submit ? 'submit' : 'button' }}"
        title="{{ $etiquetaAccesible }}"
        aria-label="{{ $etiquetaAccesible }}"
        {{ $attributes->class($clases) }}
    >
        <x-ui.icon :name="$definicion['icon']" class="{{ $text ? 'h-4 w-4' : 'h-[1.125rem] w-[1.125rem]' }}" />
        @if ($text)
            <span>{{ $etiqueta }}</span>
        @endif
    </button>
@endif
