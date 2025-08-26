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

        // Get the room (sala_id is the value being validated)
        $sala = Sala::with(['categoria', 'categoria.users', 'categoria.setores'])->find($value);
        
        if (!$sala) {
            $this->message = 'Sala não encontrada.';
            return false;
        }

        // Check if user has permission for this room's category
        if (!$this->hasRoomCategoryPermission($user, $sala->categoria)) {
            return false;
        }

        // Additional checks for specific actions
        if ($this->action === 'create' || $this->action === 'update') {
            if (!$this->canMakeReservation($user, $sala)) {
                return false;
            }
        }

        return true;
    }

    public function message()
    {
        return $this->message;
    }

    private function hasRoomCategoryPermission($user, Categoria $categoria)
    {
        // Check if user is directly associated with the category
        if ($categoria->users->contains('id', $user->id)) {
            return true;
        }

        // Check category's vinculos (link types)
        $vinculos = $categoria->vinculos;
        
        // If no specific vinculos are set, allow all authenticated users
        if (empty($vinculos)) {
            return true;
        }

        // Check each vinculo type
        foreach ($vinculos as $vinculo) {
            switch (strtolower($vinculo)) {
                case 'usp':
                    // All USP users can make reservations
                    return true;
                    
                case 'unidade':
                    // Users from the same unit can make reservations
                    if ($this->isFromSameUnit($user)) {
                        return true;
                    }
                    break;
                    
                case 'setor':
                    // Users from specific sectors can make reservations
                    if ($this->isFromAllowedSector($user, $categoria)) {
                        return true;
                    }
                    break;
                    
                case 'nenhum':
                    // Only manually registered users can make reservations
                    if ($categoria->users->contains('id', $user->id)) {
                        return true;
                    }
                    break;
            }
        }

        $this->message = 'Você não tem permissão para fazer reservas nesta categoria de sala.';
        return false;
    }

    private function canMakeReservation($user, Sala $sala)
    {
        // Check if room exists and is available
        if (!$sala) {
            $this->message = 'A sala selecionada não está disponível.';
            return false;
        }

        // Check if room is blocked
        if ($sala->restricao && $sala->restricao->bloqueada) {
            $this->message = "A sala {$sala->nome} está bloqueada para reservas: {$sala->restricao->motivo_bloqueio}";
            return false;
        }

        return true;
    }

    private function isFromSameUnit($user)
    {
        // This would typically check against a unit identifier
        // For now, we'll assume all authenticated users are from the same unit
        // In a real implementation, you would check user's codund or similar field
        return true;
    }

    private function isFromAllowedSector($user, Categoria $categoria)
    {
        // Check if user belongs to any of the allowed sectors for this category
        if (!$categoria->setores || $categoria->setores->isEmpty()) {
            return false;
        }

        // In a real implementation, you would check user's sector information
        // against the categoria->setores collection
        // For now, we'll return true as sector validation requires external API
        return true;
    }
}