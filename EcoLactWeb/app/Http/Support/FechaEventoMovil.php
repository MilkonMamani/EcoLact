<?php

namespace App\Http\Support;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Hora REAL de un evento registrado en el teléfono (`fecha_hora_evento`), p. ej.
 * un acopio hecho sin cobertura a las 09:35 y sincronizado a las 11:00. Se
 * guarda en `entregas.fecha_hora` (la hora del acopio); la llegada al servidor
 * queda en `created_at`. Nunca se usa la hora de sincronización como hora del
 * acopio.
 *
 * Contrato: ISO 8601 con desfase obligatorio ("2026-10-27T09:35:12-05:00" o
 * "…Z"), para que 09:35 de Perú nunca se lea como 09:35 UTC. Límites en
 * config/acopio.php: no más futura que el desfase de reloj tolerado y no más
 * antigua que el máximo de un registro offline.
 *
 * Solo aplica a la API móvil; los formularios Web siguen con sus reglas.
 */
final class FechaEventoMovil
{
    /** Termina en "Z" o en un desfase "+hh:mm" / "-hh:mm". */
    private const REGEX_CON_DESFASE = '/(Z|[+-]\d{2}:?\d{2})$/';

    public static function maxDesfaseRelojMinutos(): int
    {
        return (int) config('acopio.registro_movil.max_desfase_reloj_minutos');
    }

    public static function maxDiasAtraso(): int
    {
        return (int) config('acopio.registro_movil.max_dias_atraso');
    }

    /** @return array<int, string> */
    public static function reglas(): array
    {
        return [
            'nullable',
            'date',
            'regex:'.self::REGEX_CON_DESFASE,
            'before_or_equal:'.now()->addMinutes(self::maxDesfaseRelojMinutos())->toIso8601String(),
            'after_or_equal:'.now()->subDays(self::maxDiasAtraso())->toIso8601String(),
        ];
    }

    /** @return array<string, string> */
    public static function mensajes(string $campo): array
    {
        return [
            "{$campo}.date" => 'La fecha y hora del registro no tiene un formato válido.',
            "{$campo}.regex" => 'La fecha y hora del registro debe incluir la zona horaria (por ejemplo -05:00).',
            "{$campo}.before_or_equal" => 'La fecha y hora del registro no puede estar en el futuro. Revisa la hora del teléfono.',
            "{$campo}.after_or_equal" => sprintf('El registro tiene más de %d días: debe registrarlo el administrador.', self::maxDiasAtraso()),
        ];
    }

    /**
     * Se lleva a la zona de la app (UTC, convenio del proyecto) porque la base
     * guarda la hora sin desfase: "09:35-05:00" se guarda como 14:35 UTC y se
     * vuelve a mostrar como 09:35 en Perú.
     */
    public static function parse(?string $valor): ?DateTimeImmutable
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (new DateTimeImmutable($valor))->setTimezone(new DateTimeZone(config('app.timezone')));
    }
}
