<?php

namespace App\Infrastructure\Mantenimiento;

use App\Application\Acopio\RegistrarEntrega;
use App\Application\Calidad\SeleccionarMuestraCalidad;
use App\Application\Pago\EstadoAsistenciaActual;
use App\Domain\Produccion\Insumo;
use App\Domain\Shared\Enums\MetodoSeleccionMuestreo;
use App\Domain\Shared\Enums\TipoControlMuestreo;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Simulación de acopio del 28/10/2026 (solo desarrollo): los cuatro acopiadores
 * registran por QR las entregas de catorce proveedores YA EXISTENTES de su zona,
 * 12 500 L en total, y la analista de Calidad selecciona como muestra (MANUAL,
 * rutinaria) la entrega del primer proveedor de cada zona.
 *
 * No escribe tablas a mano: cada entrega pasa por RegistrarEntrega (mismas
 * validaciones de proveedor activo, acopiador activo y misma zona; ingreso de
 * leche al inventario y pago pendiente del proveedor en la misma transacción) y
 * cada muestra por SeleccionarMuestraCalidad. No genera resultados de
 * laboratorio: solo las muestras seleccionadas aparecen en "Muestras pendientes"
 * de Calidad; el resto de entregas queda sin análisis, como en el flujo real.
 *
 * Idempotente: cada entrega lleva un token_idempotencia fijo del lote
 * (SIM-ACOPIO-20261028-<dni>-<n>), así que volver a ejecutarla devuelve las
 * entregas y muestras ya creadas en vez de duplicarlas. Las observaciones llevan
 * la marca del lote para auditarlas o limpiarlas.
 */
final class SimuladorAcopio
{
    public const LOTE = 'SIM-ACOPIO-20261028';

    public const FECHA = '2026-10-28';

    public const OBSERVACION = '['.self::LOTE.'] Dato de prueba de la simulación de acopio; no es una entrega real.';

    public const LITROS_TOTALES = 12500.0;

    /**
     * Zonas, acopiadores y entregas (hora local de Lima => litros). El primer
     * proveedor de cada zona queda seleccionado como muestra pendiente.
     */
    private const PLAN = [
        ['zona_id' => 1, 'zona' => 'Collana 1', 'acopiador' => ['Yanil', 'Perez'], 'litros' => 3600, 'proveedores' => [
            ['dni' => '71000032', 'nombre' => 'Aarón Pérez Huanca', 'muestra' => '09:15', 'entregas' => ['06:12' => 780.00]],
            ['dni' => '71000046', 'nombre' => 'Ainara Cutipa Chambi', 'entregas' => ['06:31' => 640.50, '11:05' => 312.00]],
            ['dni' => '71000096', 'nombre' => 'Ainoa Quisbert Ticona', 'entregas' => ['07:02' => 905.25]],
            ['dni' => '71000055', 'nombre' => 'Alba Cutipa Aquino', 'entregas' => ['07:40' => 538.75, '11:48' => 423.50]],
        ]],
        ['zona_id' => 2, 'zona' => 'Collana 2', 'acopiador' => ['Alicia', 'Ramos'], 'litros' => 3800, 'proveedores' => [
            ['dni' => '71000208', 'nombre' => 'Abril Cutipa Quisbert', 'muestra' => '09:22', 'entregas' => ['06:05' => 865.40]],
            ['dni' => '71000227', 'nombre' => 'Adela Pérez Sucari', 'entregas' => ['06:44' => 1012.60]],
            ['dni' => '71000207', 'nombre' => 'Adelardo Quisbert Choque', 'entregas' => ['07:18' => 702.30, '12:10' => 398.70]],
            ['dni' => '71000197', 'nombre' => 'Adelia Pérez Quispe', 'entregas' => ['07:55' => 821.00]],
        ]],
        ['zona_id' => 3, 'zona' => 'Faon', 'acopiador' => ['Yoel', 'Turpo'], 'litros' => 3300, 'proveedores' => [
            ['dni' => '71000347', 'nombre' => 'Adolfo Pérez Calcina', 'muestra' => '09:31', 'entregas' => ['06:20' => 690.80]],
            ['dni' => '71000253', 'nombre' => 'Adriana Cutipa Mendoza', 'entregas' => ['06:58' => 574.20, '11:30' => 360.00]],
            ['dni' => '71000334', 'nombre' => 'África Cutipa Ccallo', 'entregas' => ['07:35' => 948.50]],
            ['dni' => '71000251', 'nombre' => 'Ágata Pérez Castro', 'entregas' => ['08:10' => 726.50]],
        ]],
        ['zona_id' => 4, 'zona' => 'Yasin', 'acopiador' => ['Lenin', 'Murga'], 'litros' => 1800, 'proveedores' => [
            ['dni' => '71000475', 'nombre' => 'Abraham Cutipa Pérez', 'muestra' => '09:40', 'entregas' => ['06:40' => 835.60]],
            ['dni' => '71000467', 'nombre' => 'Adalberto Pérez López', 'entregas' => ['07:25' => 612.40, '12:35' => 352.00]],
        ]],
    ];

