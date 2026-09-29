@extends('layouts.admin')

@section('title', $ruta->nombre())

@section('content')
    <x-ui.button :href="route('admin.zonas.index', ['zona' => $zona->id])" variant="ghost" size="sm" as="a">&larr; {{ $zona->nombre }}</x-ui.button>

    @include('admin.rutas._panel', [
        'ruta' => $ruta,
        'zona' => $zona,
        'detalles' => $detalles,
        'acopiadoresDeLaZona' => $acopiadoresDeLaZona,
        'recorridos' => $recorridos,
    ])
@endsection
