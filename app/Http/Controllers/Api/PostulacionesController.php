<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Postulacion;
use App\Models\RequisitoVacante;

class PostulacionesController extends Controller
{
    public function show($id)
    {
        $p = Postulacion::findOrFail($id);

        $vacante = DB::table('vacantes')
            ->where('id_vacante', $p->id_vacante)
            ->first();

        $area = null;
        if ($vacante?->id_area) {
            $area = DB::connection('mysql_empleados')
                ->table('areas')
                ->where('id_area', $vacante->id_area)
                ->first();
        }

        $candidato = null;
        $nombreCandidato = 'Candidato interno';

        if ($p->id_candidato_externo) {
            $candidato = DB::table('candidatos_externos')
                ->where('id_candidato_externo', $p->id_candidato_externo)
                ->first();
            $nombreCandidato = $candidato?->nombre ?? '—';
        } elseif ($p->id_empleado) {
            $empleado = DB::connection('mysql_empleados')
                ->table('empleados')
                ->where('id_empleado', $p->id_empleado)
                ->first();
            $nombreCandidato = $empleado?->nombre ?? 'Empleado interno';
        }

        $tipoCandidato = DB::table('cat_tipos_candidato')
            ->where('id_tipo_candidato', $p->id_tipo_candidato)
            ->first();

        $requisitos = RequisitoVacante::with('tipo')
            ->where('id_vacante', $p->id_vacante)
            ->get()
            ->map(fn($r) => [
                'id_requisito' => $r->id_requisito,
                'descripcion' => $r->descripcion,
                'nombre_tipo' => $r->tipo?->nombre ?? '—',
                'peso_pct' => $r->peso_pct,
            ]);

        // Verificar si tiene sanciones activas (solo internos)
        $tieneSancionActiva = false;
        if ($p->id_empleado) {
            $sanciones = DB::connection('mysql_empleados')
                ->table('sanciones_disciplinarias')
                ->where('id_empleado', $p->id_empleado)
                ->where('activa', true)
                ->count();
            $tieneSancionActiva = $sanciones > 0;
        }

        return response()->json([
            'id_postulacion' => $p->id_postulacion,
            'nombre_candidato' => $nombreCandidato,
            'tipo_candidato' => $tipoCandidato?->nombre ?? '—',
            'puntaje_automatico' => $p->puntaje_automatico,
            'id_vacante' => $p->id_vacante,
            'titulo_vacante' => $vacante?->titulo ?? '—',
            'nombre_area' => $area?->nombre_area ?? '—',
            'requisitos' => $requisitos,
            'advertencia_sancion' => $tieneSancionActiva
                ? 'Este empleado tiene sanciones disciplinarias activas.'
                : null,
        ]);
    }

