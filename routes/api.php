<?php

use App\Http\Controllers\Api\V1\CategoriaController;
use App\Http\Controllers\Api\V1\FinalidadeController;
use App\Http\Controllers\Api\V1\TokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ReservaController;
use App\Http\Controllers\Api\V1\SalaController;

Route::prefix('v1')->group(function(){
    // Endpoints públicos (sem autenticação)
    Route::get('reservas', [ReservaController::class, 'getReservas']);
    Route::get('categorias', [CategoriaController::class, 'index']);
    Route::get('categorias/{categoria}', [CategoriaController::class, 'show']);
    Route::get('salas', [SalaController::class, 'index']);
    Route::get('salas/{sala}', [SalaController::class, 'show']);
    Route::get('finalidades', [FinalidadeController::class, 'index']);

    // Endpoints de autenticação
    Route::prefix('auth')->group(function() {
        // Criação de token (sem autenticação - usa email/senha)
        Route::post('token', [TokenController::class, 'create']);
        
        // Endpoints protegidos com Sanctum
        Route::middleware('auth:sanctum')->group(function() {
            Route::get('tokens', [TokenController::class, 'index']);
            Route::delete('tokens/{tokenId}', [TokenController::class, 'destroy']);
            Route::delete('tokens', [TokenController::class, 'destroyAll']);
            
            // Endpoint para verificar se o token é válido
            Route::get('user', function (Request $request) {
                return response()->json([
                    'data' => [
                        'id' => $request->user()->id,
                        'name' => $request->user()->name,
                        'email' => $request->user()->email,
                        'roles' => $request->user()->getRoleNames(),
                        'permissions' => $request->user()->getAllPermissions()->pluck('name'),
                    ]
                ]);
            });
        });
    });

    // Endpoints protegidos para CRUD de reservas
    Route::middleware('auth:sanctum')->group(function() {
        Route::post('reservas', [ReservaController::class, 'store']);
        Route::put('reservas/{reserva}', [ReservaController::class, 'update']);
        Route::delete('reservas/{reserva}', [ReservaController::class, 'destroy']);
        
        // Endpoints para aprovação/rejeição de reservas
        Route::patch('reservas/{reserva}/approve', [ReservaController::class, 'approve']);
        Route::patch('reservas/{reserva}/reject', [ReservaController::class, 'reject']);
    });
});