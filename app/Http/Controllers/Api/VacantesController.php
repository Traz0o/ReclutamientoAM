<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Vacante;
use App\Models\RequisitoVacante;
use App\Models\CatEstatusVacante;

class VacantesController extends Controller
{
    public function index(Request $request)
    {
        $query = Vacante::with('estatus')
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
                'id_vacante'             => $v->id_vacante,
                'titulo'                 => $v->titulo,
                'nombre_area'            => $area?->nombre_area ?? '—',
                'nombre_estatus'         => $v->estatus?->nombre ?? '—',
                'total_postulantes'      => $v->postulaciones()->count(),
                'fecha_cierre'           => $v->fecha_cierre,
                'fecha_apertura_externa' => $v->fecha_apertura_externa,
            ];
        });

        return response()->json($vacantes);
    }

    public function show($id)
    {
        $v = Vacante::with(['estatus', 'requisitos.tipo'])->findOrFail($id);

        $area = null;
        if ($v->id_area) {
            $area = DB::connection('mysql_empleados')
                ->table('areas')
                ->where('id_area', $v->id_area)
                ->first();
        }

        return response()->json([
            'id_vacante'             => $v->id_vacante,
            'titulo'                 => $v->titulo,
            'descripcion'            => $v->descripcion,
            'nombre_area'            => $area?->nombre_area ?? '—',
            'nombre_estatus'         => $v->estatus?->nombre ?? '—',
            'fecha_creacion'         => $v->fecha_creacion,
            'fecha_cierre'           => $v->fecha_cierre,
            'fecha_apertura_externa' => $v->fecha_apertura_externa,
            'total_postulantes'      => $v->postulaciones()->count(),
            'externos_recibidos'     => $v->postulaciones()->where('id_tipo_candidato', 2)->count(),
            'externos_pendientes'    => $v->postulaciones()->where('id_tipo_candidato', 2)->where('id_estatus_postulacion', 1)->count(),
            'idDepa'                 => $v->id_area,
            'requisitos'             => $v->requisitos->map(fn($r) => [
                'id_requisito'    => $r->id_requisito,
                'descripcion'     => $r->descripcion,
                'nombre_tipo'     => $r->tipo?->nombre ?? '—',
                'peso_pct'        => $r->peso_pct,
                'valor_minimo'    => $r->valor_minimo,
                'valor_ideal'     => $r->valor_ideal,
                'es_excluyente'   => $r->es_excluyente,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'titulo'                 => 'required|string|max:100',
            'id_area'                => 'nullable|integer',
            'descripcion'            => 'nullable|string',
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

        $estatusActiva = CatEstatusVacante::where('nombre', 'Activa')->first();

        DB::beginTransaction();
        try {
            $vacante = Vacante::create([
                'titulo'                 => $data['titulo'],
                'id_area'                => $data['id_area'] ?? null,
                'descripcion'            => $data['descripcion'] ?? null,
                'id_estatus_vacante'     => $estatusActiva->id_estatus_vacante,
                'fecha_apertura_interna' => $data['fecha_apertura_interna'] ?? null,
                'fecha_cierre'           => $data['fecha_cierre'] ?? null,
                'creado_por'             => $request->user()->id_usuario,
                'fecha_creacion'         => now(),
            ]);

            foreach ($data['requisitos'] ?? [] as $req) {
                RequisitoVacante::create([
                    'id_vacante'        => $vacante->id_vacante,
                    'id_tipo_requisito' => $req['id_tipo_requisito'],
                    'descripcion'       => $req['descripcion'] ?? null,
                    'valor_minimo'      => $req['valor_minimo'] ?? null,
                    'valor_ideal'       => $req['valor_ideal'] ?? null,
                    'peso_pct'          => $req['peso_pct'] ?? 0,
                    'es_excluyente'     => $req['es_excluyente'] ?? false,
                ]);
            }

            DB::commit();
            return response()->json(['id_vacante' => $vacante->id_vacante], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function resumen()
    {
        $estatusActiva = CatEstatusVacante::where('nombre', 'Activa')->first();
        $estatusCerrada = CatEstatusVacante::where('nombre', 'Cerrada')->first();

        return response()->json([
            'vacantes_activas'              => Vacante::where('id_estatus_vacante', $estatusActiva?->id_estatus_vacante)->count(),
            'postulantes_totales'           => DB::table('postulaciones')->count(),
            'entrevistas_pendientes'        => DB::table('evaluacion_entrevista')->count(),
            'cerradas_mes'                  => Vacante::where('id_estatus_vacante', $estatusCerrada?->id_estatus_vacante)
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
        $postulaciones = DB::table('postulaciones')
            ->where('id_vacante', $id)
            ->join('cat_tipos_candidato', 'postulaciones.id_tipo_candidato', '=', 'cat_tipos_candidato.id_tipo_candidato')
            ->join('cat_estatus_postulacion', 'postulaciones.id_estatus_postulacion', '=', 'cat_estatus_postulacion.id_estatus_postulacion')
            ->leftJoin('candidatos_externos', 'postulaciones.id_candidato_externo', '=', 'candidatos_externos.id_candidato_externo')
            ->orderByDesc('puntaje_final')
            ->get()
            ->map(function ($p, $i) {
                return [
                    'posicion'           => $i + 1,
                    'nombre_candidato'   => $p->nombre ?? '—',
                    'tipo_candidato'     => $p->nombre ?? '—',
                    'puntaje_automatico' => $p->puntaje_automatico,
                    'puntaje_entrevista' => $p->puntaje_entrevista,
                    'puntaje_final'      => $p->puntaje_final,
                    'nombre_estatus'     => $p->nombre,
                    'id_postulacion'     => $p->id_postulacion,
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
}