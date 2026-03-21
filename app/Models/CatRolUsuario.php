<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatRolUsuario extends Model
{
    protected $table = 'cat_roles_usuario';
    protected $primaryKey = 'id_rol_usuario';
    public $timestamps = false;

    protected $fillable = ['nombre'];
}