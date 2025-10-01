<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SyncCategoriasRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // API users with valid token can sync categories
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'categoria_ids' => 'required|array|min:1',
            'categoria_ids.*' => 'required|integer|exists:categorias,id',
        ];
    }

    /**
     * Get the validation messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'categoria_ids.required' => 'É necessário informar pelo menos uma categoria.',
            'categoria_ids.array' => 'Os IDs de categorias devem ser fornecidos como um array.',
            'categoria_ids.min' => 'É necessário informar pelo menos uma categoria.',
            'categoria_ids.*.required' => 'Cada ID de categoria é obrigatório.',
            'categoria_ids.*.integer' => 'Cada ID de categoria deve ser um número inteiro.',
            'categoria_ids.*.exists' => 'Uma ou mais categorias selecionadas não existem no sistema.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'categoria_ids' => 'IDs de categorias',
            'categoria_ids.*' => 'ID de categoria',
        ];
    }
}
