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

// Falta automática: a quien no marcó asistencia (QR o manual) hoy, se le
// marca FALTA a las 12:05 hora de Perú (hora límite de asistencia: 12:00,
// con 5 minutos de margen para evitar problemas de simultaneidad).
// ->timezone() corre este horario en America/Lima sin cambiar el timezone
// global de la app (config('app.timezone') se queda en UTC, que es lo que
// esperan now()/today() en el resto del sistema).
Schedule::command('ecolact:marcar-faltas')
    ->dailyAt('12:05')
    ->timezone('America/Lima')
    ->withoutOverlapping()
    ->onOneServer();

// Cierre de nómina: el día 1 de cada mes a las 00:05 hora de Perú, cierra el
// mes anterior del personal (EN_CURSO -> PENDIENTE) y abre el mes actual.
Schedule::command('ecolact:cerrar-nomina-mensual')
    ->monthlyOn(1, '00:05')
    ->timezone('America/Lima')
    ->withoutOverlapping()
    ->onOneServer();
