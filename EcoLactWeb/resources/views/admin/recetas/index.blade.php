@extends('layouts.admin')

@section('title', 'Recetas')
@section('body-class', 'admin-fichas-page')

@section('content')
    @include('produccion._recetas', ['editable' => true, 'rutaIndex' => 'admin.recetas.index'])
@endsection
