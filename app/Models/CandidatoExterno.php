<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidatoExterno extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'candidatos_externos';
    protected $primaryKey = 'id_candidato_externo';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'email',
        'telefono',
        'anos_experiencia_automotriz',
        'id_nivel_academico',
        'carrera',
        'experiencia_tier1',
        'experiencias_tier1_distintas',
        'tiene_certificaciones',
        'fecha_registro',
    ];
}