    public function __construct(
        private readonly RegistrarEntrega $registrarEntrega,
        private readonly SeleccionarMuestraCalidad $seleccionarMuestra,
        private readonly EstadoAsistenciaActual $asistencia,
    ) {}

    /**
     * Verifica todo sin escribir nada y resuelve los ids reales.
     *
     * @return array{conflictos: list<string>, avisos: list<string>, analista: ?object, zonas: list<array<string, mixed>>}
     */
    public function planificar(?string $analistaUsername = null): array
    {
        $conflictos = [];
        $avisos = [];
        $zonas = [];
        $ahora = new DateTimeImmutable;

        $this->verificarPlan($conflictos);

        if (DB::table('insumos')->where('codigo', Insumo::CODIGO_LECHE)->doesntExist()) {
            $conflictos[] = 'No existe el insumo del sistema LECHE (codigo = LECHE): RegistrarEntrega no puede ingresar la leche al inventario.';
        }

        if (DB::table('precios_leche')->where('estado', 'ACTIVO')->whereDate('fecha_inicio', '<=', self::FECHA)
            ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', self::FECHA))->doesntExist()) {
            $avisos[] = 'No hay precio de leche vigente el '.self::FECHA.': las entregas quedarán sin valorizar (no se crea ninguno).';
        }

        $analista = $this->analista($analistaUsername, $conflictos);

        foreach (self::PLAN as $zonaPlan) {
            $zona = DB::table('zonas')->where('id', $zonaPlan['zona_id'])->first();

            if ($zona === null || $zona->nombre !== $zonaPlan['zona'] || $zona->estado !== 'ACTIVO') {
                $conflictos[] = sprintf('La zona %d no existe, no se llama "%s" o no está ACTIVA.', $zonaPlan['zona_id'], $zonaPlan['zona']);
            }

            $acopiador = $this->acopiador($zonaPlan, $conflictos);
            $proveedores = [];

            foreach ($zonaPlan['proveedores'] as $p) {
                $proveedor = DB::table('proveedores')->where('dni', $p['dni'])->first();

                if ($proveedor === null) {
                    $conflictos[] = sprintf('No existe el proveedor con DNI %s (%s).', $p['dni'], $p['nombre']);

                    continue;
                }

                if ($this->normalizar($proveedor->nombres.' '.$proveedor->apellidos) !== $this->normalizar($p['nombre'])) {
                    $conflictos[] = sprintf('El DNI %s es de "%s %s", no de "%s".', $p['dni'], $proveedor->nombres, $proveedor->apellidos, $p['nombre']);
                }

                if ((int) $proveedor->zona_id !== $zonaPlan['zona_id']) {
                    $conflictos[] = sprintf('%s (DNI %s) no pertenece a %s.', $p['nombre'], $p['dni'], $zonaPlan['zona']);
                }

                if ($proveedor->estado !== 'ACTIVO') {
                    $conflictos[] = sprintf('%s (DNI %s) no está ACTIVO.', $p['nombre'], $p['dni']);
                }

                if ($proveedor->latitud === null || $proveedor->longitud === null) {
                    $avisos[] = sprintf('%s (DNI %s) no tiene coordenadas registradas; no se modifican.', $p['nombre'], $p['dni']);
                }

                $entregas = [];
                $n = 0;

                foreach ($p['entregas'] as $hora => $litros) {
                    $momento = $this->momento($hora);

                    if ($momento > $ahora) {
                        $conflictos[] = sprintf('La entrega de %s a las %s todavía no ocurre (no se registran horas futuras).', $p['nombre'], $hora);
                    }

                    if ($acopiador !== null && ! $this->asistencia->paraUsuario((int) $acopiador->user_id, $momento)->puedeOperar()) {
                        $conflictos[] = sprintf('%s %s no tiene asistencia válida el %s: no puede operar.', $zonaPlan['acopiador'][0], $zonaPlan['acopiador'][1], self::FECHA);
                    }

                    $entregas[] = ['hora' => $hora, 'momento' => $momento, 'litros' => (float) $litros, 'token' => sprintf('%s-%s-%d', self::LOTE, $p['dni'], ++$n)];
                }

                $muestra = isset($p['muestra']) ? $this->momento($p['muestra']) : null;

                if ($muestra !== null && $analista !== null && ! $this->asistencia->paraUsuario((int) $analista->user_id, $muestra)->puedeOperar()) {
                    $conflictos[] = sprintf('La analista %s no tiene asistencia válida el %s: no puede seleccionar muestras.', $analista->username, self::FECHA);
                }

                $proveedores[] = ['id' => $proveedor->id, 'dni' => $p['dni'], 'nombre' => $p['nombre'], 'qr_token' => $proveedor->qr_token, 'muestra' => $muestra, 'entregas' => $entregas];
            }

            $zonas[] = ['zona_id' => $zonaPlan['zona_id'], 'zona' => $zonaPlan['zona'], 'acopiador' => $acopiador, 'litros' => (float) $zonaPlan['litros'], 'proveedores' => $proveedores];
        }

        return [
            'conflictos' => array_values(array_unique($conflictos)),
            'avisos' => array_values(array_unique($avisos)),
            'analista' => $analista,
            'zonas' => $zonas,
        ];
    }

