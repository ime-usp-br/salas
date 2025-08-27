<?php

use App\Http\Controllers\Api\V1\CategoriaController;
use App\Http\Controllers\Api\V1\FinalidadeController;
use App\Http\Controllers\Api\V1\TokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ReservaController;
use App\Http\Controllers\Api\V1\SalaController;

Route::prefix('v1')->group(function(){
    // Endpoints públicos (sem autenticação) - Rate limiting mais permissivo
    Route::middleware(['throttle:public'])->group(function() {
        Route::get('reservas', [ReservaController::class, 'getReservas']);
        Route::get('categorias', [CategoriaController::class, 'index']);
        Route::get('categorias/{categoria}', [CategoriaController::class, 'show']);
        Route::get('salas', [SalaController::class, 'index']);
        Route::get('salas/{sala}', [SalaController::class, 'show']);
        Route::get('salas/{sala}/availability', [SalaController::class, 'availability']);
        Route::get('finalidades', [FinalidadeController::class, 'index']);
    });

    // Endpoints de autenticação - Rate limiting mais restritivo
    Route::prefix('auth')->group(function() {
        // Criação de token (sem autenticação - usa email/senha) - Rate limiting para auth
        Route::middleware(['throttle:auth'])->group(function() {
            Route::post('token', [TokenController::class, 'create']);
        });
        
        // Endpoints protegidos com Sanctum - Rate limiting para API autenticada
        Route::middleware(['auth:sanctum', 'throttle:api'])->group(function() {
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

    // Endpoints protegidos para CRUD de reservas - Rate limiting específico para reservas
    Route::middleware(['auth:sanctum', 'throttle:reservations'])->group(function() {
        // Endpoints de consulta de reservas do usuário
        Route::get('reservas/my', [ReservaController::class, 'myReservations']);
        
        // CRUD de reservas
        Route::post('reservas', [ReservaController::class, 'store']);
        Route::put('reservas/{reserva}', [ReservaController::class, 'update']);
        Route::delete('reservas/{reserva}', [ReservaController::class, 'destroy']);
        
        // Endpoints para aprovação/rejeição de reservas - Rate limiting para admin
        Route::middleware(['throttle:admin'])->group(function() {
            Route::patch('reservas/{reserva}/approve', [ReservaController::class, 'approve']);
            Route::patch('reservas/{reserva}/reject', [ReservaController::class, 'reject']);
        });
    });

    // Endpoints para operações em lote - Rate limiting para bulk operations
    Route::middleware(['auth:sanctum', 'throttle:bulk'])->group(function() {
        // Bulk creation of reservations
        Route::post('reservas/bulk', [ReservaController::class, 'bulkStore']);
        
        // Bulk update of reservations
        Route::put('reservas/bulk', [ReservaController::class, 'bulkUpdate']);
        
        // Bulk delete of reservations
        Route::delete('reservas/bulk', [ReservaController::class, 'bulkDestroy']);
    });
});