    // ── Postulación de candidato EXTERNO ─────────────────────────────────────
    public function store(Request $request)
    {
        $data = $request->validate([
            'id_vacante' => 'required|integer|exists:vacantes,id_vacante',
            'id_candidato_externo' => 'required|integer|exists:candidatos_externos,id_candidato_externo',
        ]);

        DB::beginTransaction();
        try {
            $candidato = DB::table('candidatos_externos')
                ->where('id_candidato_externo', $data['id_candidato_externo'])
                ->first();

            $requisitos = RequisitoVacante::with('tipo')
                ->where('id_vacante', $data['id_vacante'])
                ->get();

            [$puntaje, $descartado] = $this->calcularPuntajeExterno($candidato, $requisitos);

            $idTipoCandidato = DB::table('cat_tipos_candidato')
                ->where('nombre', 'Externo')->first();

            $idEstatusPostulacion = $descartado
                ? DB::table('cat_estatus_postulacion')->where('nombre', 'Descartado')->first()
                : DB::table('cat_estatus_postulacion')->where('nombre', 'Pendiente')->first();

            $postulacion = Postulacion::create([
                'id_vacante' => $data['id_vacante'],
                'id_tipo_candidato' => $idTipoCandidato->id_tipo_candidato,
                'id_candidato_externo' => $data['id_candidato_externo'],
                'fecha_postulacion' => now(),
                'id_estatus_postulacion' => $idEstatusPostulacion->id_estatus_postulacion,
                'puntaje_automatico' => round($puntaje, 2),
                'fecha_ultimo_cambio' => now(),
            ]);

            DB::commit();
            return response()->json([
                'id_postulacion' => $postulacion->id_postulacion,
                'puntaje_automatico' => $postulacion->puntaje_automatico,
                'descartado' => $descartado,
                'mensaje' => $descartado
                    ? 'Candidato descartado por no cumplir requisito excluyente.'
                    : 'Postulación registrada correctamente.',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ── Postulación de candidato INTERNO ─────────────────────────────────────
    public function storeInterno(Request $request)
    {
        $data = $request->validate([
            'id_vacante' => 'required|integer|exists:vacantes,id_vacante',
            'id_empleado' => 'required|integer',
        ]);

        DB::beginTransaction();
        try {
            // Verificar que el empleado existe en bd_empleados
            $empleado = DB::connection('mysql_empleados')
                ->table('empleados')
                ->where('id_empleado', $data['id_empleado'])
                ->first();

            if (!$empleado) {
                return response()->json(['message' => 'Empleado no encontrado.'], 404);
            }

            // Verificar que no esté ya postulado a esta vacante
            $yaPostulado = Postulacion::where('id_vacante', $data['id_vacante'])
                ->where('id_empleado', $data['id_empleado'])
                ->exists();

            if ($yaPostulado) {
                return response()->json(['message' => 'El empleado ya está postulado a esta vacante.'], 409);
            }

            $requisitos = RequisitoVacante::with('tipo')
                ->where('id_vacante', $data['id_vacante'])
                ->get();

            // Calcular puntaje desde datos reales de bd_empleados
            [$puntaje, $descartado, $detalles] = $this->calcularPuntajeInterno(
                $data['id_empleado'],
                $requisitos
            );

            // Verificar sanciones activas — solo advertencia, no descarta
            $sanciones = DB::connection('mysql_empleados')
                ->table('sanciones_disciplinarias')
                ->where('id_empleado', $data['id_empleado'])
                ->where('activa', true)
                ->count();

            $idTipoCandidato = DB::table('cat_tipos_candidato')
                ->where('nombre', 'Interno')->first();

            $idEstatusPostulacion = $descartado
                ? DB::table('cat_estatus_postulacion')->where('nombre', 'Descartado')->first()
                : DB::table('cat_estatus_postulacion')->where('nombre', 'Pendiente')->first();

            $postulacion = Postulacion::create([
                'id_vacante' => $data['id_vacante'],
                'id_tipo_candidato' => $idTipoCandidato->id_tipo_candidato,
                'id_empleado' => $data['id_empleado'],
                'fecha_postulacion' => now(),
                'id_estatus_postulacion' => $idEstatusPostulacion->id_estatus_postulacion,
                'puntaje_automatico' => round($puntaje, 2),
                'fecha_ultimo_cambio' => now(),
            ]);

            DB::commit();
            return response()->json([
                'id_postulacion' => $postulacion->id_postulacion,
                'nombre_empleado' => $empleado->nombre,
                'puntaje_automatico' => $postulacion->puntaje_automatico,
                'descartado' => $descartado,
                'detalles_puntaje' => $detalles,
                'advertencia_sancion' => $sanciones > 0
                    ? "El empleado tiene {$sanciones} sanción(es) activa(s)."
                    : null,
                'mensaje' => $descartado
                    ? 'Empleado descartado por no cumplir requisito excluyente.'
                    : 'Postulación interna registrada correctamente.',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ── Algoritmo puntaje EXTERNO ─────────────────────────────────────────────
    private function calcularPuntajeExterno($candidato, $requisitos): array
    {
        $puntaje = 0;
        $descartado = false;

        foreach ($requisitos as $req) {
            $tipo = $req->id_tipo_requisito; // usar ID en lugar de nombre
            $cumple = false;

            if ($tipo == 1) { // Experiencia
                $minimo = intval($req->valor_minimo);
                $cumple = $candidato->anos_experiencia_automotriz >= $minimo;
                if ($cumple) {
                    $ideal = intval($req->valor_ideal ?: $minimo + 2);
                    $ratio = min($candidato->anos_experiencia_automotriz / max($ideal, 1), 1);
                    $puntaje += $ratio * $req->peso_pct;
                }

            } elseif ($tipo == 3) { // Certificación
                $cumple = $candidato->tiene_certificaciones;
                if ($cumple)
                    $puntaje += $req->peso_pct;

            } elseif ($tipo == 2) { // Educación
                $cumple = $candidato->id_nivel_academico >= 2;
                if ($cumple)
                    $puntaje += $req->peso_pct;

            } elseif ($tipo == 4) { // Habilidad técnica
                $puntaje += $req->peso_pct * 0.5;
                $cumple = true;

            } elseif ($tipo == 5) { // Idioma
                $puntaje += $req->peso_pct * 0.3;
                $cumple = true;

            } else {
                $puntaje += $req->peso_pct * 0.5;
                $cumple = true;
            }

            if ($req->es_excluyente && !$cumple) {
                $descartado = true;
                break;
            }
        }

        return [$puntaje, $descartado];
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

    public function respuestaEmpleado(Request $request, $id)
    {
        $data = $request->validate([
            'acepta' => 'required|boolean',
        ]);

        $postulacion = \App\Models\Postulacion::findOrFail($id);

        $nuevoEstatus = $data['acepta'] ? 'En revisión' : 'Descartado';

        $idEstatus = DB::table('cat_estatus_postulacion')
            ->where('nombre', $nuevoEstatus)
            ->value('id_estatus_postulacion');

        $postulacion->id_estatus_postulacion = $idEstatus;
        $postulacion->fecha_ultimo_cambio = now();
        $postulacion->save();

        return response()->json(['ok' => true, 'estatus' => $nuevoEstatus]);
    }

    public function actualizarEstatus(Request $request, $id)
    {
        $data = $request->validate([
            'nombre_estatus' => 'required|string'
        ]);

        $estatus = DB::table('cat_estatus_postulacion')
            ->where('nombre', $data['nombre_estatus'])
            ->first();

        if (!$estatus) {
            return response()->json(['message' => 'Estatus no válido.'], 422);
        }

        $postulacion = Postulacion::findOrFail($id);
        $postulacion->id_estatus_postulacion = $estatus->id_estatus_postulacion;
        $postulacion->fecha_ultimo_cambio = now();
        $postulacion->save();

        return response()->json(['ok' => true, 'nombre_estatus' => $estatus->nombre]);
    }
}