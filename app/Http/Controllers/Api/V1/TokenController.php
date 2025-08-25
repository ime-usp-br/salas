<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TokenController extends Controller
{
    /**
     * Gera um token de API para o usuário autenticado.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function create(Request $request): JsonResponse
    {
        // Rate limiting por usuário
        $key = 'token-creation:' . $request->ip();
        
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Muitas tentativas. Tente novamente em ' . RateLimiter::availableIn($key) . ' segundos.',
            ]);
        }

        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'token_name' => 'sometimes|string|max:255',
        ]);

        $user = \App\Models\User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($key);
            
            throw ValidationException::withMessages([
                'email' => 'As credenciais fornecidas estão incorretas.',
            ]);
        }

        // Limpa rate limiting após sucesso
        RateLimiter::clear($key);

        $tokenName = $request->token_name ?? 'API Token';
        
        // Cria o token
        $token = $user->createToken($tokenName);

        // Log da criação do token
        \Log::info('Token API criado', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'token_name' => $tokenName,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Token criado com sucesso',
            'data' => [
                'token' => $token->plainTextToken,
                'token_name' => $tokenName,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            ]
        ], 201);
    }

    /**
     * Lista todos os tokens do usuário autenticado.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $tokens = $user->tokens()->select([
            'id', 
            'name', 
            'last_used_at', 
            'created_at'
        ])->get();

        return response()->json([
            'data' => $tokens->map(function ($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'last_used_at' => $token->last_used_at?->format('d/m/Y H:i'),
                    'created_at' => $token->created_at->format('d/m/Y H:i'),
                ];
            })
        ]);
    }

    /**
     * Revoga um token específico do usuário.
     *
     * @param Request $request
     * @param int $tokenId
     * @return JsonResponse
     */
    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        $user = $request->user();
        $token = $user->tokens()->where('id', $tokenId)->first();

        if (!$token) {
            return response()->json([
                'error' => 'Token não encontrado',
                'message' => 'O token especificado não existe ou não pertence ao usuário autenticado.'
            ], 404);
        }

        $tokenName = $token->name;
        $token->delete();

        // Log da revogação do token
        \Log::info('Token API revogado', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'token_id' => $tokenId,
            'token_name' => $tokenName,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Token revogado com sucesso',
            'data' => [
                'token_name' => $tokenName,
                'revoked_at' => now()->format('d/m/Y H:i')
            ]
        ]);
    }

    /**
     * Revoga todos os tokens do usuário autenticado.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $tokenCount = $user->tokens()->count();
        
        // Revoga todos os tokens
        $user->tokens()->delete();

        // Log da revogação de todos os tokens
        \Log::info('Todos os tokens API revogados', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'tokens_count' => $tokenCount,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Todos os tokens foram revogados com sucesso',
            'data' => [
                'tokens_revoked' => $tokenCount,
                'revoked_at' => now()->format('d/m/Y H:i')
            ]
        ]);
    }
}