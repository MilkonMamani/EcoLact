@extends('layouts.planta')

@section('title', 'Recetas')

@section('content')
    @include('produccion._recetas', ['editable' => false, 'rutaIndex' => 'planta.recetas.index'])
@endsection
