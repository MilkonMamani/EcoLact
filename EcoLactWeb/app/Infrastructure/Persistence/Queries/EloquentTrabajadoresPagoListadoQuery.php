<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Pago\ComprobantePagoTrabajador;
use App\Application\Pago\Contracts\TrabajadoresPagoListadoQuery;
use App\Application\Pago\TrabajadoresPagoFiltro;
use App\Application\Pago\TrabajadoresPagoResumen;
use App\Application\Pago\TrabajadorPagoFila;
use App\Domain\Auth\Role;
use App\Domain\Pago\JornadaPersonal;
use App\Domain\Pago\PeriodoPago;
use App\Domain\Shared\Enums\EstadoJornada;
use App\Domain\Shared\Enums\EstadoObligacionPago;
use App\Domain\Shared\Enums\MetodoPago;
use App\Domain\Shared\Enums\PeriodicidadPago;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Domain\Trabajador\Trabajador;
use App\Domain\Trabajador\TrabajadorRepository;
use App\Infrastructure\Persistence\Models\ConfiguracionPagoModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\TrabajadorModel;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorConcreto;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Filas de "Pagos a trabajadores". Los montos, días trabajados y estado salen
 * del snapshot de cada obligación (obligaciones_pago); las faltas, de
 * jornadas_personal contando solo los días en que el trabajador estaba vigente
 * (sus periodos de actividad), igual que el calendario y el cálculo del
 * acumulado. Trabajadores y jornadas se cargan por lote para toda la página
 * (contexto()), nunca con una consulta por fila.
 *
 * Todo trabajador vigente en el mes se ve: si no tiene un sueldo propio vigente
 * en Configuración de pagos (el sueldo es por trabajador, no por rol) y no hay
 * obligación que pagar, aparece igual como fila SIN_SUELDO, con su asistencia
 * real y sin montos, en vez de desaparecer de la pantalla.
 *
 * Un pago ya registrado muestra las faltas congeladas en su comprobante
 * (pagos.comprobante_snapshot), no las de hoy: corregir la asistencia después
 * nunca reescribe un comprobante histórico.
 */
final class EloquentTrabajadoresPagoListadoQuery implements TrabajadoresPagoListadoQuery
{
    private const FICHAS = ['user.acopiador', 'user.analistaCalidad', 'user.encargadoPlanta', 'user.personalVentas'];

    /** Tablas de ficha del personal, cada una con user_id y dni. */
    private const TABLAS_FICHA = ['acopiadores', 'analistas_calidad', 'encargados_planta', 'personal_ventas'];

    public function __construct(private readonly TrabajadorRepository $trabajadores) {}

    public function obtener(TrabajadoresPagoFiltro $filtro): LengthAwarePaginator
    {
        // Un mes tiene tantas filas como trabajadores (decenas): se unen en memoria y se pagina aquí.
        $obligaciones = $filtro->soloSinSueldo ? collect() : $this->consulta($filtro)->get();
        $sinSueldo = $filtro->periodo !== null && $filtro->estado === null
            ? $this->trabajadoresSinSueldo($filtro->periodo, $filtro->busqueda, $filtro->rolId)->get()
            : collect();

        $items = $obligaciones->values()->concat($sinSueldo->values());
        $pagina = Paginator::resolveCurrentPage();
        $enPagina = $items->forPage($pagina, $filtro->porPagina)->values();
        $contexto = $this->contexto(
            $enPagina->filter(fn ($item): bool => $item instanceof ObligacionPagoModel),
            $enPagina->filter(fn ($item): bool => $item instanceof TrabajadorModel),
            $filtro->periodo,
        );

        $filas = $enPagina->map(fn (ObligacionPagoModel|TrabajadorModel $item): TrabajadorPagoFila => $item instanceof ObligacionPagoModel
            ? $this->fila($item, $contexto)
            : $this->filaSinSueldo($item, (string) $filtro->periodo, $contexto));

        return (new LengthAwarePaginatorConcreto($filas, $items->count(), $filtro->porPagina, $pagina, ['path' => Paginator::resolveCurrentPath()]))
            ->withQueryString();
    }

