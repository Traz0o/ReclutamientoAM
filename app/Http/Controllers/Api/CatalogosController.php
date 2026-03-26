<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class CatalogosController extends Controller
{
    public function areas()
    {
        $areas = DB::connection('mysql_empleados')
            ->table('areas')
            ->orderBy('nombre_area')
            ->get();

        return response()->json($areas);
    }

    public function tiposRequisito()
    {
        return response()->json(
            DB::table('cat_tipos_requisito')->orderBy('nombre')->get()
        );
    }

    public function estatusVacante()
    {
        $data = DB::table('cat_estatus_vacante')
            ->get()
            ->map(fn($e) => [
                'valor'    => $e->id_estatus_vacante,
                'etiqueta' => $e->nombre,
            ]);

        return response()->json($data);
    }

    public function estatusPostulacion()
    {
        return response()->json(
            DB::table('cat_estatus_postulacion')->orderBy('nombre')->get()
        );
    }

    public function recomendacionesEntrevista()
    {
        return response()->json(
            DB::table('cat_recomendaciones_entrevista')->orderBy('nombre')->get()
        );
    }
}