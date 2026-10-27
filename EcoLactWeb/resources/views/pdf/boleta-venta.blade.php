<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>EcoLact · Boleta de venta {{ $boleta['codigo'] }}</title>
    <style>
        @page { margin: 28px; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        .boleta { border: 1px solid #d1d5db; border-radius: 8px; padding: 22px 24px; }
        .boleta table { width: 100%; border-collapse: collapse; }
        .boleta td, .boleta th { padding: 3px 0; vertical-align: top; text-align: left; }
        .boleta-cabecera { text-align: center; margin-bottom: 14px; }
        .boleta-cabecera p { margin: 0; }
        .boleta-marca { font-size: 22px; font-weight: bold; color: #2e7d32; }
        .boleta-tipo { font-size: 11px; letter-spacing: 2px; margin-top: 2px; }
        .boleta-codigo { font-size: 13px; font-weight: bold; margin-top: 4px; }
        .boleta-etiqueta { display: block; font-size: 8px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; }
        .boleta-derecha { text-align: right !important; }
        .boleta-separada { border-top: 1px solid #e5e7eb; margin-top: 10px; padding-top: 10px; }
        .boleta-detalle th { font-size: 8px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; background: #f3f4f6; padding: 5px 4px; }
        .boleta-detalle td { padding: 6px 4px; }
        .boleta-total td { font-size: 14px; font-weight: bold; }
        .boleta-total .boleta-derecha { font-size: 18px; color: #1b5e20; }
        .boleta-nota { margin: 6px 0 0; font-size: 9px; color: #6b7280; }
    </style>
</head>
<body>
    @include('ventas.pos._boleta', ['boleta' => $boleta])
</body>
</html>
