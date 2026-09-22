@extends('layouts.print')

@section('title', 'Liquidación #' . $liquidacion->id() . ' · Imprimir')

@section('content')
    @include('admin.liquidaciones._hoja', ['liquidacion' => $liquidacion, 'detalle' => $detalle, 'periodo' => $periodo])
@endsection