    /**
     * Registra el lote en una sola transacción (todo o nada). Repetirlo no duplica nada.
     *
     * @param  array{conflictos: list<string>, avisos: list<string>, analista: ?object, zonas: list<array<string, mixed>>}  $plan
     */
    public function aplicar(array $plan): void
    {
        if ($plan['conflictos'] !== []) {
            throw new DomainException('El plan tiene conflictos; no se registró nada.');
        }

        $utc = new DateTimeZone(config('app.timezone'));

        DB::transaction(function () use ($plan, $utc): void {
            foreach ($plan['zonas'] as $zona) {
                foreach ($zona['proveedores'] as $proveedor) {
                    $primera = null;

                    foreach ($proveedor['entregas'] as $entrega) {
                        $registrada = $this->registrarEntrega->execute(
                            metodoIdentificacion: 'QR',
                            identificador: $proveedor['qr_token'],
                            acopiadorUserId: (int) $zona['acopiador']->user_id,
                            litros: $entrega['litros'],
                            fechaHora: $entrega['momento']->setTimezone($utc),
                            observacion: self::OBSERVACION,
                            tokenIdempotencia: $entrega['token'],
                        );
                        $primera ??= $registrada;
                    }

                    if ($proveedor['muestra'] !== null) {
                        $this->seleccionarMuestra->execute(
                            entregaId: (int) $primera->id(),
                            analistaUserId: (int) $plan['analista']->user_id,
                            metodo: MetodoSeleccionMuestreo::MANUAL,
                            tipoControl: TipoControlMuestreo::RUTINARIO,
                            fechaSeleccion: $proveedor['muestra']->setTimezone($utc),
                            observacion: '['.self::LOTE.'] Muestra de demostración pendiente de análisis.',
                        );
                    }
                }
            }
        });
    }

    /**
     * Totales del lote leídos de la base de datos (no del plan).
     *
     * @return array{zonas: list<array<string, mixed>>, total: float, entregas: int, pendientes: int, previas_dia: array{entregas: int, litros: float}, total_dia: float}
     */
    public function resumen(): array
    {
        $zonas = DB::table('entregas as e')
            ->join('zonas as z', 'z.id', '=', 'e.zona_id')
            ->leftJoin('acopiadores as a', 'a.id', '=', 'e.acopiador_id')
            ->leftJoin('muestreos_calidad as m', function ($join): void {
                $join->on('m.entrega_id', '=', 'e.id')->whereIn('m.estado', ['SELECCIONADA', 'EN_ANALISIS']);
            })
            ->where('e.token_idempotencia', 'like', self::LOTE.'-%')
            ->groupBy('z.id', 'z.nombre', 'a.nombres', 'a.apellidos')
            ->orderBy('z.id')
            ->selectRaw('z.nombre as zona, a.nombres, a.apellidos, COUNT(DISTINCT e.proveedor_id) as proveedores, COUNT(*) as entregas, SUM(e.litros) as litros, COUNT(m.id) as pendientes')
            ->get()
            ->map(fn (object $f): array => [
                'zona' => $f->zona, 'acopiador' => trim($f->nombres.' '.$f->apellidos), 'proveedores' => (int) $f->proveedores,
                'entregas' => (int) $f->entregas, 'litros' => round((float) $f->litros, 2), 'pendientes' => (int) $f->pendientes,
            ])
            ->all();

        [$desde, $hasta] = $this->limitesDia();
        $previas = DB::table('entregas')->whereBetween('fecha_hora', [$desde, $hasta])->where('estado', '!=', 'ANULADA')
            ->where(fn ($q) => $q->whereNull('token_idempotencia')->orWhere('token_idempotencia', 'not like', self::LOTE.'-%'));
        $previasLitros = round((float) (clone $previas)->sum('litros'), 2);
        $total = round(array_sum(array_column($zonas, 'litros')), 2);

        return [
            'zonas' => $zonas,
            'total' => $total,
            'entregas' => array_sum(array_column($zonas, 'entregas')),
            'pendientes' => array_sum(array_column($zonas, 'pendientes')),
            'previas_dia' => ['entregas' => $previas->count(), 'litros' => $previasLitros],
            'total_dia' => round($total + $previasLitros, 2),
        ];
    }

