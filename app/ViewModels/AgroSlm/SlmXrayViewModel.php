<?php

namespace App\ViewModels\AgroSlm;

use App\ViewModels\KnowledgeChunk\KnowledgeChunkListItemViewModel;
use JsonSerializable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SlmXrayViewModel implements JsonSerializable
{
    /**
     * @param  list<KnowledgeChunkListItemViewModel>  $rag_chunks
     * @param  array<string, mixed>  $json_schema
     */
    public function __construct(
        public string $model,
        public string $configured_model,
        public string $base_url,
        public string $status,
        public string $status_label,
        public bool $ollama_reachable,
        public ?string $fallback_reason,
        public SlmMetricsViewModel $metrics,
        public array $rag_chunks,
        public string $raw_prompt,
        public string $system_prompt,
        public array $json_schema
    ) {}

    /**
     * @param  array{
     *   model?: string,
     *   configured_model?: string,
     *   base_url?: string,
     *   status?: string,
     *   status_label?: string,
     *   ollama_reachable?: bool,
     *   fallback_reason?: string|null,
     *   metrics?: array<string, mixed>,
     *   rag_chunks?: list<array<string, mixed>>,
     *   raw_prompt?: string,
     *   system_prompt?: string,
     *   json_schema?: array<string, mixed>
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var list<array{id: int, source: string, title: string, topic: string, content: string, keywords: list<string>, relevance_score?: int, match_reasons?: list<string>}> $rawChunks */
        $rawChunks = $data['rag_chunks'] ?? [];

        $ragChunks = array_map(
            fn (array $c): KnowledgeChunkListItemViewModel => KnowledgeChunkListItemViewModel::fromArray($c),
            $rawChunks
        );

        return new self(
            model: (string) ($data['model'] ?? ''),
            configured_model: (string) ($data['configured_model'] ?? ''),
            base_url: (string) ($data['base_url'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            status_label: (string) ($data['status_label'] ?? ''),
            ollama_reachable: (bool) ($data['ollama_reachable'] ?? false),
            fallback_reason: isset($data['fallback_reason']) ? (string) $data['fallback_reason'] : null,
            metrics: SlmMetricsViewModel::fromArray($data['metrics'] ?? []),
            rag_chunks: $ragChunks,
            raw_prompt: (string) ($data['raw_prompt'] ?? ''),
            system_prompt: (string) ($data['system_prompt'] ?? ''),
            json_schema: $data['json_schema'] ?? []
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'model' => $this->model,
            'configured_model' => $this->configured_model,
            'base_url' => $this->base_url,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'ollama_reachable' => $this->ollama_reachable,
            'fallback_reason' => $this->fallback_reason,
            'metrics' => $this->metrics->jsonSerialize(),
            'rag_chunks' => array_map(
                fn (KnowledgeChunkListItemViewModel $c): array => $c->jsonSerialize(),
                $this->rag_chunks
            ),
            'raw_prompt' => $this->raw_prompt,
            'system_prompt' => $this->system_prompt,
            'json_schema' => $this->json_schema,
        ];
    }
}
