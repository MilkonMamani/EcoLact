<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Liquidación #{{ $liquidacion->id() }}</title>
    <style>
        @page { margin: 0; }
        body { margin: 0; }
    </style>
</head>
<body>
    @include('pdf._liquidacion_hoja', ['liquidacion' => $liquidacion, 'detalle' => $detalle, 'periodo' => $periodo, 'ultimaHoja' => true])
</body>
</html>
