<?php

namespace App\ViewModels\KnowledgeChunk;

use App\Models\KnowledgeChunk;
use JsonSerializable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class KnowledgeChunkListItemViewModel implements JsonSerializable
{
    /**
     * @param  array<int, string>  $keywords
     * @param  list<string>|null  $match_reasons
     */
    public function __construct(
        public int $id,
        public string $source,
        public string $title,
        public string $topic,
        public string $content,
        public array $keywords,
        public ?int $relevance_score = null,
        public ?array $match_reasons = null,
    ) {}

    /**
     * @param  list<string>|null  $matchReasons
     */
    public static function fromModel(
        KnowledgeChunk $knowledgeChunk,
        ?int $relevanceScore = null,
        ?array $matchReasons = null
    ): self {
        return new self(
            id: $knowledgeChunk->id,
            source: $knowledgeChunk->source,
            title: $knowledgeChunk->title,
            topic: $knowledgeChunk->topic,
            content: $knowledgeChunk->content,
            keywords: $knowledgeChunk->keywords,
            relevance_score: $relevanceScore,
            match_reasons: $matchReasons,
        );
    }

    /**
     * @param  array{
     *   id: int,
     *   source: string,
     *   title: string,
     *   topic: string,
     *   content: string,
     *   keywords: list<string>,
     *   relevance_score?: int,
     *   match_reasons?: list<string>
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            source: (string) $data['source'],
            title: (string) $data['title'],
            topic: (string) $data['topic'],
            content: (string) $data['content'],
            keywords: $data['keywords'],
            relevance_score: isset($data['relevance_score']) ? (int) $data['relevance_score'] : null,
            match_reasons: $data['match_reasons'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'title' => $this->title,
            'topic' => $this->topic,
            'content' => $this->content,
            'keywords' => $this->keywords,
            'relevance_score' => $this->relevance_score,
            'match_reasons' => $this->match_reasons,
        ];
    }
}
