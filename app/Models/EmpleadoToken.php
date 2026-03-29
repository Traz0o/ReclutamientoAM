<?php
namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken;

class EmpleadoToken extends PersonalAccessToken
{
    protected $connection = 'mysql_empleados';
    protected $table = 'personal_access_tokens';
}