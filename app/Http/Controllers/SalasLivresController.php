<?php

namespace App\Http\Controllers;

use App\Models\Sala;
use Carbon\Carbon;
use App\Http\Requests\SalaLivreRequest;

class SalasLivresController extends Controller
{
    public function index()
    {
        return view('sala.salas_livres', ['today' => Carbon::today()->format('d/m/Y')]);
    }

    //pega as reservas que não estão ocupadas nos horários solicitados
    public function search(SalaLivreRequest $request)
    {
        $validated = $request->validated();

        // Check if this is a recurring search
        $isRecurring = !empty($validated['repeat_days']) &&
                       !empty($validated['repeat_until']) &&
                       is_array($validated['repeat_days']);

        if ($isRecurring) {
            $salas = Sala::SalasLivresRecurringQuery($validated);
        } else {
            $salas = Sala::SalasLivresQuery($validated);
        }

        return response()->json($salas);
    }
}