    public function resumen(TrabajadoresPagoFiltro $filtro): TrabajadoresPagoResumen
    {
        $totales = $filtro->soloSinSueldo ? null : $this->filtrar(
            ObligacionPagoModel::query()->where('tipo_beneficiario', TipoBeneficiarioPago::PERSONAL->value),
            $filtro,
        )
            ->selectRaw('COUNT(*) AS filas')
            ->selectRaw('SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) AS pendientes', [EstadoObligacionPago::PENDIENTE->value])
            ->selectRaw('SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) AS pagados', [EstadoObligacionPago::PAGADO->value])
            ->selectRaw('SUM(CASE WHEN estado = ? THEN monto_calculado ELSE 0 END) AS total_pendiente', [EstadoObligacionPago::PENDIENTE->value])
            ->toBase()
            ->first();
        $sinSueldo = $filtro->periodo !== null && $filtro->estado === null
            ? $this->trabajadoresSinSueldo($filtro->periodo, $filtro->busqueda, $filtro->rolId)->count()
            : 0;

        return new TrabajadoresPagoResumen(
            trabajadores: (int) ($totales->filas ?? 0) + $sinSueldo,
            pendientes: (int) ($totales->pendientes ?? 0),
            pagados: (int) ($totales->pagados ?? 0),
            totalPendiente: round((float) ($totales->total_pendiente ?? 0), 2),
        );
    }

    public function buscarSinSueldo(int $trabajadorId, string $periodo): ?TrabajadorPagoFila
    {
        $trabajador = $this->trabajadoresSinSueldo($periodo, null, null)->whereKey($trabajadorId)->first();

        return $trabajador ? $this->filaSinSueldo($trabajador, $periodo, $this->contexto(collect(), collect([$trabajador]), $periodo)) : null;
    }

    public function buscarFila(int $obligacionId): ?TrabajadorPagoFila
    {
        $obligacion = $this->base()->whereKey($obligacionId)->first();

        return $obligacion ? $this->fila($obligacion, $this->contexto(collect([$obligacion]), collect(), null)) : null;
    }

