<?php

namespace App\ViewModels\AgroSlm;

use JsonSerializable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class DiagnosisPayloadViewModel implements JsonSerializable
{
    public function __construct(
        public LaudoAgronomicoViewModel $laudo,
        public SlmXrayViewModel $xray
    ) {}

    /**
     * @param  array{
     *   laudo?: array<string, mixed>,
     *   xray?: array<string, mixed>
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            laudo: LaudoAgronomicoViewModel::fromArray($data['laudo'] ?? []),
            xray: SlmXrayViewModel::fromArray($data['xray'] ?? [])
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'laudo' => $this->laudo->jsonSerialize(),
            'xray' => $this->xray->jsonSerialize(),
        ];
    }
}
