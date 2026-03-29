<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Vacante;
use App\Models\RequisitoVacante;
use App\Models\CatEstatusVacante;
use App\Models\Postulacion;

class VacantesController extends Controller
{
     private int $_postulacionesCreadas = 0;

    public function index(Request $request)
    {
        $query = Vacante::with('estatus')
        ->withCount(['postulaciones' => function ($q) {
            $idDescartado = DB::table('cat_estatus_postulacion')
                ->where('nombre', 'Descartado')
                ->value('id_estatus_postulacion');
            $q->where('id_estatus_postulacion', '!=', $idDescartado);
        }])
        ->orderByDesc('fecha_creacion');

        if ($request->has('limit')) {
            $query->limit($request->limit);
        }

        $vacantes = $query->get()->map(function ($v) {
            $area = null;
            if ($v->id_area) {
                $area = DB::connection('mysql_empleados')
                    ->table('areas')
                    ->where('id_area', $v->id_area)
                    ->first();
            }

            return [
                'id_vacante' => $v->id_vacante,
                'titulo' => $v->titulo,
                'nombre_area' => $area?->nombre_area ?? '—',
                'nombre_estatus' => $v->estatus?->nombre ?? '—',
                'total_postulantes' => $v->postulaciones()->where('id_estatus_postulacion', '!=', 
                    DB::table('cat_estatus_postulacion')->where('nombre', 'Descartado')->value('id_estatus_postulacion')
                )->count(),
                'fecha_cierre' => $v->fecha_cierre,
                'fecha_apertura_externa' => $v->fecha_apertura_externa,
            ];
        });

        return response()->json($vacantes);
    }

    public function show($id)
{
    $v = Vacante::with(['estatus', 'requisitos.tipo'])->findOrFail($id);

    $area = null;
    $tipoContrato = null;

    if ($v->id_area) {
        $area = DB::connection('mysql_empleados')
            ->table('areas')
            ->where('id_area', $v->id_area)
            ->first();

        // Obtener tipo de contrato más común entre empleados del área
        $tipoContrato = DB::connection('mysql_empleados')
            ->table('empleados')
            ->join('cat_tipos_contrato', 'empleados.id_tipo_contrato', '=', 'cat_tipos_contrato.id_tipo_contrato')
            ->where('empleados.id_area', $v->id_area)
            ->where('empleados.id_estatus_empleado', 1)
            ->select('cat_tipos_contrato.nombre', DB::raw('COUNT(*) as total'))
            ->groupBy('cat_tipos_contrato.nombre')
            ->orderByDesc('total')
            ->value('nombre');
    }

    return response()->json([
        'id_vacante'             => $v->id_vacante,
        'titulo'                 => $v->titulo,
        'descripcion'            => $v->descripcion,
        'salario' => $v->salario ?? null,
        'nombre_area'            => $area?->nombre_area ?? '—',
        'nombre_estatus'         => $v->estatus?->nombre ?? '—',
        'nombre_tipo_contrato'   => $tipoContrato ?? '—',  
        'fecha_creacion'         => $v->fecha_creacion,
        'fecha_cierre'           => $v->fecha_cierre,
        'fecha_apertura_externa' => $v->fecha_apertura_externa,
        'total_postulantes' => $v->postulaciones()
        ->whereHas('estatus', fn($q) => $q->where('nombre', '!=', 'Descartado'))
        ->count(),
        'externos_recibidos'     => $v->postulaciones()->where('id_tipo_candidato', 2)->count(),
        'externos_pendientes'    => $v->postulaciones()->where('id_tipo_candidato', 2)->where('id_estatus_postulacion', 1)->count(),
        'idDepa'                 => $v->id_area,
        'requisitos'             => $v->requisitos->map(fn($r) => [
            'id_requisito' => $r->id_requisito,
            'id_tipo_requisito' => $r->id_tipo_requisito,
            'descripcion'  => $r->descripcion,
            'nombre_tipo'  => $r->tipo?->nombre ?? '—',
            'peso_pct'     => $r->peso_pct,
            'valor_minimo' => $r->valor_minimo,
            'valor_ideal'  => $r->valor_ideal,
            'es_excluyente'=> $r->es_excluyente,
        ]),
    ]);
}

