<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pago a trabajador · {{ $c->nombre }} · EcoLact</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            .no-imprimir { display: none !important; }
            body { background: #fff !important; }
            .pp-pantalla { padding: 0 !important; }
        }
    </style>
</head>
<body class="bg-background font-sans text-text antialiased">
    <div class="no-imprimir flex items-center justify-between gap-3 border-b border-text-muted/10 bg-surface px-4 py-3">
        <a href="{{ route('admin.pagos.trabajadores.index', ['trabajador' => $c->obligacionId]) }}" class="text-sm text-text-muted hover:text-text">&larr; Volver a Pagar a trabajadores</a>
        <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark">
            <x-ui.icon name="printer" class="h-4 w-4" /> Imprimir
        </button>
    </div>

    <main class="pp-pantalla overflow-x-auto px-4 py-6">
        <div class="mx-auto shadow-lg" style="width: 794px">
            @include('admin.pagos.trabajadores._comprobante', ['c' => $c])
        </div>
    </main>
</body>
</html>
