<?php

namespace App\ViewModels\AgroSlm;

use JsonSerializable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class DiagnosticoFisicoQuimicoViewModel implements JsonSerializable
{
    public function __construct(
        public string $analise_ph_nutrientes,
        public string $status_materia_organica,
        public string $potencial_fbn
    ) {}

    /**
     * @param  array{
     *   analise_ph_nutrientes?: string,
     *   status_materia_organica?: string,
     *   potencial_fbn?: string
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            analise_ph_nutrientes: (string) ($data['analise_ph_nutrientes'] ?? ''),
            status_materia_organica: (string) ($data['status_materia_organica'] ?? ''),
            potencial_fbn: (string) ($data['potencial_fbn'] ?? '')
        );
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return [
            'analise_ph_nutrientes' => $this->analise_ph_nutrientes,
            'status_materia_organica' => $this->status_materia_organica,
            'potencial_fbn' => $this->potencial_fbn,
        ];
    }
}