    public function store(Request $request)
{
    $data = $request->validate([
        'titulo'                 => 'required|string|max:100',
        'id_area'                => 'nullable|integer',
        'descripcion'            => 'nullable|string',
        'salario' =>            'nullable|string|max:100',
        'fecha_apertura_interna' => 'nullable|date',
        'fecha_cierre'           => 'nullable|date',
        'requisitos'             => 'nullable|array',
        'requisitos.*.id_tipo_requisito' => 'required|integer',
        'requisitos.*.descripcion'       => 'nullable|string|max:200',
        'requisitos.*.valor_minimo'      => 'nullable|string|max:100',
        'requisitos.*.valor_ideal'       => 'nullable|string|max:100',
        'requisitos.*.peso_pct'          => 'nullable|numeric',
        'requisitos.*.es_excluyente'     => 'nullable|boolean',
    ]);

    DB::beginTransaction();
    try {
        $idEstatusActiva = DB::table('cat_estatus_vacante')
            ->where('nombre', 'Activa')->value('id_estatus_vacante');

        $vacante = \App\Models\Vacante::create([
            'titulo'                 => $data['titulo'],
            'id_area'                => $data['id_area'] ?? null,
            'descripcion'            => $data['descripcion'] ?? null,
            'salario'                => $data['salario'] ?? null,
            'fecha_apertura_interna' => $data['fecha_apertura_interna'] ?? null,
            'fecha_cierre'           => $data['fecha_cierre'] ?? null,
            'id_estatus_vacante'     => $idEstatusActiva,
            'fecha_creacion'         => now(),
        ]);

        $requisitosCreados = [];
        foreach ($data['requisitos'] ?? [] as $req) {
            $requisito = \App\Models\RequisitoVacante::create([
                'id_vacante'        => $vacante->id_vacante,
                'id_tipo_requisito' => $req['id_tipo_requisito'],
                'descripcion'       => $req['descripcion'] ?? null,
                'valor_minimo'      => $req['valor_minimo'] ?? null,
                'valor_ideal'       => $req['valor_ideal'] ?? null,
                'peso_pct'          => $req['peso_pct'] ?? 0,
                'es_excluyente'     => $req['es_excluyente'] ?? false,
            ]);
            $requisitosCreados[] = $requisito;
        }

        DB::commit();

        // Evaluar internos automáticamente en segundo plano
        // Se hace después del commit para no bloquear la respuesta
        if ($vacante->id_area && count($requisitosCreados) > 0) {
            $this->evaluarInternosAutomatico($vacante->id_vacante, $vacante->id_area);
        }

        return response()->json([
            'id_vacante'           => $vacante->id_vacante,
            'titulo'               => $vacante->titulo,
            'postulaciones_creadas'=> $this->_postulacionesCreadas ?? 0,
            'mensaje'              => 'Vacante creada y candidatos internos evaluados automáticamente.',
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
    }
}

// ── Evaluación automática de internos al crear vacante ────────────────────
private function evaluarInternosAutomatico(int $idVacante, int $idArea): void
{
    // Obtener empleados activos del área
    $empleados = DB::connection('mysql_empleados')
        ->table('empleados')
        ->where('id_area', $idArea)
        ->where('id_estatus_empleado', 1) // 1 = Activo
        ->get();

    if ($empleados->isEmpty()) return;

    // Cargar requisitos de la vacante
    $requisitos = \App\Models\RequisitoVacante::with('tipo')
        ->where('id_vacante', $idVacante)
        ->get();

    if ($requisitos->isEmpty()) return;

    $idTipoInterno = DB::table('cat_tipos_candidato')
        ->where('nombre', 'Interno')
        ->value('id_tipo_candidato');

    $idEstatusPendiente = DB::table('cat_estatus_postulacion')
        ->where('nombre', 'Pendiente')
        ->value('id_estatus_postulacion');

    $postulacionesCreadas = 0;


    foreach ($empleados as $empleado) {
        try {
            // Usar el mismo algoritmo de puntaje interno
            [$puntaje, $descartado, $detalles] = $this->calcularPuntajeInterno(
                $empleado->id_empleado,
                $requisitos
            );

            // Si no cumple excluyente — no registrar
            if ($descartado) continue;

            // Verificar que no esté ya postulado
            $yaPostulado = \App\Models\Postulacion::where('id_vacante', $idVacante)
                ->where('id_empleado', $empleado->id_empleado)
                ->exists();

            if ($yaPostulado) continue;

            \App\Models\Postulacion::create([
                'id_vacante'             => $idVacante,
                'id_tipo_candidato'      => $idTipoInterno,
                'id_empleado'            => $empleado->id_empleado,
                'fecha_postulacion'      => now(),
                'id_estatus_postulacion' => $idEstatusPendiente,
                'puntaje_automatico'     => round($puntaje, 2),
                'fecha_ultimo_cambio'    => now(),
            ]);

            $postulacionesCreadas++;

        } catch (\Exception $e) {
            // Si falla un empleado no detener el proceso completo
            continue;
        }
    }

    $this->_postulacionesCreadas = $postulacionesCreadas;
}


    public function resumen()
    {
        $estatusActiva = CatEstatusVacante::where('nombre', 'Activa')->first();
        $estatusCerrada = CatEstatusVacante::where('nombre', 'Cerrada')->first();

        return response()->json([
            'vacantes_activas' => Vacante::where('id_estatus_vacante', $estatusActiva?->id_estatus_vacante)->count(),
            'postulantes_totales' => DB::table('postulaciones')->count(),
            'entrevistas_pendientes' => DB::table('evaluacion_entrevista')->count(),
            'cerradas_mes' => Vacante::where('id_estatus_vacante', $estatusCerrada?->id_estatus_vacante)
                ->whereMonth('fecha_creacion', now()->month)->count(),
            'candidatos_externos_pendientes' => DB::table('postulaciones')->where('id_tipo_candidato', 2)->where('id_estatus_postulacion', 1)->count(),
        ]);
    }

    public function actualizarEstatus(Request $request, $id)
    {
        $data = $request->validate([
            'nombre_estatus' => 'required|string'
        ]);

        $estatus = CatEstatusVacante::where('nombre', $data['nombre_estatus'])->firstOrFail();
        $vacante = Vacante::findOrFail($id);
        $vacante->id_estatus_vacante = $estatus->id_estatus_vacante;
        $vacante->save();

        return response()->json(['ok' => true]);
    }

    public function flujoExterno(Request $request, $id)
    {
        $data = $request->validate([
            'activo' => 'required|boolean'
        ]);

        $vacante = Vacante::findOrFail($id);
        $vacante->fecha_apertura_externa = $data['activo'] ? now() : null;
        $vacante->save();

        return response()->json(['ok' => true]);
    }

    public function ranking($id)
    {
        $idDescartado = DB::table('cat_estatus_postulacion')
        ->where('nombre', 'Descartado')
        ->value('id_estatus_postulacion');

        $postulaciones = DB::table('postulaciones')
            ->where('postulaciones.id_vacante', $id)
            ->where('postulaciones.id_estatus_postulacion', '!=', $idDescartado)
            ->join('cat_tipos_candidato', 'postulaciones.id_tipo_candidato', '=', 'cat_tipos_candidato.id_tipo_candidato')
            ->join('cat_estatus_postulacion', 'postulaciones.id_estatus_postulacion', '=', 'cat_estatus_postulacion.id_estatus_postulacion')
            ->leftJoin('candidatos_externos', 'postulaciones.id_candidato_externo', '=', 'candidatos_externos.id_candidato_externo')
            ->select(
                'postulaciones.id_postulacion',
                'postulaciones.id_empleado',
                'postulaciones.puntaje_automatico',
                'postulaciones.puntaje_entrevista',
                'postulaciones.puntaje_final',
                'candidatos_externos.nombre as nombre_externo',
                'cat_tipos_candidato.nombre as tipo_candidato',
                'cat_estatus_postulacion.nombre as nombre_estatus'
            )
            ->orderByDesc('postulaciones.puntaje_final')
            ->orderByDesc('postulaciones.puntaje_automatico')
            ->get()
            ->map(function ($p, $i) {
                // Resolver nombre del candidato
                $nombreCandidato = $p->nombre_externo;

                if (!$nombreCandidato && $p->id_empleado) {
                    $empleado = DB::connection('mysql_empleados')
                        ->table('empleados')
                        ->where('id_empleado', $p->id_empleado)
                        ->first();
                    $nombreCandidato = $empleado?->nombre ?? 'Empleado #' . $p->id_empleado;
                }

                return [
                    'posicion' => $i + 1,
                    'nombre_candidato' => $nombreCandidato ?? '—',
                    'tipo_candidato' => $p->tipo_candidato ?? '—',
                    'puntaje_automatico' => $p->puntaje_automatico,
                    'puntaje_entrevista' => $p->puntaje_entrevista,
                    'puntaje_final' => $p->puntaje_final,
                    'nombre_estatus' => $p->nombre_estatus ?? '—',
                    'id_postulacion' => $p->id_postulacion,
                ];
            });

        return response()->json($postulaciones);
    }

    public function alertas()
    {
        $alertas = [];

        $sinCerrar = Vacante::whereNotNull('fecha_cierre')
            ->where('fecha_cierre', '<', now())
            ->whereHas('estatus', fn($q) => $q->where('nombre', 'Activa'))
            ->count();

        if ($sinCerrar > 0) {
            $alertas[] = ['mensaje' => "{$sinCerrar} vacante(s) con fecha de cierre vencida."];
        }

        if (empty($alertas)) {
            $alertas[] = ['mensaje' => 'Sin alertas pendientes.'];
        }

        return response()->json($alertas);
    }

    public function graficas()
    {
        // Vacantes por estatus
        $vacantesPorEstatus = DB::table('vacantes')
            ->join('cat_estatus_vacante', 'vacantes.id_estatus_vacante', '=', 'cat_estatus_vacante.id_estatus_vacante')
            ->select('cat_estatus_vacante.nombre as estatus', DB::raw('COUNT(*) as total'))
            ->groupBy('cat_estatus_vacante.nombre')
            ->get();

        // Postulaciones por estatus (avance del proceso)
        $postulacionesPorEstatus = DB::table('postulaciones')
            ->join('cat_estatus_postulacion', 'postulaciones.id_estatus_postulacion', '=', 'cat_estatus_postulacion.id_estatus_postulacion')
            ->select('cat_estatus_postulacion.nombre as estatus', DB::raw('COUNT(*) as total'))
            ->groupBy('cat_estatus_postulacion.nombre')
            ->get();

        return response()->json([
            'vacantes_por_estatus' => $vacantesPorEstatus,
            'postulaciones_por_estatus' => $postulacionesPorEstatus,
        ]);
    }

    public function graficasVacante($id)
{
    $idDescartado = DB::table('cat_estatus_postulacion')
        ->where('nombre', 'Descartado')
        ->value('id_estatus_postulacion');

    $postulaciones = DB::table('postulaciones')
        ->leftJoin('candidatos_externos', 'postulaciones.id_candidato_externo', '=', 'candidatos_externos.id_candidato_externo')
        ->where('postulaciones.id_vacante', $id)
        ->where('postulaciones.id_estatus_postulacion', '!=', $idDescartado)
        ->select(
            'postulaciones.id_empleado',
            'candidatos_externos.nombre as nombre_candidato',
            'postulaciones.puntaje_automatico',
            'postulaciones.puntaje_entrevista',
            'postulaciones.puntaje_final'
        )
        ->orderByDesc('postulaciones.puntaje_final')
        ->get()
        ->map(function($p) {
            $nombre = $p->nombre_candidato;

            if (!$nombre && $p->id_empleado) {
                $empleado = DB::connection('mysql_empleados')
                    ->table('empleados')
                    ->where('id_empleado', $p->id_empleado)
                    ->first();
                $nombre = $empleado?->nombre ?? 'Interno';
            }

            return [
                'nombre' => $nombre,
                'puntaje_automatico' => (float) ($p->puntaje_automatico ?? 0),
                'puntaje_entrevista' => (float) ($p->puntaje_entrevista ?? 0),
                'puntaje_final' => (float) ($p->puntaje_final ?? 0),
            ];
        });

    return response()->json($postulaciones);
}

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'titulo' => 'required|string|max:100',
            'id_area' => 'nullable|integer',
            'salario' => 'nullable|string|max:100',
            'descripcion' => 'nullable|string',
            'fecha_apertura_interna' => 'nullable|date',
            'fecha_cierre' => 'nullable|date',
            'requisitos' => 'nullable|array',
            'requisitos.*.id_tipo_requisito' => 'required|integer',
            'requisitos.*.descripcion' => 'nullable|string|max:200',
            'requisitos.*.valor_minimo' => 'nullable|string|max:100',
            'requisitos.*.valor_ideal' => 'nullable|string|max:100',
            'requisitos.*.peso_pct' => 'nullable|numeric',
            'requisitos.*.es_excluyente' => 'nullable|boolean',
        ]);

