<?php

/*
|--------------------------------------------------------------------------
| Registro de acopio desde el móvil (también sin conexión)
|--------------------------------------------------------------------------
|
| El teléfono envía la hora REAL del acopio (`fecha_hora_evento`, ISO 8601 con
| desfase), que se guarda en `entregas.fecha_hora`; `entregas.created_at` es
| la hora en que llegó al servidor (sincronización). El servidor no confía
| ciegamente en esa hora:
|
| - max_desfase_reloj_minutos: tolerancia para relojes de teléfono
|   adelantados; una hora más futura que esto se rechaza (422).
| - max_dias_atraso: antigüedad máxima de un registro offline. 7 días = un
|   ciclo de pago semanal del proveedor (jueves a miércoles): un acopio más
|   antiguo lo registra el administrador, no la cola del teléfono.
|
*/

return [
    'registro_movil' => [
        'max_desfase_reloj_minutos' => (int) env('ACOPIO_MOVIL_MAX_DESFASE_RELOJ_MINUTOS', 10),
        'max_dias_atraso' => (int) env('ACOPIO_MOVIL_MAX_DIAS_ATRASO', 7),
    ],
];
