<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatTipoRequisito extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'cat_tipos_requisito';
    protected $primaryKey = 'id_tipo_requisito';
    public $timestamps = false;

    protected $fillable = ['nombre'];
}