        $vacante = Vacante::findOrFail($id);

        DB::beginTransaction();
        try {
            $vacante->update([
                'titulo' => $data['titulo'],
                'id_area' => $data['id_area'] ?? null,
                'salario' => $data['salario'] ?? null,
                'descripcion' => $data['descripcion'] ?? null,
                'fecha_apertura_interna' => $data['fecha_apertura_interna'] ?? null,
                'fecha_cierre' => $data['fecha_cierre'] ?? null,
            ]);

            if (isset($data['requisitos'])) {
                $idsRequisitos = $vacante->requisitos()->pluck('id_requisito');
                DB::table('detalle_evaluacion_entrevista')
                    ->whereIn('id_requisito', $idsRequisitos)
                    ->delete();


                $vacante->requisitos()->delete();


                foreach ($data['requisitos'] as $req) {
                    RequisitoVacante::create([
                        'id_vacante' => $vacante->id_vacante,
                        'id_tipo_requisito' => $req['id_tipo_requisito'],
                        'descripcion' => $req['descripcion'] ?? null,
                        'valor_minimo' => $req['valor_minimo'] ?? null,
                        'valor_ideal' => $req['valor_ideal'] ?? null,
                        'peso_pct' => $req['peso_pct'] ?? 0,
                        'es_excluyente' => $req['es_excluyente'] ?? false,
                    ]);
                }
            }

            DB::commit();
            return response()->json(['id_vacante' => $vacante->id_vacante]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    private function calcularPuntajeInterno(int $idEmpleado, $requisitos): array
    {
        $puntaje = 0;
        $descartado = false;
        $detalles = [];

        // Cargar datos del empleado
        $experiencias = DB::connection('mysql_empleados')
            ->table('experiencia_previa')
            ->where('id_empleado', $idEmpleado)
            ->get();

        $certificaciones = DB::connection('mysql_empleados')
            ->table('certificaciones_empleado')
            ->where('id_empleado', $idEmpleado)
            ->where('vigente', true)
            ->get();

        $formacion = DB::connection('mysql_empleados')
            ->table('formacion_academica')
            ->where('id_empleado', $idEmpleado)
            ->orderByDesc('id_nivel_academico')
            ->first();

        $evaluaciones = DB::connection('mysql_empleados')
            ->table('evaluaciones_desempeño')
            ->where('id_empleado', $idEmpleado)
            ->get();

        $kpis = DB::connection('mysql_empleados')
            ->table('kpis_operativos')
            ->where('id_empleado', $idEmpleado)
            ->get();

        $proyectos = DB::connection('mysql_empleados')
            ->table('participacion_proyectos')
            ->where('id_empleado', $idEmpleado)
            ->get();

        // Calcular años de experiencia previa
        $anosTotal = 0;
        $anosAutomotriz = 0;
        $esTier1 = false;

        foreach ($experiencias as $exp) {
            $inicio = \Carbon\Carbon::parse($exp->fecha_inicio);
            $fin = $exp->fecha_fin
                ? \Carbon\Carbon::parse($exp->fecha_fin)
                : now();
            $anos = $inicio->diffInYears($fin);
            $anosTotal += $anos;
            if ($exp->es_sector_automotriz) {
                $anosAutomotriz += $anos;
                if ($exp->es_tier1)
                    $esTier1 = true;
            }
        }

        // Sumar antigüedad dentro de la empresa (Brose = automotriz Tier 1)
        $empleadoInfo = DB::connection('mysql_empleados')
            ->table('empleados')
            ->where('id_empleado', $idEmpleado)
            ->first();

        if ($empleadoInfo?->fecha_ingreso) {
            $anosEnEmpresa = \Carbon\Carbon::parse($empleadoInfo->fecha_ingreso)
                ->diffInYears(now());
            $anosTotal += $anosEnEmpresa;
            $anosAutomotriz += $anosEnEmpresa;
            $esTier1 = true;
        }

        // Promedios
        $promedioEval = $evaluaciones->count() > 0
            ? $evaluaciones->avg('calificacion_general')
            : 0;

        $promedioKpi = $kpis->count() > 0
            ? $kpis->avg('cumplimiento_pct')
            : 0;

        $esLiderProyecto = $proyectos->where('id_rol_participacion', 1)->count() > 0;

        // Evaluar cada requisito por ID
        foreach ($requisitos as $req) {
            $tipo = $req->id_tipo_requisito;
            $cumple = false;
            $score = 0;
            $detalle = [];

            if ($tipo == 1) { // Experiencia
                $minimo = intval($req->valor_minimo);
                $ideal = intval($req->valor_ideal ?: $minimo + 2);
                $cumple = $anosAutomotriz >= $minimo;

                if ($cumple) {
                    $ratio = min($anosAutomotriz / max($ideal, 1), 1);
                    $score = $ratio * $req->peso_pct;
                    if ($esTier1)
                        $score = min($score * 1.1, $req->peso_pct);
                }

                $detalle = [
                    'tipo' => 'Experiencia',
                    'anos_automotriz' => round($anosAutomotriz, 2),
                    'anos_total' => round($anosTotal, 2),
                    'anos_en_empresa' => $anosEnEmpresa ?? 0,
                    'es_tier1' => $esTier1,
                    'minimo_requerido' => $minimo,
                    'ideal_requerido' => $ideal,
                    'cumple' => $cumple,
                    'score' => round($score, 2),
                ];

            } elseif ($tipo == 3) { // Certificación
                $cumple = $certificaciones->count() > 0;
                $score = $cumple ? $req->peso_pct : 0;

                $detalle = [
                    'tipo' => 'Certificación',
                    'certificaciones_vigentes' => $certificaciones->count(),
                    'nombres' => $certificaciones->pluck('id_tipo_certificacion'),
                    'cumple' => $cumple,
                    'score' => round($score, 2),
                ];

            } elseif ($tipo == 2) { // Educación
                $nivelEmpleado = $formacion?->id_nivel_academico ?? 0;
                $nivelMinimo = intval($req->valor_minimo ?: 2);
                $nivelIdeal = intval($req->valor_ideal ?: 3);
                $cumple = $nivelEmpleado >= $nivelMinimo;

                if ($cumple) {
                    $ratio = min($nivelEmpleado / max($nivelIdeal, 1), 1);
                    $score = $ratio * $req->peso_pct;
                }

                $detalle = [
                    'tipo' => 'Educación',
                    'nivel_empleado' => $nivelEmpleado,
                    'nivel_minimo' => $nivelMinimo,
                    'carrera' => $formacion?->carrera ?? '—',
                    'cumple' => $cumple,
                    'score' => round($score, 2),
                ];

            } elseif ($tipo == 4) { // Habilidad técnica
                $scoreEval = $promedioEval > 0 ? ($promedioEval / 5) : 0.5;
                $scoreKpi = $promedioKpi > 0 ? min($promedioKpi / 100, 1) : 0.5;
                $bonusLider = $esLiderProyecto ? 0.1 : 0;

                $ratio = min(($scoreEval * 0.5 + $scoreKpi * 0.4 + $bonusLider), 1);
                $score = $ratio * $req->peso_pct;
                $cumple = true;

                $detalle = [
                    'tipo' => 'Habilidad técnica',
                    'promedio_eval' => round($promedioEval, 2),
                    'promedio_kpi' => round($promedioKpi, 2),
                    'es_lider_proyecto' => $esLiderProyecto,
                    'ratio' => round($ratio, 4),
                    'score' => round($score, 2),
                ];

            } elseif ($tipo == 5) { // Idioma
                $score = $req->peso_pct * 0.3;
                $cumple = true;

                $detalle = [
                    'tipo' => 'Idioma',
                    'nota' => 'Sin datos de idioma en BD — se evalúa en entrevista',
                    'score' => round($score, 2),
                    'cumple' => true,
                ];

            } else {
                $score = $req->peso_pct * 0.5;
                $cumple = true;

                $detalle = [
                    'tipo' => 'Otro',
                    'score' => round($score, 2),
                    'cumple' => true,
                ];
            }

            $puntaje += $score;
            $detalles[] = array_merge($detalle, [
                'requisito' => $req->descripcion ?? "Requisito {$req->id_requisito}",
                'peso_pct' => $req->peso_pct,
            ]);

            if ($req->es_excluyente && !$cumple) {
                $descartado = true;
                break;
            }
        }

        return [round($puntaje, 2), $descartado, $detalles];
    }

    //devuelve solo las vacantes donde el empleado cumple los requisitos mínimos
    public function vacantesElegibles(Request $request)
{
    $idEmpleado = $request->query('id_empleado');

    if (!$idEmpleado) {
        return response()->json(['message' => 'id_empleado requerido'], 400);
    }

    $estatusValidos = DB::table('cat_estatus_vacante')
    ->whereIn('nombre', ['Activa', 'En proceso'])
    ->pluck('id_estatus_vacante');

    $vacantes = Vacante::with('requisitos.tipo')
        ->whereIn('id_estatus_vacante', $estatusValidos)
        ->get();

    $elegibles = [];

    foreach ($vacantes as $v) {
        if ($v->requisitos->isEmpty()) continue;

        [$puntaje, $descartado, $detalles] = $this->calcularPuntajeInterno(
            (int) $idEmpleado,
            $v->requisitos
        );

        if ($descartado) continue;

        $area = null;
        if ($v->id_area) {
            $area = DB::connection('mysql_empleados')
                ->table('areas')
                ->where('id_area', $v->id_area)
                ->first();
        }

        $postulacion = \App\Models\Postulacion::where('id_vacante', $v->id_vacante)
        ->where('id_empleado', $idEmpleado)
        ->first();

        $idDescartado = DB::table('cat_estatus_postulacion')
        ->where('nombre', 'Descartado')
        ->value('id_estatus_postulacion');

        if ($postulacion && $postulacion->id_estatus_postulacion == $idDescartado) continue;

        $elegibles[] = [
            'id_vacante'      => $v->id_vacante,
            'titulo'          => $v->titulo,
            'nombre_area'     => $area?->nombre_area ?? '—',
            'fecha_cierre'    => $v->fecha_cierre,
            'puntaje'         => $puntaje,
            'id_postulacion'  => $postulacion?->id_postulacion,
        ];
    }

    usort($elegibles, fn($a, $b) => $b['puntaje'] <=> $a['puntaje']);

    return response()->json($elegibles);
}

    public function destroy($id)
{
    $vacante = Vacante::findOrFail($id);

    DB::beginTransaction();
    try {
        $postulaciones = DB::table('postulaciones')
            ->where('id_vacante', $id)
            ->pluck('id_postulacion');

        // Obtener IDs de evaluaciones antes de borrarlas
        $evaluaciones = DB::table('evaluacion_entrevista')
            ->whereIn('id_postulacion', $postulaciones)
            ->pluck('id_evaluacion');

        // Eliminar detalles de evaluación
        DB::table('detalle_evaluacion_entrevista')
            ->whereIn('id_evaluacion', $evaluaciones)
            ->delete();

        // Eliminar evaluaciones de entrevista
        DB::table('evaluacion_entrevista')
            ->whereIn('id_postulacion', $postulaciones)
            ->delete();

        // Eliminar postulaciones
        DB::table('postulaciones')
            ->where('id_vacante', $id)
            ->delete();

        // Eliminar requisitos
        DB::table('requisitos_vacante')
            ->where('id_vacante', $id)
            ->delete();

        // Eliminar vacante
        $vacante->delete();

        DB::commit();
        return response()->json(['ok' => true, 'mensaje' => 'Vacante eliminada correctamente.']);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
    }
}


}