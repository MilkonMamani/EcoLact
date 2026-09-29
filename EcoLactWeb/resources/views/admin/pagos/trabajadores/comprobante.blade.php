<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- El título es el nombre sugerido del archivo al "Guardar como PDF". --}}
    <title>Comprobante {{ $c->numero() }} - {{ $c->nombre }}</title>
    @fonts
    @vite(['resources/css/app.css'])
    {{--
        A4 vertical solo para este documento (no afecta la impresión de otras pantallas). Sin margen
        de página: los márgenes los da la propia hoja (210 × 297 mm), así el navegador no agrega su
        cabecera/pie ni parte el comprobante en dos hojas.
    --}}
    <style>@page { size: A4 portrait; margin: 0; }</style>
</head>
{{--
    Página imprimible del comprobante (admin.pagos.trabajadores.comprobante): un documento
    suelto, sin layout del admin (no hay sidebar ni navegación que ocultar). La barra superior
    solo existe en pantalla; al imprimir sale únicamente la hoja A4 (ver @media print en
    comprobante-pago.css). El panel de Pagos a trabajadores la carga en un iframe oculto e
    imprime exactamente esta hoja.
--}}
<body class="cp-pagina">
    <div class="cp-toolbar" data-comprobante-toolbar>
        <a href="{{ route('admin.pagos.trabajadores.index', ['trabajador' => $c->obligacionId]) }}">&larr; Volver a Pagos a trabajadores</a>
        <button type="button" onclick="window.print()">{{ $c->estaPagado() ? 'Imprimir comprobante' : 'Imprimir vista previa' }}</button>
    </div>

    <main class="cp-contenedor">
        @include('admin.pagos.trabajadores._comprobante', ['c' => $c])
    </main>
</body>
</html>
