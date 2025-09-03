<?php

namespace App\Rules;

use App\Models\Sala;
use App\Models\Categoria;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class UserPermissionRule implements Rule
{
    private $request;
    private $message;
    private $action;

    public function __construct($request, $action = 'create')
    {
        $this->request = $request;
        $this->action = $action; // 'create', 'update', 'delete'
    }

    public function passes($attribute, $value)
    {
        $user = Auth::user();
        
        if (!$user) {
            $this->message = 'Usuário não autenticado.';
            return false;
        }

        // Para API, verificar apenas se é admin
        if ($user->hasRole('admin')) {
            // Admin pode fazer tudo, apenas verificar se sala existe e não está bloqueada
            $sala = Sala::with('restricao')->find($value);
            
            if (!$sala) {
                $this->message = 'Sala não encontrada.';
                return false;
            }

            // Verificar se sala não está bloqueada
            if ($sala->restricao && $sala->restricao->bloqueada) {
                $this->message = "A sala {$sala->nome} está bloqueada para reservas: {$sala->restricao->motivo_bloqueio}";
                return false;
            }

            return true;
        }

        // Usuários não-admin não podem usar a API
        $this->message = 'Apenas administradores podem criar reservas via API.';
        return false;
    }

    public function message()
    {
        return $this->message;
    }
}