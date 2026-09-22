@extends('layouts.admin')

@section('title', 'Recetas')

@section('content')
    @include('produccion._recetas', ['editable' => true, 'rutaIndex' => 'admin.recetas.index'])
@endsection
