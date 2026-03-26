<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vacante extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'vacantes';
    protected $primaryKey = 'id_vacante';
    public $timestamps = false;

    protected $fillable = [
        'titulo',
        'id_area',
        'id_solicitante',
        'descripcion',
        'id_estatus_vacante',
        'fecha_apertura_interna',
        'fecha_apertura_externa',
        'fecha_cierre',
        'creado_por',
        'fecha_creacion',
    ];

    public function requisitos()
    {
        return $this->hasMany(RequisitoVacante::class, 'id_vacante', 'id_vacante');
    }

    public function estatus()
    {
        return $this->belongsTo(CatEstatusVacante::class, 'id_estatus_vacante', 'id_estatus_vacante');
    }

    public function postulaciones()
    {
        return $this->hasMany(Postulacion::class, 'id_vacante', 'id_vacante');
    }
}
