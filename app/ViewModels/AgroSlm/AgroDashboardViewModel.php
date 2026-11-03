<?php

namespace App\ViewModels\AgroSlm;

use JsonSerializable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AgroDashboardViewModel implements JsonSerializable
{
    public function __construct(
        public OllamaHealthViewModel $ollamaHealth,
        public KnowledgeBaseSummaryViewModel $knowledgeBase,
        public DiagnosisPayloadViewModel $initialDiagnosis
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'ollamaHealth' => $this->ollamaHealth->jsonSerialize(),
            'knowledgeBase' => $this->knowledgeBase->jsonSerialize(),
            'initialDiagnosis' => $this->initialDiagnosis->jsonSerialize(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
