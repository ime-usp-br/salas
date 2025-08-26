<?php

namespace App\Http\Requests\Api;

use App\Models\Reserva;
use App\Rules\ApprovalWorkflowRule;
use Illuminate\Foundation\Http\FormRequest;

class ApproveReservaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by the ApprovalWorkflowRule
        // This ensures we have a user authenticated via API token
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reserva = $this->route('reserva');
        $action = $this->route()->getActionMethod(); // 'approve' or 'reject'

        return [
            'reserva' => [
                new ApprovalWorkflowRule($reserva, $action)
            ],
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
            'reserva.approval_workflow_rule' => 'Não é possível processar esta ação na reserva.',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        // Add the reserva to validation data so our rule can access it
        $validator->sometimes('reserva', 'required', function ($input) {
            return true;
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        // Add the reserva from route to validation data
        $this->merge([
            'reserva' => $this->route('reserva')
        ]);
    }
}