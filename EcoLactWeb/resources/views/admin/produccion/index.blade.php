@extends('layouts.admin')

@section('title', 'Producción')

@section('content')
    @include('produccion._historial', ['prefijo' => 'admin'])
@endsection
