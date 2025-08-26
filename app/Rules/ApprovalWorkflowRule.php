<?php

namespace App\Rules;

use App\Models\Reserva;
use App\Models\Sala;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class ApprovalWorkflowRule implements Rule
{
    private $reserva;
    private $action;
    private $message;

    public function __construct($reserva, $action = 'approve')
    {
        $this->reserva = $reserva;
        $this->action = $action; // 'approve' or 'reject'
    }

    public function passes($attribute, $value)
    {
        // Ensure we have a valid reservation
        if (!$this->reserva instanceof Reserva) {
            $this->message = 'Reserva não encontrada.';
            return false;
        }

        // Check if reservation is in pending status
        if (!$this->isPendingStatus()) {
            return false;
        }

        // Check if reservation is not in the past
        if (!$this->isNotPastReservation()) {
            return false;
        }

        // Check if user has permission to approve/reject
        if (!$this->hasApprovalPermission()) {
            return false;
        }

        // Check if reservation doesn't conflict with room restrictions after approval
        if ($this->action === 'approve' && !$this->meetsRoomRestrictions()) {
            return false;
        }

        return true;
    }

    public function message()
    {
        return $this->message;
    }

    private function isPendingStatus()
    {
        if ($this->reserva->status !== 'pendente') {
            $statusMessages = [
                'aprovada' => 'já está aprovada',
                'rejeitada' => 'já foi rejeitada'
            ];
            
            $statusText = $statusMessages[$this->reserva->status] ?? 'não está pendente';
            $actionText = $this->action === 'approve' ? 'aprovada' : 'rejeitada';
            
            $this->message = "A reserva não pode ser {$actionText} porque {$statusText}.";
            return false;
        }
        
        return true;
    }

    private function isNotPastReservation()
    {
        try {
            // Parse reservation date (stored in d/m/Y format)
            $reservationDate = Carbon::createFromFormat('d/m/Y', $this->reserva->data);
            
            if ($reservationDate->isPast()) {
                $actionText = $this->action === 'approve' ? 'aprovar' : 'rejeitar';
                $this->message = "Não é possível {$actionText} reservas de datas passadas.";
                return false;
            }
        } catch (\Exception $e) {
            $this->message = 'Data da reserva inválida.';
            return false;
        }
        
        return true;
    }

    private function hasApprovalPermission()
    {
        $user = Auth::user();
        
        if (!$user) {
            $this->message = 'Usuário não autenticado.';
            return false;
        }

        // Check if user is responsible for the room
        $isResponsible = $this->reserva->sala->responsaveis->contains('id', $user->id);
        
        // Check if user is admin (with error handling)
        $isAdmin = false;
        if (method_exists($user, 'hasRole')) {
            try {
                $adminRoles = ['admin', 'administrator', 'superadmin', 'super-admin'];
                foreach ($adminRoles as $role) {
                    if ($user->hasRole($role)) {
                        $isAdmin = true;
                        break;
                    }
                }
            } catch (\Exception $e) {
                // Role check failed, continue as non-admin
            }
        }

        if (!$isResponsible && !$isAdmin) {
            $actionText = $this->action === 'approve' ? 'aprovar' : 'rejeitar';
            $this->message = "Apenas responsáveis pela sala podem {$actionText} reservas.";
            return false;
        }

        return true;
    }

    private function meetsRoomRestrictions()
    {
        $sala = $this->reserva->sala;
        
        // If room has no restrictions, allow approval
        if (!$sala->restricao) {
            return true;
        }

        // Check if room is blocked
        if ($sala->restricao->bloqueada) {
            $this->message = "Não é possível aprovar reservas para a sala {$sala->nome} pois está bloqueada: {$sala->restricao->motivo_bloqueio}";
            return false;
        }

        // Check minimum advance time restriction
        if ($sala->restricao->dias_antecedencia > 0) {
            try {
                $reservationDate = Carbon::createFromFormat('d/m/Y', $this->reserva->data);
                $daysInAdvance = Carbon::now()->diffInDays($reservationDate, false);
                
                if ($daysInAdvance < $sala->restricao->dias_antecedencia) {
                    $this->message = "Não é possível aprovar reservas para a sala {$sala->nome} com menos de {$sala->restricao->dias_antecedencia} dias de antecedência.";
                    return false;
                }
            } catch (\Exception $e) {
                $this->message = 'Erro ao validar antecedência mínima da reserva.';
                return false;
            }
        }

        // Check date limits for AUTO type restrictions
        if ($sala->restricao->tipo_restricao === 'AUTO') {
            try {
                $reservationDate = Carbon::createFromFormat('d/m/Y', $this->reserva->data);
                $limitDate = Carbon::now()->addDays($sala->restricao->dias_limite);
                
                if ($reservationDate->isAfter($limitDate)) {
                    $this->message = "Não é possível aprovar reservas para a sala {$sala->nome} além da data limite: {$limitDate->format('d/m/Y')}.";
                    return false;
                }
            } catch (\Exception $e) {
                $this->message = 'Erro ao validar limite de data da reserva.';
                return false;
            }
        }

        // Check fixed date limits
        if ($sala->restricao->tipo_restricao === 'FIXA') {
            try {
                $reservationDate = Carbon::createFromFormat('d/m/Y', $this->reserva->data);
                
                if ($reservationDate->isAfter($sala->restricao->data_limite)) {
                    $limitDateFormatted = Carbon::parse($sala->restricao->data_limite)->format('d/m/Y');
                    $this->message = "Não é possível aprovar reservas para a sala {$sala->nome} além da data limite: {$limitDateFormatted}.";
                    return false;
                }
            } catch (\Exception $e) {
                $this->message = 'Erro ao validar data limite fixa da reserva.';
                return false;
            }
        }

        return true;
    }
}