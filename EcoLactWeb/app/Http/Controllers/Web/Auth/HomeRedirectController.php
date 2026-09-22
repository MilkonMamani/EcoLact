<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Support\SesionWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class HomeRedirectController extends Controller
{
    public function __invoke(Request $request, SesionWeb $sesion): RedirectResponse
    {
        return $sesion->redirigir($request);
    }
}
