<?php

namespace App\Actions\Agro;

use App\Services\Agro\AgroPromptBuilder;
use App\Services\Ollama\OllamaClient;

class GenerateAgroDiagnosisAction
{
    public function __construct(
        protected RetrieveRagChunksAction $retrieveRagChunks,
        protected AgroPromptBuilder $promptBuilder,
        protected OllamaClient $ollamaClient,
        protected BuildFallbackDiagnosisAction $buildFallback
    ) {}

    /**
     * Execute full RAG retrieval + SLM inference pipeline with automatic fallback and telemetry metrics.
     *
     * @param  array{
     *   bioma: string,
     *   relevo: string,
     *   textura_solo: string,
     *   ph: float|int|string,
     *   mos: float|int|string,
     *   cultura_atual: string,
     *   problemas: list<string>
     * }  $params
     * @return array{
     *   laudo: array<string, mixed>,
     *   xray: array<string, mixed>
     * }
     */
    public function execute(array $params): array
    {
        $ragChunks = $this->retrieveRagChunks->execute($params, 4);
        $rawPrompt = $this->promptBuilder->buildUserPrompt($params, $ragChunks);
        $jsonSchema = $this->promptBuilder->getJsonSchema();
        $systemPrompt = AgroPromptBuilder::SYSTEM_PROMPT;

        $baseUrl = $this->ollamaClient->getBaseUrl();
        $configuredModel = $this->ollamaClient->getDefaultModel();

        $startTime = microtime(true);

        $ollamaResult = $this->ollamaClient->generate(
            systemPrompt: $systemPrompt,
            prompt: $rawPrompt,
            schema: $jsonSchema
        );

        if ($ollamaResult['success'] && is_array($ollamaResult['parsed'])) {
            return [
                'laudo' => $this->normalizeLaudo($ollamaResult['parsed'], $ragChunks),
                'xray' => [
                    'model' => $ollamaResult['used_model'],
                    'configured_model' => $configuredModel,
                    'base_url' => $baseUrl,
                    'status' => 'online',
                    'status_label' => '100% Local / Offline via Ollama',
                    'ollama_reachable' => true,
                    'fallback_reason' => null,
                    'metrics' => $ollamaResult['metrics'],
                    'rag_chunks' => $ragChunks,
                    'raw_prompt' => $rawPrompt,
                    'system_prompt' => $systemPrompt,
                    'json_schema' => $jsonSchema,
                ],
            ];
        }

        // Contingência Acadêmica Local caso o daemon Ollama esteja desligado
        $fallbackLaudo = $this->buildFallback->execute($params, $ragChunks);
        $elapsedMs = round((microtime(true) - $startTime) * 1000, 1);
        $estimatedPromptTokens = (int) round(mb_strlen($rawPrompt) / 3.8);
        $serializedOutput = json_encode($fallbackLaudo, JSON_UNESCAPED_UNICODE) ?: '';
        $estimatedOutputTokens = (int) round(mb_strlen($serializedOutput) / 3.8);

        return [
            'laudo' => $fallbackLaudo,
            'xray' => [
                'model' => "{$configuredModel} (Contingência Didática RAG)",
                'configured_model' => $configuredModel,
                'base_url' => $baseUrl,
                'status' => 'fallback_offline',
                'status_label' => 'Modo Contingência Local (Ollama Offline — Síntese RAG Determinística)',
                'ollama_reachable' => false,
                'fallback_reason' => $ollamaResult['error'] ?? 'Não foi possível conectar ao servidor Ollama em http://localhost:11434.',
                'metrics' => [
                    'total_duration_ms' => $elapsedMs,
                    'load_duration_ms' => 0.0,
                    'prompt_eval_count' => $estimatedPromptTokens,
                    'eval_count' => $estimatedOutputTokens,
                    'eval_duration_ms' => $elapsedMs,
                    'tokens_per_second' => 0.0,
                ],
                'rag_chunks' => $ragChunks,
                'raw_prompt' => $rawPrompt,
                'system_prompt' => $systemPrompt,
                'json_schema' => $jsonSchema,
            ],
        ];
    }

    /**
     * Ensure all required keys of the JSON Schema exist on the returned SLM object.
     *
     * @param  array<string, mixed>  $parsed
     * @param  list<array{source: string, title: string, ...}>  $ragChunks
     * @return array<string, mixed>
     */
    protected function normalizeLaudo(array $parsed, array $ragChunks): array
    {
        $diagnostico = is_array($parsed['diagnostico_fisico_quimico'] ?? null)
            ? $parsed['diagnostico_fisico_quimico']
            : [];

        $plano = is_array($parsed['plano_manejo_conservacionista'] ?? null)
            ? $parsed['plano_manejo_conservacionista']
            : [];

        $defaultRefs = array_map(
            fn (array $c): string => "{$c['source']}: {$c['title']}",
            $ragChunks
        );

        $refs = is_array($parsed['referencias_utilizadas'] ?? null) && count($parsed['referencias_utilizadas']) > 0
            ? array_values(array_map('strval', $parsed['referencias_utilizadas']))
            : $defaultRefs;

        return [
            'resumo_executivo' => (string) ($parsed['resumo_executivo'] ?? ''),
            'diagnostico_fisico_quimico' => [
                'analise_ph_nutrientes' => (string) ($diagnostico['analise_ph_nutrientes'] ?? ''),
                'status_materia_organica' => (string) ($diagnostico['status_materia_organica'] ?? ''),
                'potencial_fbn' => (string) ($diagnostico['potencial_fbn'] ?? ''),
            ],
            'plano_manejo_conservacionista' => [
                'manejo_bacia_solo' => is_array($plano['manejo_bacia_solo'] ?? null)
                    ? array_values(array_map('strval', $plano['manejo_bacia_solo']))
                    : [],
                'arranjo_rotacao_culturas' => is_array($plano['arranjo_rotacao_culturas'] ?? null)
                    ? array_values(array_map(function ($item): array {
                        $arr = is_array($item) ? $item : [];

                        return [
                            'etapa' => (string) ($arr['etapa'] ?? 'Etapa de Manejo'),
                            'especies' => (string) ($arr['especies'] ?? ''),
                            'objetivo_agronomico' => (string) ($arr['objetivo_agronomico'] ?? ''),
                        ];
                    }, $plano['arranjo_rotacao_culturas']))
                    : [],
            ],
            'relacao_condicionantes_ambientais' => (string) ($parsed['relacao_condicionantes_ambientais'] ?? ''),
            'referencias_utilizadas' => $refs,
        ];
    }
}