    /** Inicio y fin del 28/10/2026 en Lima, en la zona de la app (como se guarda fecha_hora). */
    public function limitesDia(): array
    {
        $utc = new DateTimeZone(config('app.timezone'));

        return [
            $this->momento('00:00:00')->setTimezone($utc)->format('Y-m-d H:i:s'),
            $this->momento('23:59:59')->setTimezone($utc)->format('Y-m-d H:i:s'),
        ];
    }

    /** @param  list<string>  $conflictos */
    private function verificarPlan(array &$conflictos): void
    {
        $total = 0.0;

        foreach (self::PLAN as $zona) {
            $litrosZona = 0.0;

            foreach ($zona['proveedores'] as $p) {
                foreach ($p['entregas'] as $litros) {
                    if ($litros <= 0) {
                        $conflictos[] = sprintf('Litros no válidos para %s: %s.', $p['nombre'], $litros);
                    }

                    $litrosZona += $litros;
                }
            }

            if (round($litrosZona, 2) !== round((float) $zona['litros'], 2)) {
                $conflictos[] = sprintf('%s suma %s L y debía sumar %s L.', $zona['zona'], $litrosZona, $zona['litros']);
            }

            $total += $litrosZona;
        }

        if (round($total, 2) !== self::LITROS_TOTALES) {
            $conflictos[] = sprintf('El plan suma %s L y debía sumar %s L.', $total, self::LITROS_TOTALES);
        }
    }

    /**
     * @param  array<string, mixed>  $zonaPlan
     * @param  list<string>  $conflictos
     */
    private function acopiador(array $zonaPlan, array &$conflictos): ?object
    {
        [$nombres, $apellidos] = $zonaPlan['acopiador'];
        $etiqueta = $nombres.' '.$apellidos;
        $candidatos = DB::table('acopiadores as a')
            ->join('users as u', 'u.id', '=', 'a.user_id')
            ->join('roles as r', 'r.id', '=', 'u.role_id')
            ->where('a.nombres', $nombres)->where('a.apellidos', $apellidos)
            ->get(['a.id', 'a.user_id', 'a.zona_id', 'a.estado', 'u.estado as usuario_estado', 'r.nombre as rol']);

        if ($candidatos->count() !== 1) {
            $conflictos[] = sprintf('Se esperaba exactamente un acopiador "%s" y hay %d.', $etiqueta, $candidatos->count());

            return null;
        }

        $acopiador = $candidatos->first();

        if ($acopiador->rol !== 'ACOPIADOR' || $acopiador->estado !== 'ACTIVO' || $acopiador->usuario_estado !== 'ACTIVO') {
            $conflictos[] = sprintf('%s no es un acopiador ACTIVO con usuario ACTIVO.', $etiqueta);
        }

        $autorizado = DB::table('acopiador_zona_historial')
            ->where('acopiador_id', $acopiador->id)->where('zona_id', $zonaPlan['zona_id'])
            ->whereDate('fecha_inicio', '<=', self::FECHA)
            ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', self::FECHA))
            ->exists();

        if ((int) $acopiador->zona_id !== $zonaPlan['zona_id'] || ! $autorizado) {
            $conflictos[] = sprintf('%s no está asignado a %s el %s.', $etiqueta, $zonaPlan['zona'], self::FECHA);
        }

        return $acopiador;
    }

    /** @param  list<string>  $conflictos */
    private function analista(?string $username, array &$conflictos): ?object
    {
        $candidatos = DB::table('analistas_calidad as ac')
            ->join('users as u', 'u.id', '=', 'ac.user_id')
            ->join('roles as r', 'r.id', '=', 'u.role_id')
            ->where('r.nombre', 'CALIDAD')->where('ac.estado', 'ACTIVO')->where('u.estado', 'ACTIVO')
            ->when($username !== null, fn ($q) => $q->where('u.username', $username))
            ->get(['u.id as user_id', 'u.username']);

        if ($candidatos->count() === 1) {
            return $candidatos->first();
        }

        $conflictos[] = $candidatos->isEmpty()
            ? 'No hay un analista de Calidad ACTIVO'.($username !== null ? " con usuario {$username}" : '').' para seleccionar las muestras.'
            : 'Hay varios analistas de Calidad activos: indica cuál con --analista=<usuario>.';

        return null;
    }

    private function momento(string $hora): DateTimeImmutable
    {
        return new DateTimeImmutable(self::FECHA.' '.$hora, new DateTimeZone(config('app.zona_horaria_negocio')));
    }

    private function normalizar(string $texto): string
    {
        return Str::lower(Str::ascii(preg_replace('/\s+/u', ' ', trim($texto))));
    }
}
