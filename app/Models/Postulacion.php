<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Postulacion extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'postulaciones';
    protected $primaryKey = 'id_postulacion';
    public $timestamps = false;

    protected $fillable = [
        'id_vacante',
        'id_tipo_candidato',
        'id_empleado',
        'id_candidato_externo',
        'fecha_postulacion',
        'id_estatus_postulacion',
        'puntaje_automatico',
        'puntaje_entrevista',
        'puntaje_final',
        'motivo_descarte',
        'fecha_ultimo_cambio',
        'cambiado_por',
    ];

    public function vacante()
    {
        return $this->belongsTo(Vacante::class, 'id_vacante', 'id_vacante');
    }

    public function candidatoExterno()
    {
        return $this->belongsTo(CandidatoExterno::class, 'id_candidato_externo', 'id_candidato_externo');
    }

    public function estatus()
    {
        return $this->belongsTo(CatEstatusPostulacion::class, 'id_estatus_postulacion', 'id_estatus_postulacion');
    }
}