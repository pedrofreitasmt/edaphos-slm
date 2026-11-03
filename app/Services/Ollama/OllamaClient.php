<?php

namespace App\Services\Ollama;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class OllamaClient
{
    protected string $baseUrl;

    protected string $defaultModel;

    protected string $fallbackModel;

    protected int $timeout;

    public function __construct(
        ?string $baseUrl = null,
        ?string $defaultModel = null,
        ?string $fallbackModel = null,
        ?int $timeout = null
    ) {
        $this->baseUrl = rtrim($baseUrl ?? (string) config('services.ollama.base_url', 'http://localhost:11434'), '/');
        $this->defaultModel = $defaultModel ?? (string) config('services.ollama.model', 'agro-slm');
        $this->fallbackModel = $fallbackModel ?? (string) config('services.ollama.fallback_model', 'qwen2.5:3b');
        $this->timeout = $timeout ?? (int) config('services.ollama.timeout', 120);
    }

    /**
     * Check Ollama server health and available models.
     *
     * @return array{
     *   online: bool,
     *   base_url: string,
     *   configured_model: string,
     *   model_installed: bool,
     *   available_models: list<string>,
     *   message: string
     * }
     */
    public function checkHealth(): array
    {
        try {
            $response = Http::timeout(3)->get("{$this->baseUrl}/api/tags");

            if ($response->successful()) {
                /** @var array<int, array{name?: string}> $modelsRaw */
                $modelsRaw = $response->json('models', []);
                $availableModels = [];

                foreach ($modelsRaw as $m) {
                    if (! empty($m['name'])) {
                        $availableModels[] = (string) $m['name'];
                    }
                }

                $modelInstalled = collect($availableModels)->contains(function (string $name): bool {
                    return $name === $this->defaultModel
                        || Str::startsWith($name, $this->defaultModel.':')
                        || Str::before($name, ':') === Str::before($this->defaultModel, ':');
                });

                return [
                    'online' => true,
                    'base_url' => $this->baseUrl,
                    'configured_model' => $this->defaultModel,
                    'model_installed' => $modelInstalled,
                    'available_models' => $availableModels,
                    'message' => $modelInstalled
                        ? "Ollama ativo • Modelo '{$this->defaultModel}' pronto para inferência local"
                        : "Ollama ativo • Modelo '{$this->defaultModel}' ainda não criado (execute: ollama create agro-slm -f ai/Modelfile)",
                ];
            }
        } catch (Throwable) {
            // Ollama is offline or unreachable
        }

        return [
            'online' => false,
            'base_url' => $this->baseUrl,
            'configured_model' => $this->defaultModel,
            'model_installed' => false,
            'available_models' => [],
            'message' => "Servidor Ollama offline em {$this->baseUrl} • Motor Didático de Contingência RAG ativo",
        ];
    }

    /**
     * Send structured generation request to Ollama /api/generate with JSON schema and fallback models.
     *
     * @param  array<string, mixed>  $schema
     * @return array{
     *   success: bool,
     *   used_model: string,
     *   parsed: array<string, mixed>|null,
     *   raw_response: array<string, mixed>,
     *   metrics: array{
     *     total_duration_ms: float,
     *     load_duration_ms: float,
     *     prompt_eval_count: int,
     *     eval_count: int,
     *     eval_duration_ms: float,
     *     tokens_per_second: float
     *   },
     *   error: string|null
     * }
     */
    public function generate(
        string $systemPrompt,
        string $prompt,
        array $schema,
        ?string $model = null,
        ?string $fallbackModel = null
    ): array {
        $primary = $model ?? $this->defaultModel;
        $secondary = $fallbackModel ?? $this->fallbackModel;

        $modelsToTry = array_values(array_unique(array_filter([$primary, $secondary, 'llama3.2:3b'])));
        $lastError = null;

        foreach ($modelsToTry as $candidateModel) {
            $startTime = microtime(true);

            try {
                $response = Http::timeout($this->timeout)
                    ->acceptJson()
                    ->post("{$this->baseUrl}/api/generate", [
                        'model' => $candidateModel,
                        'system' => $systemPrompt,
                        'prompt' => $prompt,
                        'format' => $schema,
                        'stream' => false,
                        'options' => [
                            'temperature' => 0.2,
                            'num_ctx' => 4096,
                        ],
                    ]);

                if ($response->status() === 404) {
                    $lastError = "Modelo '{$candidateModel}' não encontrado no Ollama local.";

                    continue;
                }

                if (! $response->successful()) {
                    $lastError = "Erro HTTP {$response->status()} retornado pelo Ollama: ".$response->body();

                    continue;
                }

                /** @var array<string, mixed> $payload */
                $payload = $response->json() ?? [];
                $responseText = (string) ($payload['response'] ?? '');

                /** @var array<string, mixed>|null $decoded */
                $decoded = json_decode($responseText, true);

                if (is_array($decoded) && isset($decoded['resumo_executivo'])) {
                    $metrics = $this->calculateMetrics($payload, $startTime);

                    return [
                        'success' => true,
                        'used_model' => $candidateModel,
                        'parsed' => $decoded,
                        'raw_response' => $payload,
                        'metrics' => $metrics,
                        'error' => null,
                    ];
                }

                $lastError = 'A resposta do modelo não pôde ser decodificada conforme o JSON Schema.';
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                break; // If server connection failed, don't keep hammering the offline host
            }
        }

        return [
            'success' => false,
            'used_model' => $primary,
            'parsed' => null,
            'raw_response' => [],
            'metrics' => [
                'total_duration_ms' => 0.0,
                'load_duration_ms' => 0.0,
                'prompt_eval_count' => 0,
                'eval_count' => 0,
                'eval_duration_ms' => 0.0,
                'tokens_per_second' => 0.0,
            ],
            'error' => $lastError,
        ];
    }

    /**
     * Compute duration in ms and tokens/second from Ollama raw response metrics.
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *   total_duration_ms: float,
     *   load_duration_ms: float,
     *   prompt_eval_count: int,
     *   eval_count: int,
     *   eval_duration_ms: float,
     *   tokens_per_second: float
     * }
     */
    protected function calculateMetrics(array $payload, float $startTime): array
    {
        $totalDurationNs = (int) ($payload['total_duration'] ?? 0);
        $loadDurationNs = (int) ($payload['load_duration'] ?? 0);
        $promptEvalCount = (int) ($payload['prompt_eval_count'] ?? 0);
        $evalCount = (int) ($payload['eval_count'] ?? 0);
        $evalDurationNs = (int) ($payload['eval_duration'] ?? 0);

        $totalDurationMs = $totalDurationNs > 0
            ? round($totalDurationNs / 1_000_000, 1)
            : round((microtime(true) - $startTime) * 1000, 1);

        $loadDurationMs = round($loadDurationNs / 1_000_000, 1);
        $evalDurationMs = round($evalDurationNs / 1_000_000, 1);

        $tokensPerSecond = $evalDurationNs > 0
            ? round($evalCount / ($evalDurationNs / 1_000_000_000), 2)
            : ($totalDurationMs > 0 ? round($evalCount / ($totalDurationMs / 1000), 2) : 0.0);

        return [
            'total_duration_ms' => $totalDurationMs,
            'load_duration_ms' => $loadDurationMs,
            'prompt_eval_count' => $promptEvalCount,
            'eval_count' => $evalCount,
            'eval_duration_ms' => $evalDurationMs,
            'tokens_per_second' => $tokensPerSecond,
        ];
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getDefaultModel(): string
    {
        return $this->defaultModel;
    }
}
