<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\FinalidadeResource;
use App\Models\Finalidade;
use Illuminate\Http\JsonResponse;

class FinalidadeController extends Controller
{
    /**
     * Retorna todas as finalidades disponíveis.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $finalidades = Finalidade::select('id', 'legenda', 'cor')->get();

        return response()->json([
            'data' => FinalidadeResource::collection($finalidades)
        ]);
    }
}
