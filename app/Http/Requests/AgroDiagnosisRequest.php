<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AgroDiagnosisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bioma' => ['required', 'string', 'max:100'],
            'relevo' => ['required', 'string', 'max:120'],
            'textura_solo' => ['required', 'string', 'max:120'],
            'ph' => ['required', 'numeric', 'min:3.0', 'max:8.5'],
            'mos' => ['required', 'numeric', 'min:0.1', 'max:15.0'],
            'cultura_atual' => ['required', 'string', 'max:500'],
            'problemas' => ['nullable', 'array'],
            'problemas.*' => ['string', 'max:120'],
        ];
    }

    public function attributes(): array
    {
        return [
            'bioma' => 'Bioma',
            'relevo' => 'Relevo',
            'textura_solo' => 'Textura do Solo',
            'ph' => 'PH',
            'mos' => 'MOS',
            'cultura_atual' => 'Cultura Atual',
            'problemas' => 'Problemas',
        ];
    }
}
