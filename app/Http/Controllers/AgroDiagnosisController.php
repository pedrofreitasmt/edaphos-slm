<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeChunk;
use App\Services\AgroSlmService;
use Database\Seeders\KnowledgeBaseSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgroDiagnosisController extends Controller
{
    /**
     * Display the AgroSolo AI scientific dashboard.
     */
    public function index(AgroSlmService $service): Response
    {
        if (KnowledgeChunk::query()->count() === 0) {
            (new KnowledgeBaseSeeder)->run();
        }

        $allChunks = KnowledgeChunk::query()
            ->orderBy('id')
            ->get()
            ->map(fn (KnowledgeChunk $chunk): array => [
                'id' => $chunk->id,
                'source' => $chunk->source,
                'title' => $chunk->title,
                'topic' => $chunk->topic,
                'content' => $chunk->content,
                'keywords' => $chunk->keywords,
            ])
            ->values()
            ->all();

        $defaultScenario = [
            'bioma' => 'Cerrado',
            'relevo' => 'Meia Encosta Suave (3-8%)',
            'textura_solo' => 'Argilosa (>35% argila)',
            'ph' => 4.6,
            'mos' => 1.4,
            'cultura_atual' => 'Monocultura de Soja em Latossolo com baixa cobertura',
            'problemas' => ['Erosão Laminar', 'Baixa Fixação de N', 'Baixa Palhada'],
        ];

        $initialRagChunks = $service->retrieveRelevantChunks($defaultScenario, 4);
        $initialPrompt = $service->buildPrompt($defaultScenario, $initialRagChunks);
        $initialLaudo = $service->buildFallbackDiagnosis($defaultScenario, $initialRagChunks);
        $ollamaHealth = $service->checkHealth();

        return Inertia::render('AgroSlm/Dashboard', [
            'ollamaHealth' => $ollamaHealth,
            'knowledgeBase' => [
                'total' => count($allChunks),
                'sources' => [
                    'Embrapa' => collect($allChunks)->where('source', 'Embrapa')->count(),
                    'FAO' => collect($allChunks)->where('source', 'FAO')->count(),
                    'Wikipedia' => collect($allChunks)->where('source', 'Wikipedia')->count(),
                ],
                'chunks' => $allChunks,
            ],
            'initialDiagnosis' => [
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
                        'total_duration_ms' => 0,
                        'load_duration_ms' => 0,
                        'prompt_eval_count' => (int) round(mb_strlen($initialPrompt) / 3.8),
                        'eval_count' => (int) round(mb_strlen(json_encode($initialLaudo, JSON_UNESCAPED_UNICODE) ?: '') / 3.8),
                        'eval_duration_ms' => 0,
                        'tokens_per_second' => 0,
                    ],
                    'rag_chunks' => $initialRagChunks,
                    'raw_prompt' => $initialPrompt,
                    'system_prompt' => AgroSlmService::SYSTEM_PROMPT,
                    'json_schema' => $service->getJsonSchema(),
                ],
            ],
        ]);
    }

    /**
     * Execute RAG retrieval + Local SLM inference via Ollama.
     */
    public function diagnose(Request $request, AgroSlmService $service): JsonResponse|Response
    {
        /** @var array{
         *   bioma: string,
         *   relevo: string,
         *   textura_solo: string,
         *   ph: numeric-string|float|int,
         *   mos: numeric-string|float|int,
         *   cultura_atual: string,
         *   problemas?: array<int, string>
         * } $validated
         */
        $validated = $request->validate([
            'bioma' => ['required', 'string', 'max:100'],
            'relevo' => ['required', 'string', 'max:120'],
            'textura_solo' => ['required', 'string', 'max:120'],
            'ph' => ['required', 'numeric', 'min:3.0', 'max:8.5'],
            'mos' => ['required', 'numeric', 'min:0.1', 'max:15.0'],
            'cultura_atual' => ['required', 'string', 'max:500'],
            'problemas' => ['nullable', 'array'],
            'problemas.*' => ['string', 'max:120'],
        ]);

        $params = [
            'bioma' => (string) $validated['bioma'],
            'relevo' => (string) $validated['relevo'],
            'textura_solo' => (string) $validated['textura_solo'],
            'ph' => (float) $validated['ph'],
            'mos' => (float) $validated['mos'],
            'cultura_atual' => (string) $validated['cultura_atual'],
            'problemas' => array_values(array_map('strval', $validated['problemas'] ?? [])),
        ];

        $result = $service->generateDiagnosis($params);

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return Inertia::render('AgroSlm/Dashboard', [
            'ollamaHealth' => $service->checkHealth(),
            'initialDiagnosis' => $result,
        ]);
    }

    /**
     * Check live Ollama connection status and available local models.
     */
    public function status(AgroSlmService $service): JsonResponse
    {
        return response()->json($service->checkHealth());
    }
}
