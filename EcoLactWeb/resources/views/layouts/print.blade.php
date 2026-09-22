<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Imprimir') · EcoLact</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            .no-imprimir { display: none !important; }
            .hoja-liquidacion { page-break-after: always; }
            .hoja-liquidacion:last-child { page-break-after: auto; }
            body { background: #fff !important; }
        }
    </style>
</head>
<body class="bg-background font-sans text-text antialiased print:bg-white">
    <div class="no-imprimir flex items-center justify-between gap-3 border-b border-text-muted/10 bg-surface px-4 py-3">
        <a href="{{ url()->previous() }}" class="text-sm text-text-muted hover:text-text">&larr; Volver</a>
        <button type="button" onclick="window.print()" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90">
            🖨️ Imprimir
        </button>
    </div>

    <main class="mx-auto max-w-3xl px-4 py-6 print:max-w-none print:px-0 print:py-0">
        @yield('content')
    </main>
</body>
</html>
