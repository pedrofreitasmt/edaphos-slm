<?php

namespace App\ViewModels\AgroSlm;

use App\Models\KnowledgeChunk;
use App\ViewModels\KnowledgeChunk\KnowledgeChunkListItemViewModel;
use Illuminate\Support\Collection;
use JsonSerializable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class KnowledgeBaseSummaryViewModel implements JsonSerializable
{
    /**
     * @param  array{Embrapa: int, FAO: int, Wikipedia: int}  $sources
     * @param  list<KnowledgeChunkListItemViewModel>  $chunks
     */
    public function __construct(
        public int $total,
        public array $sources,
        public array $chunks
    ) {}

    /**
     * @param  Collection<int, KnowledgeChunk>|array<int, KnowledgeChunk>  $chunks
     */
    public static function fromChunks(Collection|array $chunks): self
    {
        $collection = collect($chunks);

        /** @var list<KnowledgeChunkListItemViewModel> $viewModels */
        $viewModels = $collection
            ->map(fn (KnowledgeChunk $chunk): KnowledgeChunkListItemViewModel => KnowledgeChunkListItemViewModel::fromModel($chunk))
            ->values()
            ->all();

        return new self(
            total: $collection->count(),
            sources: [
                'Embrapa' => $collection->where('source', 'Embrapa')->count(),
                'FAO' => $collection->where('source', 'FAO')->count(),
                'Wikipedia' => $collection->where('source', 'Wikipedia')->count(),
            ],
            chunks: $viewModels
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'total' => $this->total,
            'sources' => $this->sources,
            'chunks' => array_map(
                fn (KnowledgeChunkListItemViewModel $c): array => $c->jsonSerialize(),
                $this->chunks
            ),
        ];
    }
}
