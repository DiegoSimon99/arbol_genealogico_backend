<?php

use App\Http\Controllers\TreeController;
use Illuminate\Support\Facades\Route;

Route::prefix('tree')->group(function () {
    Route::post('/person', [TreeController::class, 'store']); // crear persona
    Route::post('/attach', [TreeController::class, 'attach']); // definir padre->hijo
    Route::post('/move', [TreeController::class, 'move']); // mover subárbol
    Route::delete('/person/{id}', [TreeController::class, 'destroy']); // eliminar subárbol

    Route::get('/{rootId}/bfs', [TreeController::class, 'bfs']); // Anchura                   
    Route::get('/{rootId}/dfs', [TreeController::class, 'dfs']); // Profundidad
    Route::get('/person/{id}', [TreeController::class, 'show']); // Buscar persona
    Route::get('/{rootId}/max-depth', [TreeController::class, 'maxDepth']); // Profundidad maxima
    Route::get('/{id}/descendants/count', [TreeController::class, 'countDescendants']); // Número total de descendientes
});
