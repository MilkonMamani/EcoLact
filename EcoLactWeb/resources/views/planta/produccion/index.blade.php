@extends('layouts.planta')

@section('title', 'Producción')

@section('content')
    @include('produccion._historial', ['prefijo' => 'planta'])
@endsection
