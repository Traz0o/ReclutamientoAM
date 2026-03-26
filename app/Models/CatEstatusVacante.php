<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatEstatusVacante extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'cat_estatus_vacante';
    protected $primaryKey = 'id_estatus_vacante';
    public $timestamps = false;

    protected $fillable = ['nombre'];
}