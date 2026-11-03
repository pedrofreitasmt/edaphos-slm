<?php

namespace App\ViewModels\AgroSlm;

use JsonSerializable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SlmMetricsViewModel implements JsonSerializable
{
    public function __construct(
        public float $total_duration_ms,
        public float $load_duration_ms,
        public int $prompt_eval_count,
        public int $eval_count,
        public float $eval_duration_ms,
        public float $tokens_per_second
    ) {}

    /**
     * @param  array{
     *   total_duration_ms?: float|int,
     *   load_duration_ms?: float|int,
     *   prompt_eval_count?: int,
     *   eval_count?: int,
     *   eval_duration_ms?: float|int,
     *   tokens_per_second?: float|int
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            total_duration_ms: (float) ($data['total_duration_ms'] ?? 0.0),
            load_duration_ms: (float) ($data['load_duration_ms'] ?? 0.0),
            prompt_eval_count: (int) ($data['prompt_eval_count'] ?? 0),
            eval_count: (int) ($data['eval_count'] ?? 0),
            eval_duration_ms: (float) ($data['eval_duration_ms'] ?? 0.0),
            tokens_per_second: (float) ($data['tokens_per_second'] ?? 0.0)
        );
    }

    /**
     * @return array<string, float|int>
     */
    public function jsonSerialize(): array
    {
        return [
            'total_duration_ms' => $this->total_duration_ms,
            'load_duration_ms' => $this->load_duration_ms,
            'prompt_eval_count' => $this->prompt_eval_count,
            'eval_count' => $this->eval_count,
            'eval_duration_ms' => $this->eval_duration_ms,
            'tokens_per_second' => $this->tokens_per_second,
        ];
    }
}
