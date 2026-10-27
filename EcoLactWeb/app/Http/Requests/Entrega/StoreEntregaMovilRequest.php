<?php

namespace App\Http\Requests\Entrega;

use App\Http\Support\FechaEventoMovil;

/**
 * Registro de entrega desde la app móvil del acopiador, que puede llegar
 * horas después del acopio (cola offline). Además de las reglas de
 * StoreEntregaRequest:
 * - `fecha_hora_evento`: hora real del acopio capturada en el teléfono al
 *   pulsar "Guardar" (ISO 8601 con desfase, ver FechaEventoMovil). Se acepta
 *   también `fecha_hora` por compatibilidad con versiones anteriores de la
 *   app; si no llega ninguna, se usa la hora del servidor.
 * - la cabecera `Idempotency-Key` (id local de la operación) es opcional,
 *   pero si llega se valida: es la que impide duplicar un reintento.
 */
class StoreEntregaMovilRequest extends StoreEntregaRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'fecha_hora_evento' => FechaEventoMovil::reglas(),
            'fecha_hora' => FechaEventoMovil::reglas(),
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            ...FechaEventoMovil::mensajes('fecha_hora_evento'),
            ...FechaEventoMovil::mensajes('fecha_hora'),
            'idempotency_key.max' => 'La clave de idempotencia no puede superar 64 caracteres.',
        ];
    }

    /** Hora del acopio: la del teléfono si llegó; null → RegistrarEntrega usa la hora del servidor. */
    public function fechaHoraEvento(): ?string
    {
        return $this->validated('fecha_hora_evento') ?? $this->validated('fecha_hora');
    }
}
