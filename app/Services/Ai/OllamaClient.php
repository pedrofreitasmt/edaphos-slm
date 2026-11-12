<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class OllamaClient
{
    protected string $baseUrl;

    protected string $defaultModel;

    protected string $chatModel;

    protected string $fallbackModel;

    protected string $keepAlive;

    protected int $timeout;

    public function __construct(
        ?string $baseUrl = null,
        ?string $defaultModel = null,
        ?string $fallbackModel = null,
        ?int $timeout = null,
        ?string $chatModel = null,
        ?string $keepAlive = null
    ) {
        $this->baseUrl = rtrim($baseUrl ?? (string) config('ai.drivers.ollama.base_url', 'http://localhost:11434'), '/');
        $this->defaultModel = $defaultModel ?? (string) config('ai.drivers.ollama.model', 'agro-slm');
        $this->chatModel = $chatModel ?? (string) config('ai.drivers.ollama.chat_model', 'qwen2.5:3b');
        $this->fallbackModel = $fallbackModel ?? (string) config('ai.drivers.ollama.fallback_model', 'qwen2.5:3b');
        $this->keepAlive = $keepAlive ?? (string) config('ai.drivers.ollama.keep_alive', '30m');
        $this->timeout = $timeout ?? (int) config('ai.drivers.ollama.timeout', 240);
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
        $this->ensureSufficientExecutionTime();

        $primary = $model ?? $this->defaultModel;
        $secondary = $fallbackModel ?? $this->fallbackModel;

        $modelsToTry = array_values(array_unique(array_filter([$primary, $secondary])));
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
                        'keep_alive' => $this->keepAlive,
                        'options' => [
                            'temperature' => 0.15,
                            'num_predict' => 600,
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
                if (! is_array($decoded) || ! isset($decoded['resumo_executivo'])) {
                    $decoded = $this->repairJson($responseText);
                }

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

                // If the primary model executed and returned HTTP 200, do not loop over secondary models on CPU
                break;
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
     * Send conversational chat request to Ollama /api/chat.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{
     *   success: bool,
     *   used_model: string,
     *   content: string|null,
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
    public function chat(
        array $messages,
        ?string $systemPrompt = null,
        ?string $model = null
    ): array {
        $this->ensureSufficientExecutionTime();

        $candidates = $this->resolveChatCandidates($model);
        $payloadMessages = $this->buildChatMessages($messages, $systemPrompt);
        $lastError = null;

        foreach ($candidates as $candidate) {
            $startTime = microtime(true);

            try {
                $response = Http::timeout($this->timeout)
                    ->acceptJson()
                    ->post("{$this->baseUrl}/api/chat", $this->buildChatRequestPayload($candidate, $payloadMessages, false));

                if ($response->status() === 404) {
                    $lastError = "Modelo '{$candidate}' não encontrado no Ollama.";

                    continue;
                }

                if (! $response->successful()) {
                    $lastError = "Erro HTTP {$response->status()}: ".$response->body();

                    continue;
                }

                /** @var array<string, mixed> $payload */
                $payload = $response->json() ?? [];
                $assistantMessage = (string) ($payload['message']['content'] ?? '');

                if ($assistantMessage !== '') {
                    return [
                        'success' => true,
                        'used_model' => $candidate,
                        'content' => $assistantMessage,
                        'metrics' => $this->calculateMetrics($payload, $startTime),
                        'error' => null,
                    ];
                }

                $lastError = 'Resposta vazia retornada pelo modelo.';
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                break;
            }
        }

        return [
            'success' => false,
            'used_model' => $candidates[0] ?? $this->chatModel,
            'content' => null,
            'metrics' => $this->emptyMetrics(),
            'error' => $lastError,
        ];
    }

    /**
     * Stream conversational chat response from Ollama /api/chat line-by-line (NDJSON).
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @param  callable(string): void  $onChunk
     * @return array{
     *   success: bool,
     *   used_model: string,
     *   content: string|null,
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
    public function streamChat(
        array $messages,
        callable $onChunk,
        ?string $systemPrompt = null,
        ?string $model = null
    ): array {
        $this->ensureSufficientExecutionTime();

        $candidates = $this->resolveChatCandidates($model);
        $payloadMessages = $this->buildChatMessages($messages, $systemPrompt);
        $lastError = null;

        foreach ($candidates as $candidate) {
            $startTime = microtime(true);
            $fullContent = '';
            $finalPayload = [];

            try {
                $response = Http::timeout($this->timeout)
                    ->withOptions(['stream' => true])
                    ->acceptJson()
                    ->post("{$this->baseUrl}/api/chat", $this->buildChatRequestPayload($candidate, $payloadMessages, true));

                if ($response->status() === 404) {
                    $lastError = "Modelo '{$candidate}' não encontrado no Ollama.";

                    continue;
                }

                if (! $response->successful()) {
                    $lastError = "Erro HTTP {$response->status()}: ".$response->body();

                    continue;
                }

                $body = $response->toPsrResponse()->getBody();
                $buffer = '';

                while (! $body->eof()) {
                    $buffer .= $body->read(1024);

                    while (($newlinePos = strpos($buffer, "\n")) !== false) {
                        $line = trim(substr($buffer, 0, $newlinePos));
                        $buffer = substr($buffer, $newlinePos + 1);

                        if ($line === '') {
                            continue;
                        }

                        /** @var array<string, mixed>|null $chunkPayload */
                        $chunkPayload = json_decode($line, true);
                        if (! is_array($chunkPayload)) {
                            continue;
                        }

                        $delta = (string) ($chunkPayload['message']['content'] ?? '');
                        if ($delta !== '') {
                            $fullContent .= $delta;
                            $onChunk($delta);
                        }

                        if (! empty($chunkPayload['done'])) {
                            $finalPayload = $chunkPayload;
                        }
                    }
                }

                $trailing = trim($buffer);
                if ($trailing !== '') {
                    /** @var array<string, mixed>|null $chunkPayload */
                    $chunkPayload = json_decode($trailing, true);
                    if (is_array($chunkPayload)) {
                        $delta = (string) ($chunkPayload['message']['content'] ?? '');
                        if ($delta !== '') {
                            $fullContent .= $delta;
                            $onChunk($delta);
                        }
                        if (! empty($chunkPayload['done'])) {
                            $finalPayload = $chunkPayload;
                        }
                    }
                }

                if ($fullContent !== '') {
                    return [
                        'success' => true,
                        'used_model' => $candidate,
                        'content' => $fullContent,
                        'metrics' => $this->calculateMetrics($finalPayload, $startTime),
                        'error' => null,
                    ];
                }

                $lastError = 'Resposta vazia retornada pelo modelo.';
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                break;
            }
        }

        return [
            'success' => false,
            'used_model' => $candidates[0] ?? $this->chatModel,
            'content' => null,
            'metrics' => $this->emptyMetrics(),
            'error' => $lastError,
        ];
    }

    /**
     * @return list<string>
     */
    protected function resolveChatCandidates(?string $model): array
    {
        $primary = $model ?? $this->chatModel;

        return array_values(array_unique(array_filter([
            $primary,
            $this->fallbackModel,
            $this->defaultModel,
            'qwen2.5:3b',
            'qwen2.5:1.5b',
        ])));
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return list<array{role: string, content: string}>
     */
    protected function buildChatMessages(array $messages, ?string $systemPrompt): array
    {
        $payloadMessages = [];
        if ($systemPrompt) {
            $payloadMessages[] = [
                'role' => 'system',
                'content' => $systemPrompt,
            ];
        }

        foreach ($messages as $msg) {
            $payloadMessages[] = [
                'role' => (string) ($msg['role'] ?? 'user'),
                'content' => (string) ($msg['content'] ?? ''),
            ];
        }

        return $payloadMessages;
    }

    /**
     * @param  list<array{role: string, content: string}>  $payloadMessages
     * @return array<string, mixed>
     */
    protected function buildChatRequestPayload(string $model, array $payloadMessages, bool $stream): array
    {
        return [
            'model' => $model,
            'messages' => $payloadMessages,
            'stream' => $stream,
            'keep_alive' => $this->keepAlive,
            'options' => [
                'temperature' => 0.2,
                'top_p' => 0.85,
                'repeat_penalty' => 1.15,
                'num_ctx' => 3072,
                'num_predict' => 350,
            ],
        ];
    }

    /**
     * @return array{
     *   total_duration_ms: float,
     *   load_duration_ms: float,
     *   prompt_eval_count: int,
     *   eval_count: int,
     *   eval_duration_ms: float,
     *   tokens_per_second: float
     * }
     */
    protected function emptyMetrics(): array
    {
        return [
            'total_duration_ms' => 0.0,
            'load_duration_ms' => 0.0,
            'prompt_eval_count' => 0,
            'eval_count' => 0,
            'eval_duration_ms' => 0.0,
            'tokens_per_second' => 0.0,
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

    /**
     * Resilient JSON parser and repair utility for structured LLM outputs truncated by token limits.
     *
     * @return array<string, mixed>|null
     */
    public function repairJson(string $json): ?array
    {
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $trimmed = trim($json);
        if ($trimmed === '' || ! str_starts_with($trimmed, '{')) {
            return null;
        }

        // 1. If cut off inside an open string (odd count of unescaped quotes), close it
        $quotes = preg_match_all('/(?<!\\\\)"/', $trimmed);
        if ($quotes % 2 !== 0) {
            $trimmed .= '"';
        }

        // 2. Count open braces { and brackets [ outside strings
        $len = strlen($trimmed);
        $stack = [];
        $inString = false;
        for ($i = 0; $i < $len; $i++) {
            $char = $trimmed[$i];
            if ($char === '"' && ($i === 0 || $trimmed[$i - 1] !== '\\')) {
                $inString = ! $inString;

                continue;
            }
            if ($inString) {
                continue;
            }
            if ($char === '{' || $char === '[') {
                $stack[] = $char;
            } elseif ($char === '}' && end($stack) === '{') {
                array_pop($stack);
            } elseif ($char === ']' && end($stack) === '[') {
                array_pop($stack);
            }
        }

        // 3. Close remaining in reverse order
        while ($open = array_pop($stack)) {
            $trimmed .= ($open === '{') ? '}' : ']';
        }

        $fixed = json_decode($trimmed, true);

        return is_array($fixed) ? $fixed : null;
    }

    /**
     * Dynamically ensure PHP's max_execution_time covers Ollama's configured timeout + safety buffer.
     * Encapsulates execution timing inside the gateway, eliminating scattered set_time_limit calls.
     */
    protected function ensureSufficientExecutionTime(): void
    {
        $needed = $this->timeout + 30;
        $current = (int) ini_get('max_execution_time');

        if ($current > 0 && $current < $needed) {
            @ini_set('max_execution_time', (string) $needed);
            @set_time_limit($needed);
        }
    }
}
