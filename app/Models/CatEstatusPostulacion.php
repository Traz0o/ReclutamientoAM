<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatEstatusPostulacion extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'cat_estatus_postulacion';
    protected $primaryKey = 'id_estatus_postulacion';
    public $timestamps = false;

    protected $fillable = ['nombre'];
}