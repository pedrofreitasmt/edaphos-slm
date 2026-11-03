<?php

namespace App\ViewModels\AgroSlm;

use JsonSerializable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class RotacaoEtapaViewModel implements JsonSerializable
{
    public function __construct(
        public string $etapa,
        public string $especies,
        public string $objetivo_agronomico
    ) {}

    /**
     * @param  array{
     *   etapa?: string,
     *   especies?: string,
     *   objetivo_agronomico?: string
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            etapa: (string) ($data['etapa'] ?? ''),
            especies: (string) ($data['especies'] ?? ''),
            objetivo_agronomico: (string) ($data['objetivo_agronomico'] ?? '')
        );
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return [
            'etapa' => $this->etapa,
            'especies' => $this->especies,
            'objetivo_agronomico' => $this->objetivo_agronomico,
        ];
    }
}
