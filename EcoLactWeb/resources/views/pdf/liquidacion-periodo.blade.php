<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sobres del periodo {{ $periodo->fecha_inicio->format('d/m/Y') }}</title>
    <style>
        @page { margin: 0; }
        body { margin: 0; }
    </style>
</head>
<body>
    @if (count($hojas) === 0)
        <div style="padding: 24px; font-family: DejaVu Sans, sans-serif;">
            No hay liquidaciones pendientes de pago en este periodo
            {{ $zona ? ' para la zona '.$zona->nombre : '' }}.
        </div>
    @else
        @foreach ($hojas as $hoja)
            @include('pdf._liquidacion_hoja', [
                'liquidacion' => $hoja['liquidacion'],
                'detalle' => $hoja['detalle'],
                'periodo' => $periodo,
                'ultimaHoja' => $loop->last,
            ])
        @endforeach
    @endif
</body>
</html>
