<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AgroChatMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', 'max:2000'],
            'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:4000'],
            'edaphic_context' => ['nullable', 'array'],
            'edaphic_context.bioma' => ['nullable', 'string', 'max:100'],
            'edaphic_context.ph' => ['nullable', 'numeric', 'min:3.0', 'max:8.5'],
            'edaphic_context.mos' => ['nullable', 'numeric', 'min:0.1', 'max:15.0'],
            'edaphic_context.textura_solo' => ['nullable', 'string', 'max:120'],
            'edaphic_context.relevo' => ['nullable', 'string', 'max:120'],
            'edaphic_context.cultura_atual' => ['nullable', 'string', 'max:500'],
        ];
    }
}
