<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class UsuarioSistema extends Authenticatable
{
    use HasApiTokens;

    protected $connection = 'pgsql';
    protected $table = 'usuarios_sistema';
    protected $primaryKey = 'id_usuario';
    public $timestamps = false;
    protected $authPasswordName = 'password_hash';

    protected $fillable = [
        'id_empleado',
        'email',
        'password_hash',
        'id_rol_usuario',
        'activo'
    ];

    protected $hidden = ['password_hash'];

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function rol()
    {
        return $this->belongsTo(CatRolUsuario::class, 'id_rol_usuario', 'id_rol_usuario');
    }
}