<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pagos pendientes: sueldo mensual del personal y leche de semanas cerradas de
// proveedores. Idempotente: correrlo todos los días nunca duplica un pago.
// Requiere el cron habitual de Laravel: * * * * * php artisan schedule:run
Schedule::command('ecolact:generar-pagos')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->onOneServer();
