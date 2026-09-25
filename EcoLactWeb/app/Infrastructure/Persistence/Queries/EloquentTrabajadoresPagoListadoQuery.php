<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Pago\ComprobantePagoTrabajador;
use App\Application\Pago\Contracts\TrabajadoresPagoListadoQuery;
use App\Application\Pago\TrabajadoresPagoFiltro;
use App\Application\Pago\TrabajadorPagoFila;
use App\Domain\Pago\JornadaPersonal;
use App\Domain\Pago\PeriodoPago;
use App\Domain\Shared\Enums\EstadoJornada;
use App\Domain\Shared\Enums\EstadoObligacionPago;
use App\Domain\Shared\Enums\MetodoPago;
use App\Domain\Shared\Enums\PeriodicidadPago;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Domain\Trabajador\TrabajadorRepository;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentTrabajadoresPagoListadoQuery implements TrabajadoresPagoListadoQuery
{
    private const FICHAS = ['user.acopiador', 'user.analistaCalidad', 'user.encargadoPlanta', 'user.personalVentas'];

    /** Tablas de ficha del personal, cada una con user_id y dni. */
    private const TABLAS_FICHA = ['acopiadores', 'analistas_calidad', 'encargados_planta', 'personal_ventas'];

    public function __construct(private readonly TrabajadorRepository $trabajadores) {}

    public function obtener(TrabajadoresPagoFiltro $filtro): LengthAwarePaginator
    {
        return $this->consulta($filtro)
            ->paginate($filtro->porPagina)
            ->withQueryString()
            ->through(fn (ObligacionPagoModel $obligacion): TrabajadorPagoFila => $this->fila($obligacion));
    }

    public function buscarFila(int $obligacionId): ?TrabajadorPagoFila
    {
        $obligacion = $this->base()->whereKey($obligacionId)->first();

        return $obligacion ? $this->fila($obligacion) : null;
    }

    public function comprobante(int $obligacionId): ?ComprobantePagoTrabajador
    {
        $obligacion = $this->base()->whereKey($obligacionId)->first();

        if ($obligacion === null) {
            return null;
        }

        $fila = $this->fila($obligacion);
        $pago = $obligacion->pago;

        return new ComprobantePagoTrabajador(
            obligacionId: $fila->id,
            userId: $fila->userId,
            nombre: $fila->nombre,
            dni: $fila->dni,
            rol: $fila->rol,
            periodoEtiqueta: $fila->periodoEtiqueta,
            periodoInicio: $fila->periodoInicioIso,
            periodoRango: $fila->periodoRango,
            diasTrabajados: $fila->diasTrabajados,
            faltas: $fila->faltas,
            valorDia: $fila->valorDia,
            sueldoBase: $fila->sueldoBase,
            acumulado: $fila->acumulado,
            descuentoPorFaltas: $fila->esMesCompleto ? max(0.0, round($fila->sueldoBase - $fila->acumulado, 2)) : 0.0,
            esMesCompleto: $fila->esMesCompleto,
            fechaIngresoEtiqueta: $fila->fechaIngresoEtiqueta,
            bonificacion: $fila->bonificacion,
            descuento: $fila->descuento,
            total: $fila->total,
            estado: $fila->estado,
            observacion: $pago?->observacion,
            pagadoEl: $fila->pagadoEl,
            pagadoPor: $fila->registradoPor,
            metodoPago: $fila->metodoPago,
            anioFirma: CarbonImmutable::instance($obligacion->periodo_inicio)->year,
        );
    }

    private function consulta(TrabajadoresPagoFiltro $filtro): Builder
    {
        return $this->base()
            ->when($filtro->busqueda !== null, function (Builder $query) use ($filtro): void {
                $patron = '%'.$filtro->busqueda.'%';
                $userIdsPorDni = $this->userIdsPorDni($filtro->busqueda);

                $query->where(function (Builder $grupo) use ($patron, $userIdsPorDni): void {
                    $grupo->where('beneficiario_nombre', 'like', $patron);

                    if ($userIdsPorDni !== []) {
                        $grupo->orWhereIn('user_id', $userIdsPorDni);
                    }
                });
            })
            ->when($filtro->rolId !== null, fn (Builder $query) => $query->where('rol_id', $filtro->rolId))
            ->when($filtro->periodo !== null, function (Builder $query) use ($filtro): void {
                $mes = CarbonImmutable::createFromFormat('Y-m-d', $filtro->periodo.'-01');
                $query->whereBetween('periodo_inicio', [$mes->toDateString(), $mes->endOfMonth()->toDateString()]);
            })
            ->when($filtro->estado !== null, fn (Builder $query) => $query->where('estado', $filtro->estado->value))
            ->orderByRaw("CASE estado WHEN 'PENDIENTE' THEN 0 WHEN 'EN_CURSO' THEN 1 ELSE 2 END")
            ->orderBy('beneficiario_nombre');
    }

    private function base(): Builder
    {
        return ObligacionPagoModel::query()
            ->where('tipo_beneficiario', TipoBeneficiarioPago::PERSONAL->value)
            ->with(['rol', 'pago.registradoPor', ...self::FICHAS]);
    }

    private function fila(ObligacionPagoModel $obligacion): TrabajadorPagoFila
    {
        $base = $obligacion->base_calculo ?? [];
        $periodicidad = PeriodicidadPago::from($obligacion->periodicidad);
        $inicio = DateTimeImmutable::createFromInterface($obligacion->periodo_inicio);
        $fin = DateTimeImmutable::createFromInterface($obligacion->periodo_fin);
        $pago = $obligacion->pago;
        $sueldoBase = (float) ($base['sueldo_base'] ?? ((float) $obligacion->monto_calculado - (float) $obligacion->bonificacion + (float) $obligacion->descuento));
        // Filas PAGADAS anteriores a esta columna (acumulado === null): se
        // asume que todo el sueldo ya estaba acumulado, como pagaba el modelo viejo.
        $valorDia = (float) ($obligacion->valor_dia ?? round($sueldoBase / JornadaPersonal::DIAS_BASE_PERIODO, 2));
        $acumulado = (float) ($obligacion->acumulado ?? $sueldoBase);
        $trabajador = $this->trabajadores->buscarPorUserId((int) $obligacion->user_id);
        $esMesCompleto = $trabajador?->estuvoVigenteTodoElRango($inicio, $fin) ?? true;
        $faltas = $trabajador !== null
            ? DB::table('jornadas_personal')
                ->where('trabajador_id', $trabajador->id())
                ->where('estado', EstadoJornada::FALTA->value)
                ->whereBetween('fecha', [$inicio->format('Y-m-d'), $fin->format('Y-m-d')])
                ->count()
            : 0;

        return new TrabajadorPagoFila(
            id: $obligacion->id,
            userId: (int) $obligacion->user_id,
            nombre: $obligacion->beneficiario_nombre,
            iniciales: $this->iniciales($obligacion->beneficiario_nombre),
            rol: $obligacion->rol?->nombre ?? ($base['rol'] ?? 'Personal'),
            dni: $this->dni($obligacion),
            periodoEtiqueta: PeriodoPago::etiquetaDe($periodicidad, $inicio),
            periodoRango: $inicio->format('d/m/Y').' – '.$fin->format('d/m/Y'),
            periodoInicioIso: $obligacion->periodo_inicio->format('Y-m-d'),
            sueldoBase: $sueldoBase,
            acumulado: $acumulado,
            bonificacion: (float) $obligacion->bonificacion,
            descuento: (float) $obligacion->descuento,
            total: (float) $obligacion->monto_calculado,
            estado: $obligacion->estado,
            estadoEtiqueta: EstadoObligacionPago::from($obligacion->estado)->etiqueta(),
            observacion: $pago?->observacion,
            diasTrabajados: (int) $obligacion->dias_trabajados,
            faltas: $faltas,
            valorDia: $valorDia,
            esMesCompleto: $esMesCompleto,
            fechaIngresoEtiqueta: $esMesCompleto ? null : $trabajador?->fechaIngreso()->format('d/m/Y'),
            pagadoEl: $pago?->fecha_pago?->format('d/m/Y'),
            metodoPago: $pago !== null ? MetodoPago::from($pago->metodo_pago)->etiqueta() : null,
            registradoPor: $pago?->registradoPor?->username,
        );
    }

    /**
     * user_id de cada ficha (acopiador, analista de calidad, encargado de
     * planta, personal de ventas) cuyo DNI coincide con el texto buscado.
     *
     * @return list<int>
     */
    private function userIdsPorDni(string $texto): array
    {
        $patron = '%'.$texto.'%';

        return collect(self::TABLAS_FICHA)
            ->flatMap(fn (string $tabla) => DB::table($tabla)->where('dni', 'like', $patron)->pluck('user_id'))
            ->unique()
            ->values()
            ->all();
    }

    private function dni(ObligacionPagoModel $obligacion): ?string
    {
        $usuario = $obligacion->user;

        if ($usuario === null) {
            return null;
        }

        $ficha = collect(['acopiador', 'analistaCalidad', 'encargadoPlanta', 'personalVentas'])
            ->map(fn (string $relacion) => $usuario->{$relacion})
            ->filter()
            ->first();

        return $ficha?->dni;
    }

    private function iniciales(string $nombre): string
    {
        $partes = preg_split('/\s+/', trim($nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $iniciales = collect($partes)->take(2)->map(fn (string $parte) => mb_strtoupper(mb_substr($parte, 0, 1)));

        return $iniciales->implode('') ?: '?';
    }
}
