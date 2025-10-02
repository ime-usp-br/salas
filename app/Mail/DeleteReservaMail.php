<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Reserva;
use App\Models\User;
use App\Models\Sala;

class DeleteReservaMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $reserva;
    public $irmaos;
    public $purge;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Reserva $reserva, bool $purge = false)
    {
        $this->reserva = $reserva;
        $this->purge = $purge;

        // Load siblings before they get deleted (for email display)
        if ($purge && $reserva->parent_id) {
            $this->irmaos = Reserva::where('parent_id', $reserva->parent_id)->get();
        }
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.delete_reserva')
                    ->subject('Exclusão de reserva — Sistema Reserva de Salas')
                    ->to($this->reserva->user->email)
                    ->with([
                        'irmaos' => $this->irmaos
                    ]);
    }
}
