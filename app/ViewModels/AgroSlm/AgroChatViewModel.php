<?php

namespace App\ViewModels\AgroSlm;

use JsonSerializable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AgroChatViewModel implements JsonSerializable
{
    /**
     * @param  list<string>  $suggestedQuestions
     * @param  list<array{id: string, title: string, subtitle: string, badge: string, params: array<string, mixed>}>  $availablePresets
     */
    public function __construct(
        public OllamaHealthViewModel $ollamaHealth,
        public KnowledgeBaseSummaryViewModel $knowledgeBase,
        public array $suggestedQuestions,
        public array $availablePresets
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'ollamaHealth' => $this->ollamaHealth->jsonSerialize(),
            'knowledgeBase' => $this->knowledgeBase->jsonSerialize(),
            'suggestedQuestions' => $this->suggestedQuestions,
            'availablePresets' => $this->availablePresets,
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
