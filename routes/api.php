<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\VacantesController;
use App\Http\Controllers\Api\CatalogosController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    
    Route::get('/vacantes/resumen', [VacantesController::class, 'resumen']);
    Route::get('/alertas', [VacantesController::class, 'alertas']);
    Route::get('/vacantes/{id}/ranking', [VacantesController::class, 'ranking']);
    Route::patch('/vacantes/{id}/estatus', [VacantesController::class, 'actualizarEstatus']);
    Route::patch('/vacantes/{id}/flujo-externo', [VacantesController::class, 'flujoExterno']);
    Route::get('/vacantes', [VacantesController::class, 'index']);
    Route::post('/vacantes', [VacantesController::class, 'store']);
    Route::get('/vacantes/{id}', [VacantesController::class, 'show']);

    // Catálogos
    Route::prefix('catalogos')->group(function () {
        Route::get('/areas', [CatalogosController::class, 'areas']);
        Route::get('/tipos-requisito', [CatalogosController::class, 'tiposRequisito']);
        Route::get('/estatus-vacante', [CatalogosController::class, 'estatusVacante']);
        Route::get('/estatus-postulacion', [CatalogosController::class, 'estatusPostulacion']);
        Route::get('/recomendaciones-entrevista', [CatalogosController::class, 'recomendacionesEntrevista']);
    });
});