<?php

use App\Application\Pago\VentanaAsistencia;
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

// Cierre de asistencia (regla única, App\Application\Pago\VentanaAsistencia):
// la asistencia del día se registra o corrige solo antes de las 07:00 hora de
// Perú; a las 07:00 el día queda cerrado y a quien debía asistir y no registró
// nada se le marca FALTA automática. Idempotente: si corre más de una vez (o si
// una pantalla ya cerró el día al abrirse) nunca duplica.
// ->timezone() corre este horario en la zona del negocio sin cambiar el
// timezone global de la app (config('app.timezone') se queda en UTC).
Schedule::command('ecolact:marcar-faltas')
    ->dailyAt(VentanaAsistencia::HORA_CIERRE)
    ->timezone(config('app.zona_horaria_negocio'))
    ->withoutOverlapping()
    ->onOneServer();

// Cierre de nómina: el día 1 de cada mes a las 00:05 hora de Perú, cierra el
// mes anterior del personal (EN_CURSO -> PENDIENTE) y abre el mes actual.
Schedule::command('ecolact:cerrar-nomina-mensual')
    ->monthlyOn(1, '00:05')
    ->timezone('America/Lima')
    ->withoutOverlapping()
    ->onOneServer();
