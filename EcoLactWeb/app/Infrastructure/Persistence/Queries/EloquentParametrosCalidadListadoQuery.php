<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Calidad\Contracts\ParametrosCalidadListadoQuery;
use App\Infrastructure\Persistence\Models\ParametroCalidadModel;
use App\Infrastructure\Persistence\Models\ReglaCalidadModel;

class EloquentParametrosCalidadListadoQuery implements ParametrosCalidadListadoQuery
{
    public function listar(): array
    {
        return ParametroCalidadModel::query()
            ->with('reglaVigente')
            ->withCount('reglasCalidad')
            ->orderBy('nombre')
            ->get()
            ->map(fn (ParametroCalidadModel $parametro): array => $this->datosParametro($parametro))
            ->all();
    }

    public function panel(int $parametroId): ?array
    {
        $parametro = ParametroCalidadModel::query()
            ->with('reglaVigente')
            ->withCount('reglasCalidad')
            ->find($parametroId);

        return $parametro ? $this->datosParametro($parametro) : null;
    }

    /**
     * @return array{id: int, codigo: string, nombre: string, descripcion: string|null, unidad: string|null, estado: string, actualizado: string|null, tieneHistorial: bool, reglaActual: array<string, mixed>|null}
     */
    private function datosParametro(ParametroCalidadModel $parametro): array
    {
        return [
            'id' => $parametro->id,
            'codigo' => $parametro->codigo,
            'nombre' => $parametro->nombre,
            'descripcion' => $parametro->descripcion,
            'unidad' => $parametro->unidad,
            'estado' => $parametro->estado,
            'actualizado' => ($parametro->updated_at ?? $parametro->created_at)?->format('d/m/Y'),
            'tieneHistorial' => $parametro->reglas_calidad_count > 0,
            'reglaActual' => $parametro->reglaVigente
                ? $this->datosRegla($parametro->reglaVigente, $parametro->unidad)
                : null,
        ];
    }

    /**
     * @return array{id: int, valorMinimo: string|null, valorMaximo: string|null, rango: string, resultadoIncumplimiento: string, fechaInicio: string, fechaFin: string|null, estado: string}
     */
    private function datosRegla(ReglaCalidadModel $regla, ?string $unidad): array
    {
        $minimo = $this->numero($regla->valor_minimo);
        $maximo = $this->numero($regla->valor_maximo);

        return [
            'id' => $regla->id,
            'valorMinimo' => $minimo,
            'valorMaximo' => $maximo,
            'rango' => $this->rango($minimo, $maximo, $unidad),
            'resultadoIncumplimiento' => $regla->resultado_incumplimiento,
            'fechaInicio' => $regla->fecha_inicio->format('Y-m-d'),
            'fechaFin' => $regla->fecha_fin?->format('Y-m-d'),
            'estado' => $regla->estado,
        ];
    }

    /** 1022.0000 → "1022", 1.0280 → "1.028" (sin ceros de relleno del decimal(…, 4)). */
    private function numero(mixed $valor): ?string
    {
        return $valor === null ? null : rtrim(rtrim(number_format((float) $valor, 4, '.', ''), '0'), '.');
    }

    /**
     * "2 – 7 %", "1022 – 1035", "≥ 3.2 %". Solo los símbolos (%, °C) acompañan al número:
     * unidades como g/mL o pH ya se leen en su propia columna.
     */
    private function rango(?string $minimo, ?string $maximo, ?string $unidad): string
    {
        $sufijo = $unidad !== null && preg_match('/^(%|°\S*)$/u', $unidad) ? ' '.$unidad : '';

        return match (true) {
            $minimo !== null && $maximo !== null => "{$minimo} – {$maximo}{$sufijo}",
            $minimo !== null => "≥ {$minimo}{$sufijo}",
            $maximo !== null => "≤ {$maximo}{$sufijo}",
            default => 'Sin límites',
        };
    }
}