    public function comprobante(int $obligacionId): ?ComprobantePagoTrabajador
    {
        $obligacion = $this->base()->whereKey($obligacionId)->first();

        if ($obligacion === null) {
            return null;
        }

        $contexto = $this->contexto(collect([$obligacion]), collect(), null);
        $fila = $this->fila($obligacion, $contexto);
        $pago = $obligacion->pago;
        $trabajador = $contexto['trabajadores'][(int) $obligacion->user_id] ?? null;
        $zonaHoraria = config('app.zona_horaria_negocio');
        // Pagado: se emitió al registrar el pago. Sin pago todavía: es una vista previa emitida hoy.
        $emitidoEl = $pago !== null ? ($pago->pagado_at ?? $pago->created_at) : null;
        $emision = ($emitidoEl !== null ? CarbonImmutable::instance($emitidoEl) : CarbonImmutable::now())->setTimezone($zonaHoraria);

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
            fechaIngresoEtiqueta: $trabajador?->fechaIngreso()->format('d/m/Y') ?? $fila->fechaIngresoEtiqueta,
            bonificacion: $fila->bonificacion,
            descuento: $fila->descuento,
            total: $fila->total,
            estado: $fila->estado,
            observacion: $pago?->observacion,
            pagadoEl: $fila->pagadoEl,
            pagadoPor: $fila->registradoPor,
            metodoPago: $fila->metodoPago,
            zona: $obligacion->user?->acopiador?->zona?->nombre,
            fechaEmision: $emision->format('d/m/Y'),
            fechaHoraEmision: $emision->format('d/m/Y, H:i'),
            numeroOperacion: $pago?->numero_operacion,
        );
    }

    private function consulta(TrabajadoresPagoFiltro $filtro): Builder
    {
        return $this->filtrar($this->base(), $filtro)
            ->orderByRaw("CASE estado WHEN 'PENDIENTE' THEN 0 WHEN 'EN_CURSO' THEN 1 ELSE 2 END")
            ->orderBy('beneficiario_nombre');
    }

    /** Búsqueda, rol, mes y estado sobre obligaciones de personal (sin orden: también sirve para agregados). */
    private function filtrar(Builder $query, TrabajadoresPagoFiltro $filtro): Builder
    {
        return $query
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
            ->when($filtro->estado !== null, fn (Builder $query) => $query->where('estado', $filtro->estado->value));
    }

    private function base(): Builder
    {
        return ObligacionPagoModel::query()
            ->where('tipo_beneficiario', TipoBeneficiarioPago::PERSONAL->value)
            ->with(['rol', 'pago.registradoPor', ...self::FICHAS]);
    }

    /**
     * Trabajadores (con periodos) y fechas de FALTA / PRESENTE de todas las filas
     * recibidas, en dos consultas.
     *
     * @param  Collection<int, ObligacionPagoModel>  $obligaciones
     * @param  Collection<int, TrabajadorModel>  $sinSueldo
     * @return array{trabajadores: array<int, Trabajador>, faltas: array<int, list<string>>, presentes: array<int, list<string>>}
     */
    private function contexto(Collection $obligaciones, Collection $sinSueldo, ?string $periodo): array
    {
        $userIds = $obligaciones->pluck('user_id')->concat($sinSueldo->pluck('user_id'))->map(fn ($id): int => (int) $id)->unique()->values()->all();
        $trabajadores = $this->trabajadores->buscarPorUserIds($userIds);
        $trabajadorIds = array_values(array_map(fn (Trabajador $trabajador): ?int => $trabajador->id(), $trabajadores));

        $desde = $obligaciones->map(fn ($o): string => $o->periodo_inicio->format('Y-m-d'));
        $hasta = $obligaciones->map(fn ($o): string => $o->periodo_fin->format('Y-m-d'));

        if ($periodo !== null && $sinSueldo->isNotEmpty()) {
            [$inicioMes, $finMes] = $this->rangoDelMes($periodo);
            $desde->push($inicioMes);
            $hasta->push($finMes);
        }

        $jornadas = $trabajadorIds === [] || $desde->isEmpty() ? collect() : DB::table('jornadas_personal')
            ->whereIn('trabajador_id', $trabajadorIds)
            ->whereBetween('fecha', [$desde->min(), $hasta->max()])
            ->get(['trabajador_id', 'fecha', 'estado']);

        $porEstado = fn (EstadoJornada $estado): array => $jornadas
            ->where('estado', $estado->value)
            ->groupBy('trabajador_id')
            ->map(fn ($grupo): array => $grupo->map(fn ($jornada): string => substr((string) $jornada->fecha, 0, 10))->values()->all())
            ->all();

        return ['trabajadores' => $trabajadores, 'faltas' => $porEstado(EstadoJornada::FALTA), 'presentes' => $porEstado(EstadoJornada::PRESENTE)];
    }

    /**
     * Trabajadores vigentes en algún día del mes, de un rol remunerable, SIN un sueldo
     * propio (configuraciones_pago.user_id) activo y vigente en algún día de ese mes, y
     * sin obligación en ese mes. El sueldo es por trabajador: que un compañero del mismo
     * rol tenga sueldo no oculta a quien todavía no lo tiene.
     */
    private function trabajadoresSinSueldo(string $periodo, ?string $busqueda, ?int $rolId): Builder
    {
        [$inicioMes, $finMes] = $this->rangoDelMes($periodo);
        $usuariosConSueldo = ConfiguracionPagoModel::query()
            ->where('tipo_beneficiario', TipoBeneficiarioPago::PERSONAL->value)
            ->where('estado', 'ACTIVO')
            ->whereNotNull('user_id')
            ->whereDate('fecha_inicio', '<=', $finMes)
            ->where(fn (Builder $vigencia) => $vigencia->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $inicioMes))
            ->select('user_id');

        return TrabajadorModel::query()
            ->with(['user.role', 'periodos'])
            ->whereNotIn('user_id', $usuariosConSueldo)
            ->whereHas('user', fn (Builder $usuario) => $usuario
                ->whereHas('role', fn (Builder $rol) => $rol->where('es_remunerable', true))
                ->when($rolId !== null, fn (Builder $query) => $query->where('role_id', $rolId)))
            ->whereHas('periodos', fn (Builder $periodoActividad) => $periodoActividad
                ->whereDate('fecha_inicio', '<=', $finMes)
                ->where(fn (Builder $finPeriodo) => $finPeriodo->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $inicioMes)))
            ->whereNotIn('user_id', ObligacionPagoModel::query()
                ->where('tipo_beneficiario', TipoBeneficiarioPago::PERSONAL->value)
                ->whereNotNull('user_id')
                ->whereBetween('periodo_inicio', [$inicioMes, $finMes])
                ->select('user_id'))
            ->when($busqueda !== null, function (Builder $query) use ($busqueda): void {
                $patron = '%'.$busqueda.'%';
                $query->where(fn (Builder $grupo) => $grupo->where('nombres', 'like', $patron)->orWhere('apellidos', 'like', $patron)->orWhere('dni', 'like', $patron));
            })
            ->orderBy('nombres')
            ->orderBy('apellidos');
    }

    /** @param  array{trabajadores: array<int, Trabajador>, faltas: array<int, list<string>>, presentes: array<int, list<string>>}  $contexto */
    private function filaSinSueldo(TrabajadorModel $modelo, string $periodo, array $contexto): TrabajadorPagoFila
    {
        [$inicioMes, $finMes] = $this->rangoDelMes($periodo);
        $inicio = new DateTimeImmutable($inicioMes);
        $fin = new DateTimeImmutable($finMes);
        $trabajador = $contexto['trabajadores'][(int) $modelo->user_id] ?? null;
        $nombre = trim($modelo->nombres.' '.$modelo->apellidos) ?: ($modelo->user?->username ?? 'Sin nombre');
        $esMesCompleto = $trabajador?->estuvoVigenteTodoElRango($inicio, $fin) ?? true;

        return new TrabajadorPagoFila(
            id: 0,
            userId: (int) $modelo->user_id,
            nombre: $nombre,
            iniciales: $this->iniciales($nombre),
            rol: Role::etiquetaDe($modelo->user?->role?->nombre ?? 'Personal'),
            dni: $modelo->dni,
            periodoEtiqueta: PeriodoPago::mesQueContiene($inicio)->etiqueta(),
            periodoRango: $inicio->format('d/m/Y').' – '.$fin->format('d/m/Y'),
            periodoInicioIso: $inicioMes,
            sueldoBase: 0.0,
            bonificacion: 0.0,
            descuento: 0.0,
            total: 0.0,
            estado: TrabajadorPagoFila::SIN_SUELDO,
            estadoEtiqueta: 'Sin sueldo configurado',
            observacion: null,
            pagadoEl: null,
            metodoPago: null,
            registradoPor: null,
            diasTrabajados: $this->contarVigentes($contexto['presentes'], $trabajador, $inicioMes, $finMes),
            faltas: $this->contarVigentes($contexto['faltas'], $trabajador, $inicioMes, $finMes),
            valorDia: 0.0,
            acumulado: 0.0,
            esMesCompleto: $esMesCompleto,
            fechaIngresoEtiqueta: $esMesCompleto ? null : $trabajador?->fechaIngreso()->format('d/m/Y'),
            trabajadorId: (int) $modelo->id,
        );
    }

    /**
     * Jornadas de un trabajador en [inicio, fin] que caen en días en que estaba vigente.
     *
     * @param  array<int, list<string>>  $fechasPorTrabajador
     */
    private function contarVigentes(array $fechasPorTrabajador, ?Trabajador $trabajador, string $inicio, string $fin): int
    {
        if ($trabajador === null) {
            return 0;
        }

        return count(array_filter(
            $fechasPorTrabajador[$trabajador->id()] ?? [],
            fn (string $fecha): bool => $fecha >= $inicio && $fecha <= $fin && $trabajador->estaVigenteEn(new DateTimeImmutable($fecha)),
        ));
    }

    /** @return array{0: string, 1: string} primer y último día (Y-m-d) del mes "Y-m" */
    private function rangoDelMes(string $periodo): array
    {
        $mes = CarbonImmutable::createFromFormat('Y-m-d', $periodo.'-01');

        return [$mes->startOfMonth()->toDateString(), $mes->endOfMonth()->toDateString()];
    }

    /** @param  array{trabajadores: array<int, Trabajador>, faltas: array<int, list<string>>, presentes: array<int, list<string>>}  $contexto */
    private function fila(ObligacionPagoModel $obligacion, array $contexto): TrabajadorPagoFila
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
        $trabajador = $contexto['trabajadores'][(int) $obligacion->user_id] ?? null;
        $esMesCompleto = $trabajador?->estuvoVigenteTodoElRango($inicio, $fin) ?? true;
        $congeladas = $pago?->comprobante_snapshot['faltas'] ?? null;
        $faltas = $congeladas !== null
            ? (int) $congeladas
            : $this->contarVigentes($contexto['faltas'], $trabajador, $inicio->format('Y-m-d'), $fin->format('Y-m-d'));

        return new TrabajadorPagoFila(
            id: $obligacion->id,
            userId: (int) $obligacion->user_id,
            nombre: $obligacion->beneficiario_nombre,
            iniciales: $this->iniciales($obligacion->beneficiario_nombre),
            rol: Role::etiquetaDe($obligacion->rol?->nombre ?? ($base['rol'] ?? 'Personal')),
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
