<?php

namespace App\ViewModels\AgroSlm;

use JsonSerializable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AgroChatMessageResponseViewModel implements JsonSerializable
{
    /**
     * @param  list<array<string, mixed>>  $rag_chunks
     * @param  array<string, mixed>  $metrics
     */
    public function __construct(
        public string $reply,
        public string $model,
        public string $status,
        public array $rag_chunks,
        public array $metrics
    ) {}

    /**
     * @param  array{
     *   reply: string,
     *   model: string,
     *   status: string,
     *   rag_chunks: list<array<string, mixed>>,
     *   metrics: array<string, mixed>
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            reply: (string) $data['reply'],
            model: (string) $data['model'],
            status: (string) $data['status'],
            rag_chunks: $data['rag_chunks'] ?? [],
            metrics: $data['metrics'] ?? []
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'reply' => $this->reply,
            'model' => $this->model,
            'status' => $this->status,
            'rag_chunks' => $this->rag_chunks,
            'metrics' => $this->metrics,
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
