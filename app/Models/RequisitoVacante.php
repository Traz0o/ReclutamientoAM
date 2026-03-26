<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequisitoVacante extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'requisitos_vacante';
    protected $primaryKey = 'id_requisito';
    public $timestamps = false;

    protected $fillable = [
        'id_vacante',
        'id_tipo_requisito',
        'descripcion',
        'valor_minimo',
        'valor_ideal',
        'peso_pct',
        'es_excluyente',
    ];

    public function tipo()
    {
        return $this->belongsTo(CatTipoRequisito::class, 'id_tipo_requisito', 'id_tipo_requisito');
    }
}