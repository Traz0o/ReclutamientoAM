<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\VacantesController;
use App\Http\Controllers\Api\CatalogosController;
use App\Http\Controllers\Api\EvaluacionesEntrevistaController;
use App\Http\Controllers\Api\PostulacionesController;
use App\Http\Controllers\Api\AuditoriaController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/login-empleado', [AuthController::class, 'loginEmpleado']);

Route::get('/vacantes', [VacantesController::class, 'index']);
Route::get('/vacantes/{id}', [VacantesController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/auditoria/postulaciones', [AuditoriaController::class, 'postulaciones']);
    Route::get('/auditoria/vacantes', [AuditoriaController::class, 'vacantes']);
    Route::get('/vacantes/resumen', [VacantesController::class, 'resumen']);
    Route::get('/alertas', [VacantesController::class, 'alertas']);
    Route::get('/vacantes/{id}/ranking', [VacantesController::class, 'ranking']);
    Route::patch('/vacantes/{id}/estatus', [VacantesController::class, 'actualizarEstatus']);
    Route::patch('/vacantes/{id}/flujo-externo', [VacantesController::class, 'flujoExterno']);
    Route::post('/vacantes', [VacantesController::class, 'store']);
    Route::delete('/vacantes/{id}', [VacantesController::class, 'destroy']);
    Route::post('/evaluaciones-entrevista', [EvaluacionesEntrevistaController::class, 'store']);
    Route::get('/evaluaciones-entrevista/{id}', [EvaluacionesEntrevistaController::class, 'show']);
    Route::get('/postulaciones/{id}', [PostulacionesController::class, 'show']);
    Route::patch('/postulaciones/{id}/estatus', [PostulacionesController::class, 'actualizarEstatus']);
    Route::post('/postulaciones', [PostulacionesController::class, 'store']);
    Route::get('/graficas', [VacantesController::class, 'graficas']);
    Route::get('/vacantes/{id}/graficas', [VacantesController::class, 'graficasVacante']);
    Route::put('/vacantes/{id}', [VacantesController::class, 'update']);
    Route::post('/postulaciones/interno', [PostulacionesController::class, 'storeInterno']);
    Route::get('/vacantes-elegibles', [VacantesController::class, 'vacantesElegibles']);
    Route::middleware('auth:sanctum,empleados')->group(function () {
        Route::get('/vacantes-elegibles', [VacantesController::class, 'vacantesElegibles']);
    });
    Route::patch('/postulaciones/{id}/respuesta-empleado', [PostulacionesController::class, 'respuestaEmpleado']);

    // Catálogos
    Route::prefix('catalogos')->group(function () {
        Route::get('/areas', [CatalogosController::class, 'areas']);
        Route::get('/tipos-requisito', [CatalogosController::class, 'tiposRequisito']);
        Route::get('/estatus-vacante', [CatalogosController::class, 'estatusVacante']);
        Route::get('/estatus-postulacion', [CatalogosController::class, 'estatusPostulacion']);
        Route::get('/recomendaciones-entrevista', [CatalogosController::class, 'recomendacionesEntrevista']);
    });
});