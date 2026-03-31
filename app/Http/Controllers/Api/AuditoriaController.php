<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditoriaController extends Controller
{
    public function postulaciones(Request $request)
    {
        $query = DB::table('auditoria_postulaciones as a')
            ->leftJoin('vacantes as v', 'a.id_vacante', '=', 'v.id_vacante')
            ->leftJoin('cat_estatus_postulacion as e1',
                DB::raw('a.valor_anterior::integer'),
                '=', 'e1.id_estatus_postulacion')
            ->leftJoin('cat_estatus_postulacion as e2',
                DB::raw('a.valor_nuevo::integer'),
                '=', 'e2.id_estatus_postulacion')
            ->select(
                'a.id_auditoria',
                'a.id_postulacion',
                'a.id_vacante',
                'v.titulo as titulo_vacante',
                'a.campo_modificado',
                DB::raw("COALESCE(e1.nombre, a.valor_anterior) as valor_anterior"),
                DB::raw("COALESCE(e2.nombre, a.valor_nuevo) as valor_nuevo"),
                'a.accion',
                'a.fecha_cambio'
            )
            ->orderByDesc('a.fecha_cambio');

        if ($request->has('id_vacante')) {
            $query->where('a.id_vacante', $request->id_vacante);
        }

        return response()->json($query->limit(100)->get());
    }

    public function vacantes(Request $request)
    {
        $registros = DB::table('auditoria_vacantes')
            ->orderByDesc('fecha_cambio')
            ->limit(100)
            ->get();

        return response()->json($registros);
    }
}