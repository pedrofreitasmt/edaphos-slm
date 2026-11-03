<?php

namespace App\Services;

use App\Actions\Agro\BuildFallbackDiagnosisAction;
use App\Actions\Agro\GenerateAgroDiagnosisAction;
use App\Actions\Agro\RetrieveRagChunksAction;
use App\Models\KnowledgeChunk;
use App\Services\Agro\AgroPromptBuilder;
use App\Services\Ollama\OllamaClient;
use App\ViewModels\AgroSlm\AgroDashboardViewModel;
use App\ViewModels\AgroSlm\DiagnosisPayloadViewModel;
use App\ViewModels\AgroSlm\KnowledgeBaseSummaryViewModel;
use App\ViewModels\AgroSlm\OllamaHealthViewModel;

class AgroSlmService
{
    /**
     * System prompt rigoroso de Doutor em Ciência do Solo e Agroecologia (Embrapa/FAO).
     */
    public const SYSTEM_PROMPT = AgroPromptBuilder::SYSTEM_PROMPT;

    public function __construct(
        protected OllamaClient $ollamaClient,
        protected AgroPromptBuilder $promptBuilder,
        protected RetrieveRagChunksAction $retrieveRagChunks,
        protected BuildFallbackDiagnosisAction $buildFallback,
        protected GenerateAgroDiagnosisAction $generateDiagnosisAction
    ) {}

    /**
     * Build initial dashboard data with local RAG knowledge chunks and fallback preview.
     */
    public function getDiagnosisData(): AgroDashboardViewModel
    {
        $knowledgeBaseVm = $this->getKnowledgeBaseSummary();

        $defaultScenario = [
            'bioma' => 'Cerrado',
            'relevo' => 'Meia Encosta Suave (3-8%)',
            'textura_solo' => 'Argilosa (>35% argila)',
            'ph' => 4.6,
            'mos' => 1.4,
            'cultura_atual' => 'Monocultura de Soja em Latossolo com baixa cobertura',
            'problemas' => ['Erosão Laminar', 'Baixa Fixação de N', 'Baixa Palhada'],
        ];

        $initialRagChunks = $this->retrieveRagChunks->execute($defaultScenario, 4);
        $initialPrompt = $this->promptBuilder->buildUserPrompt($defaultScenario, $initialRagChunks);
        $initialLaudo = $this->buildFallback->execute($defaultScenario, $initialRagChunks);
        $ollamaHealth = $this->ollamaClient->checkHealth();

        $healthVm = OllamaHealthViewModel::fromArray($ollamaHealth);

        $initialDiagnosisVm = DiagnosisPayloadViewModel::fromArray([
            'laudo' => $initialLaudo,
            'xray' => [
                'model' => $ollamaHealth['configured_model'],
                'configured_model' => $ollamaHealth['configured_model'],
                'base_url' => $ollamaHealth['base_url'],
                'status' => $ollamaHealth['online'] ? 'ready' : 'fallback_offline',
                'status_label' => $ollamaHealth['online']
                    ? '100% Local / Offline via Ollama (Pronto para Inferência)'
                    : 'Pré-visualização Didática RAG (Clique em Diagnosticar para consultar o SLM)',
                'ollama_reachable' => $ollamaHealth['online'],
                'fallback_reason' => $ollamaHealth['online'] ? null : $ollamaHealth['message'],
                'metrics' => [
                    'total_duration_ms' => 0.0,
                    'load_duration_ms' => 0.0,
                    'prompt_eval_count' => (int) round(mb_strlen($initialPrompt) / 3.8),
                    'eval_count' => (int) round(mb_strlen(json_encode($initialLaudo, JSON_UNESCAPED_UNICODE) ?: '') / 3.8),
                    'eval_duration_ms' => 0.0,
                    'tokens_per_second' => 0.0,
                ],
                'rag_chunks' => $initialRagChunks,
                'raw_prompt' => $initialPrompt,
                'system_prompt' => self::SYSTEM_PROMPT,
                'json_schema' => $this->promptBuilder->getJsonSchema(),
            ],
        ]);

        return new AgroDashboardViewModel(
            ollamaHealth: $healthVm,
            knowledgeBase: $knowledgeBaseVm,
            initialDiagnosis: $initialDiagnosisVm
        );
    }

    /**
     * Retrieve all knowledge chunks from database and build the KnowledgeBaseSummary ViewModel.
     */
    public function getKnowledgeBaseSummary(): KnowledgeBaseSummaryViewModel
    {
        $allChunks = KnowledgeChunk::query()->orderBy('id')->get();

        return KnowledgeBaseSummaryViewModel::fromChunks($allChunks);
    }

    /**
     * Execute RAG retrieval + Local SLM inference via Ollama.
     *
     * @param  array{
     *   bioma: string,
     *   relevo: string,
     *   textura_solo: string,
     *   ph: float,
     *   mos: float,
     *   cultura_atual: string,
     *   problemas: list<string>
     * }
     * @return array<string, mixed>
     */
    public function generateDiagnosis(array $validatedData): array
    {
        $params = [
            'bioma' => (string) $validatedData['bioma'],
            'relevo' => (string) $validatedData['relevo'],
            'textura_solo' => (string) $validatedData['textura_solo'],
            'ph' => (float) $validatedData['ph'],
            'mos' => (float) $validatedData['mos'],
            'cultura_atual' => (string) $validatedData['cultura_atual'],
            'problemas' => array_values(array_map('strval', $validatedData['problemas'] ?? [])),
        ];

        return $this->generateDiagnosisAction->execute($params);
    }
}
