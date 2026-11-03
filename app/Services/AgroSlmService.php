<?php

namespace App\Services;

use App\Models\KnowledgeChunk;
use Database\Seeders\KnowledgeBaseSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class AgroSlmService
{
    /**
     * System prompt rigoroso de Doutor em Ciência do Solo e Agroecologia (Embrapa/FAO).
     */
    public const SYSTEM_PROMPT = <<<'PROMPT'
Você é o AgroSolo AI, um Pesquisador Sênior Ph.D. em Ciência do Solo, Física e Química do Solo, Manejo de Microbacias Hidrográficas e Agroecologia, fundamentado estritamente nos manuais técnicos da Embrapa (Empresa Brasileira de Pesquisa Agropecuária), nos boletins de solos da FAO (Organização das Nações Unidas para a Alimentação e a Agricultura) e na literatura científica consolidada de Ciências Agrárias.

DIRETRIZES TÉCNICAS OBRIGATÓRIAS:
1. Relacione rigorosamente os condicionantes edafoclimáticos informados (Bioma, Posição no Relevo/Bacia, Textura do Solo, pH em CaCl2, Matéria Orgânica - MOS %, Histórico de Uso e Problemas Observados) às recomendações de manejo conservacionista.
2. Explique os mecanismos físico-químicos e biológicos envolvidos: atividade de Al3+ (alumínio trocável), saturação por bases (V%), capacidade de troca catiônica (CTC efetiva), adsorção específica de fósforo, disponibilidade de Molibdênio (Mo) e Cobalto (Co) para o complexo enzimático da nitrogenase na Fixação Biológica de Nitrogênio (FBN via Bradyrhizobium/Rhizobium), relação C/N da palhada no Sistema Plantio Direto (SPD) e hidrodinâmica de infiltração vs. escoamento superficial na microbacia.
3. No arranjo de rotação de culturas, indique sempre pelo menos 3 etapas cronológicas claras (ex: 1. Safra Principal / Correção; 2. Segunda Safra / Consórcio Gramínea-Leguminosa; 3. Entressafra / Adubação Verde e Descompactação Biológica) citando nomes comuns e nomes científicos em itálico ou entre parênteses (ex: Crotalaria juncea, Crotalaria ochroleuca, Cajanus cajan, Urochloa ruziziensis, Pennisetum glaucum, Raphanus sativus, Arachis pintoi).
4. Utilize prioritariamente os fragmentos documentais da Base de Conhecimento Local (RAG) fornecidos no contexto e cite-os nominalmente no campo "referencias_utilizadas".
5. Responda EXCLUSIVAMENTE em Português do Brasil (pt-BR), com linguagem científica clara, densa, didática e estritamente aderente ao JSON Schema solicitado.
PROMPT;

    /**
     * Check Ollama server health and available models.
     *
     * @return array{online: bool, base_url: string, configured_model: string, model_installed: bool, available_models: list<string>, message: string}
     */
    public function checkHealth(): array
    {
        $baseUrl = rtrim((string) config('services.ollama.base_url', 'http://localhost:11434'), '/');
        $configuredModel = (string) config('services.ollama.model', 'agro-slm');

        try {
            $response = Http::timeout(3)->get("{$baseUrl}/api/tags");

            if ($response->successful()) {
                /** @var array<int, array{name?: string}> $modelsRaw */
                $modelsRaw = $response->json('models', []);
                $availableModels = [];

                foreach ($modelsRaw as $m) {
                    if (! empty($m['name'])) {
                        $availableModels[] = (string) $m['name'];
                    }
                }

                $modelInstalled = collect($availableModels)->contains(function (string $name) use ($configuredModel): bool {
                    return $name === $configuredModel
                        || Str::startsWith($name, $configuredModel.':')
                        || Str::before($name, ':') === Str::before($configuredModel, ':');
                });

                return [
                    'online' => true,
                    'base_url' => $baseUrl,
                    'configured_model' => $configuredModel,
                    'model_installed' => $modelInstalled,
                    'available_models' => $availableModels,
                    'message' => $modelInstalled
                        ? "Ollama ativo • Modelo '{$configuredModel}' pronto para inferência local"
                        : "Ollama ativo • Modelo '{$configuredModel}' ainda não criado (usará fallback disponível ou execute: ollama create agro-slm -f ai/Modelfile)",
                ];
            }
        } catch (Throwable) {
            // Ollama is offline or unreachable
        }

        return [
            'online' => false,
            'base_url' => $baseUrl,
            'configured_model' => $configuredModel,
            'model_installed' => false,
            'available_models' => [],
            'message' => "Servidor Ollama offline em {$baseUrl} • Motor Didático de Contingência RAG ativo",
        ];
    }

    /**
     * Retrieve the 4 most relevant knowledge chunks from SQLite/DB based on soil & environmental parameters.
     *
     * @param  array{
     *   bioma: string,
     *   relevo: string,
     *   textura_solo: string,
     *   ph: float,
     *   mos: float,
     *   cultura_atual: string,
     *   problemas: list<string>
     * }  $params
     * @return list<array{
     *   id: int,
     *   source: string,
     *   title: string,
     *   topic: string,
     *   content: string,
     *   keywords: list<string>,
     *   relevance_score: int,
     *   match_reasons: list<string>
     * }>
     */
    public function retrieveRelevantChunks(array $params, int $limit = 4): array
    {
        if (KnowledgeChunk::query()->count() === 0) {
            (new KnowledgeBaseSeeder)->run();
        }

        $chunks = KnowledgeChunk::query()->get();

        $biomaLower = Str::lower($params['bioma']);
        $relevoLower = Str::lower($params['relevo']);
        $texturaLower = Str::lower($params['textura_solo']);
        $culturaLower = Str::lower($params['cultura_atual']);
        $problemasLower = array_map(fn (string $p): string => Str::lower($p), $params['problemas']);
        $ph = (float) $params['ph'];
        $mos = (float) $params['mos'];

        $scored = [];

        foreach ($chunks as $chunk) {
            $score = 0;
            $reasons = [];

            $titleLower = Str::lower($chunk->title);
            $topicLower = Str::lower($chunk->topic);
            $contentLower = Str::lower($chunk->content);
            /** @var list<string> $keywords */
            $keywords = is_array($chunk->keywords) ? $chunk->keywords : [];
            $keywordsLower = array_map(fn (string $k): string => Str::lower($k), $keywords);

            // 1. Match by Biome
            if (in_array($biomaLower, $keywordsLower, true) || Str::contains($titleLower, $biomaLower)) {
                $score += 35;
                $reasons[] = "Bioma: {$params['bioma']}";
            } elseif (Str::contains($contentLower, $biomaLower)) {
                $score += 18;
                $reasons[] = "Menção ao bioma {$params['bioma']}";
            }

            // 2. Match by Soil pH condition (Acidity / Liming / FBN)
            if ($ph < 5.5) {
                if (in_array('calagem', $keywordsLower, true) || in_array('acidez', $keywordsLower, true)) {
                    $score += $ph < 5.0 ? 32 : 22;
                    $reasons[] = sprintf('Acidez do solo (pH %.1f em CaCl₂) → Calagem e neutralização de Al³⁺', $ph);
                }
                if ($ph <= 5.3 && in_array('fbn', $keywordsLower, true)) {
                    $score += 18;
                    $reasons[] = sprintf('Restrição de Mo/FBN sob pH %.1f', $ph);
                }
            }

            // 3. Match by Soil Organic Matter (MOS %)
            if ($mos < 2.2 && (in_array('mos', $keywordsLower, true) || in_array('matéria orgânica', $keywordsLower, true))) {
                $score += $mos < 1.5 ? 28 : 18;
                $reasons[] = sprintf('Baixo teor de MOS (%.1f%%) → Dinâmica C/N e SPD', $mos);
            }

            // 4. Match by Soil Texture (Sandy / Clayey / Medium)
            if (Str::contains($texturaLower, 'arenosa') && (in_array('arenosa', $keywordsLower, true) || in_array('neossolo quartzarênico', $keywordsLower, true))) {
                $score += 38;
                $reasons[] = 'Textura Arenosa (<15% argila) / Neossolo Quartzarênico';
            }
            if (Str::contains($texturaLower, 'argilosa') && in_array('latossolo', $keywordsLower, true) && $biomaLower === 'cerrado') {
                $score += 16;
                $reasons[] = 'Textura Argilosa em Latossolo';
            }

            // 5. Match by Relief / Watershed Position
            if (Str::contains($relevoLower, 'encosta forte') && (in_array('encosta forte', $keywordsLower, true) || in_array('declividade', $keywordsLower, true) || in_array('escorregamento', $keywordsLower, true))) {
                $score += 30;
                $reasons[] = 'Relevo de Encosta Forte (>15%) → Estabilidade de taludes e controle de enxurrada';
            } elseif (Str::contains($relevoLower, 'meia encosta') && (in_array('terraceamento', $keywordsLower, true) || in_array('curvas de nível', $keywordsLower, true))) {
                $score += 24;
                $reasons[] = 'Relevo de Meia Encosta Suave (3-8%) → Terraceamento e plantio em nível';
            } elseif (Str::contains($relevoLower, 'fundo de vale') && (in_array('fundo de vale', $keywordsLower, true) || in_array('várzea', $keywordsLower, true))) {
                $score += 28;
                $reasons[] = 'Posição em Fundo de Vale / Várzea → Hidromorfismo e zona ripária';
            }

            // 6. Match by Observed Environmental Problems
            foreach ($problemasLower as $index => $prob) {
                $originalProb = $params['problemas'][$index] ?? $prob;
                if (in_array($prob, $keywordsLower, true)) {
                    $score += 30;
                    $reasons[] = "Problema observado: {$originalProb}";
                } elseif (
                    (Str::contains($prob, 'erosão') && in_array('erosão laminar', $keywordsLower, true))
                    || (Str::contains($prob, 'fixação') && in_array('fbn', $keywordsLower, true))
                    || (Str::contains($prob, 'compactação') && in_array('compactação', $keywordsLower, true))
                    || (Str::contains($prob, 'nascente') && in_array('assoreamento de nascente', $keywordsLower, true))
                    || (Str::contains($prob, 'palhada') && in_array('palhada', $keywordsLower, true))
                ) {
                    $score += 28;
                    $reasons[] = "Problema observado: {$originalProb}";
                }
            }

            // 7. Match by Current Crop / Land Use History tokens
            $culturaTokens = preg_split('/[\s,.;\/()-]+/u', $culturaLower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($culturaTokens as $token) {
                if (mb_strlen($token) < 4) {
                    continue;
                }
                if (in_array($token, $keywordsLower, true) || Str::contains($titleLower, $token)) {
                    $score += 14;
                    $reasons[] = "Histórico/Cultura: termo '{$token}'";
                    break;
                }
            }

            // Baseline score for foundational soil chemistry & SOM chunks so we always have solid scientific grounding
            if ($topicLower === 'físico-química do solo' && $score === 0) {
                $score += 5;
                $reasons[] = 'Fundamento edáfico geral de Físico-Química do Solo';
            }

            $scored[] = [
                'id' => (int) $chunk->id,
                'source' => (string) $chunk->source,
                'title' => (string) $chunk->title,
                'topic' => (string) $chunk->topic,
                'content' => (string) $chunk->content,
                'keywords' => $keywords,
                'relevance_score' => $score,
                'match_reasons' => array_values(array_unique($reasons)),
            ];
        }

        usort($scored, fn (array $a, array $b): int => $b['relevance_score'] <=> $a['relevance_score']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * Get the strict JSON Schema required for Ollama Structured Outputs (`format` parameter).
     *
     * @return array<string, mixed>
     */
    public function getJsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'resumo_executivo' => [
                    'type' => 'string',
                    'description' => 'Síntese técnica relacionando o bioma e solo às práticas recomendadas',
                ],
                'diagnostico_fisico_quimico' => [
                    'type' => 'object',
                    'properties' => [
                        'analise_ph_nutrientes' => [
                            'type' => 'string',
                            'description' => 'Impacto do pH atual na disponibilidade nutricional e alumínio',
                        ],
                        'status_materia_organica' => [
                            'type' => 'string',
                            'description' => 'Avaliação da MOS e estrutura física do solo',
                        ],
                        'potencial_fbn' => [
                            'type' => 'string',
                            'description' => 'Como o cenário atual afeta a Fixação Biológica de Nitrogênio',
                        ],
                    ],
                    'required' => [
                        'analise_ph_nutrientes',
                        'status_materia_organica',
                        'potencial_fbn',
                    ],
                ],
                'plano_manejo_conservacionista' => [
                    'type' => 'object',
                    'properties' => [
                        'manejo_bacia_solo' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                            'description' => 'Lista de práticas conservacionistas de manejo de bacia e solo',
                        ],
                        'arranjo_rotacao_culturas' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'etapa' => [
                                        'type' => 'string',
                                        'description' => 'Safra Principal / Entressafra / Adubação Verde',
                                    ],
                                    'especies' => [
                                        'type' => 'string',
                                        'description' => 'Espécies recomendadas (nomes comuns e científicos)',
                                    ],
                                    'objetivo_agronomico' => [
                                        'type' => 'string',
                                        'description' => 'Descompactação, aporte de N, relação C/N, cobertura',
                                    ],
                                ],
                                'required' => [
                                    'etapa',
                                    'especies',
                                    'objetivo_agronomico',
                                ],
                            ],
                        ],
                    ],
                    'required' => [
                        'manejo_bacia_solo',
                        'arranjo_rotacao_culturas',
                    ],
                ],
                'relacao_condicionantes_ambientais' => [
                    'type' => 'string',
                    'description' => 'Explicação didática de como o bioma, relevo e solo ditam as escolhas acima',
                ],
                'referencias_utilizadas' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                    ],
                    'description' => 'Fontes da Embrapa, FAO e Wikipedia utilizadas na fundamentação',
                ],
            ],
            'required' => [
                'resumo_executivo',
                'diagnostico_fisico_quimico',
                'plano_manejo_conservacionista',
                'relacao_condicionantes_ambientais',
                'referencias_utilizadas',
            ],
        ];
    }

    /**
     * Build the RAG-augmented user prompt sent to the SLM.
     *
     * @param  array{
     *   bioma: string,
     *   relevo: string,
     *   textura_solo: string,
     *   ph: float,
     *   mos: float,
     *   cultura_atual: string,
     *   problemas: list<string>
     * }  $params
     * @param  list<array{
     *   id: int,
     *   source: string,
     *   title: string,
     *   topic: string,
     *   content: string,
     *   keywords: list<string>,
     *   relevance_score: int,
     *   match_reasons: list<string>
     * }>  $ragChunks
     */
    public function buildPrompt(array $params, array $ragChunks): string
    {
        $problemasTexto = count($params['problemas']) > 0
            ? implode(', ', $params['problemas'])
            : 'Nenhum problema crítico específico reportado';

        $ragContext = '';
        foreach ($ragChunks as $idx => $chunk) {
            $num = $idx + 1;
            $ragContext .= "[DOCUMENTO {$num} | Fonte: {$chunk['source']} | Tópico: {$chunk['topic']}]\n";
            $ragContext .= "Título: {$chunk['title']}\n";
            $ragContext .= "Conteúdo Técnico: {$chunk['content']}\n\n";
        }

        $phFormatted = number_format((float) $params['ph'], 1, '.', '');
        $mosFormatted = number_format((float) $params['mos'], 1, '.', '');

        return <<<PROMPT
=== CONTEXTO CIENTÍFICO RECUPERADO DA BASE LOCAL (RAG - EMBRAPA / FAO / WIKIPEDIA) ===
{$ragContext}=== PARÂMETROS EDAFOCLIMÁTICOS E AMBIENTAIS DA UNIDADE DE PRODUÇÃO ===
- Bioma Brasileiro: {$params['bioma']}
- Posição na Bacia Hidrográfica / Relevo: {$params['relevo']}
- Textura do Solo: {$params['textura_solo']}
- pH em CaCl2: {$phFormatted}
- Matéria Orgânica do Solo (MOS): {$mosFormatted}%
- Histórico de Uso / Cultura Atual: {$params['cultura_atual']}
- Problemas Ambientais e Edáficos Observados: {$problemasTexto}

=== TAREFA DE DIAGNÓSTICO E RECOMENDAÇÃO AGRONÔMICA ===
Com base exclusivamente nos fragmentos científicos acima (RAG) e nos condicionantes ambientais/edáficos fornecidos, elabore um Laudo Agronômico Conservacionista completo em JSON estruturado contendo:
1. "resumo_executivo": Síntese densa conectando o bioma ({$params['bioma']}), a classe textural ({$params['textura_solo']}), o pH ({$phFormatted}) e a MOS ({$mosFormatted}%) às intervenções prioritárias.
2. "diagnostico_fisico_quimico":
   - "analise_ph_nutrientes": Avaliação química detalhada do pH {$phFormatted} em CaCl2 sobre a atividade de Al3+, saturação por bases (V%), CTC efetiva, necessidade de calagem/gessagem e disponibilidade de P, Ca, Mg e Mo.
   - "status_materia_organica": Avaliação do teor de MOS de {$mosFormatted}% frente à textura ({$params['textura_solo']}), impactos na retenção de água (CAD), agregação e estabilidade estrutural.
   - "potencial_fbn": Avaliação da viabilidade da Fixação Biológica de Nitrogênio (simbiose com Bradyrhizobium/Rhizobium), disponibilidade de Molibdênio/Cobalto no pH atual e condições de aeração/temperatura para nodulação.
3. "plano_manejo_conservacionista":
   - "manejo_bacia_solo": Array com pelo menos 4 práticas detalhadas de engenharia conservacionista de solo e água adequadas à posição de relevo ({$params['relevo']}) e aos problemas ({$problemasTexto}).
   - "arranjo_rotacao_culturas": Array com 3 etapas cronológicas (Safra Principal, Segunda Safra/Consórcio e Entressafra/Adubação Verde), especificando espécies com nomes comuns e científicos e o objetivo agronômico (relação C/N, descompactação biológica, FBN, formação de palhada).
4. "relacao_condicionantes_ambientais": Explicação didática aprofundada demonstrando como a interação entre bioma, geomorfologia da bacia e atributos físico-químicos do solo determinou cada escolha técnica.
5. "referencias_utilizadas": Array listando os títulos e fontes (Embrapa, FAO, Wikipedia) dos documentos do RAG efetivamente empregados.
PROMPT;
    }

    /**
     * Execute RAG retrieval + Ollama SLM inference (with automatic model fallback and offline resilience).
     *
     * @param  array{
     *   bioma: string,
     *   relevo: string,
     *   textura_solo: string,
     *   ph: float,
     *   mos: float,
     *   cultura_atual: string,
     *   problemas: list<string>
     * }  $params
     * @return array{
     *   laudo: array<string, mixed>,
     *   xray: array<string, mixed>
     * }
     */
    public function generateDiagnosis(array $params): array
    {
        $ragChunks = $this->retrieveRelevantChunks($params, 4);
        $rawPrompt = $this->buildPrompt($params, $ragChunks);
        $jsonSchema = $this->getJsonSchema();

        $baseUrl = rtrim((string) config('services.ollama.base_url', 'http://localhost:11434'), '/');
        $configuredModel = (string) config('services.ollama.model', 'agro-slm');
        $fallbackModel = (string) config('services.ollama.fallback_model', 'qwen2.5:3b');
        $timeout = (int) config('services.ollama.timeout', 120);

        $startTime = microtime(true);

        $ollamaResult = $this->callOllamaGenerate(
            baseUrl: $baseUrl,
            model: $configuredModel,
            fallbackModel: $fallbackModel,
            prompt: $rawPrompt,
            schema: $jsonSchema,
            timeout: $timeout
        );

        if ($ollamaResult['success'] && is_array($ollamaResult['parsed'])) {
            $data = $ollamaResult['raw_response'];
            $totalDurationNs = (int) ($data['total_duration'] ?? 0);
            $loadDurationNs = (int) ($data['load_duration'] ?? 0);
            $promptEvalCount = (int) ($data['prompt_eval_count'] ?? 0);
            $evalCount = (int) ($data['eval_count'] ?? 0);
            $evalDurationNs = (int) ($data['eval_duration'] ?? 0);

            $totalDurationMs = $totalDurationNs > 0
                ? round($totalDurationNs / 1_000_000, 1)
                : round((microtime(true) - $startTime) * 1000, 1);

            $loadDurationMs = round($loadDurationNs / 1_000_000, 1);
            $evalDurationMs = round($evalDurationNs / 1_000_000, 1);

            $tokensPerSecond = $evalDurationNs > 0
                ? round($evalCount / ($evalDurationNs / 1_000_000_000), 2)
                : ($totalDurationMs > 0 ? round($evalCount / ($totalDurationMs / 1000), 2) : 0.0);

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
                    'metrics' => [
                        'total_duration_ms' => $totalDurationMs,
                        'load_duration_ms' => $loadDurationMs,
                        'prompt_eval_count' => $promptEvalCount,
                        'eval_count' => $evalCount,
                        'eval_duration_ms' => $evalDurationMs,
                        'tokens_per_second' => $tokensPerSecond,
                    ],
                    'rag_chunks' => $ragChunks,
                    'raw_prompt' => $rawPrompt,
                    'system_prompt' => self::SYSTEM_PROMPT,
                    'json_schema' => $jsonSchema,
                ],
            ];
        }

        // Graceful local academic fallback when Ollama daemon is not running or model is not yet pulled
        $fallbackLaudo = $this->buildFallbackDiagnosis($params, $ragChunks);
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
                'system_prompt' => self::SYSTEM_PROMPT,
                'json_schema' => $jsonSchema,
            ],
        ];
    }

    /**
     * Call Ollama /api/generate endpoint with JSON Schema format and automatic model fallback.
     *
     * @param  array<string, mixed>  $schema
     * @return array{
     *   success: bool,
     *   used_model: string,
     *   parsed: array<string, mixed>|null,
     *   raw_response: array<string, mixed>,
     *   error: string|null
     * }
     */
    protected function callOllamaGenerate(
        string $baseUrl,
        string $model,
        string $fallbackModel,
        string $prompt,
        array $schema,
        int $timeout
    ): array {
        $modelsToTry = array_values(array_unique(array_filter([$model, $fallbackModel, 'llama3.2:3b'])));

        $lastError = null;

        foreach ($modelsToTry as $candidateModel) {
            try {
                $response = Http::timeout($timeout)
                    ->acceptJson()
                    ->post("{$baseUrl}/api/generate", [
                        'model' => $candidateModel,
                        'system' => self::SYSTEM_PROMPT,
                        'prompt' => $prompt,
                        'format' => $schema,
                        'stream' => false,
                        'options' => [
                            'temperature' => 0.2,
                            'num_ctx' => 4096,
                        ],
                    ]);

                if ($response->status() === 404) {
                    $lastError = "Modelo '{$candidateModel}' não encontrado no Ollama local. Execute: ollama create agro-slm -f ai/Modelfile";

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
                    return [
                        'success' => true,
                        'used_model' => $candidateModel,
                        'parsed' => $decoded,
                        'raw_response' => $payload,
                        'error' => null,
                    ];
                }

                $lastError = 'O modelo retornou uma resposta que não pôde ser decodificada conforme o JSON Schema.';
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                break; // If connection failed/refused, no need to retry other models on the same unreachable host
            }
        }

        return [
            'success' => false,
            'used_model' => $model,
            'parsed' => null,
            'raw_response' => [],
            'error' => $lastError,
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

    /**
     * Build a rich, scientifically grounded agronomic diagnosis using the retrieved RAG chunks
     * when Ollama is offline, ensuring classroom demonstrations always succeed.
     *
     * @param  array{
     *   bioma: string,
     *   relevo: string,
     *   textura_solo: string,
     *   ph: float,
     *   mos: float,
     *   cultura_atual: string,
     *   problemas: list<string>
     * }  $params
     * @param  list<array{
     *   id: int,
     *   source: string,
     *   title: string,
     *   topic: string,
     *   content: string,
     *   keywords: list<string>,
     *   relevance_score: int,
     *   match_reasons: list<string>
     * }>  $ragChunks
     * @return array<string, mixed>
     */
    public function buildFallbackDiagnosis(array $params, array $ragChunks): array
    {
        $bioma = $params['bioma'];
        $relevo = $params['relevo'];
        $textura = $params['textura_solo'];
        $ph = (float) $params['ph'];
        $mos = (float) $params['mos'];
        $cultura = $params['cultura_atual'];
        $problemas = $params['problemas'];
        $problemasStr = count($problemas) > 0 ? implode(', ', $problemas) : 'degradação edáfica incipiente';

        $phFormatted = number_format($ph, 1, ',', '');
        $mosFormatted = number_format($mos, 1, ',', '');

        // 1. Análise de pH e Nutrientes
        if ($ph < 4.9) {
            $analisePh = "O pH em CaCl₂ de {$phFormatted} caracteriza acidez ativa muito elevada, com forte atividade de alumínio trocável (Al³⁺) tóxico aos meristemas radiculares e intensa adsorção específica/precipitação de fósforo (P) nos óxidos de Fe e Al. Há severa deficiência de bases trocáveis (Ca²⁺ e Mg²⁺), baixa CTC efetiva nas cargas variáveis e insolubilização do Molibdênio (MoO₄²⁻). Recomenda-se calagem corretiva com calcário dolomítico (PRNT > 80%) para elevar a saturação por bases (V%) a 60–70% e situar o pH em CaCl₂ entre 5,5 e 6,0, associada à gessagem agrícola (CaSO₄·2H₂O) para fornecer Ca²⁺ e neutralizar Al³⁺ em subsuperfície (20–60 cm).";
        } elseif ($ph < 5.5) {
            $analisePh = "O pH em CaCl₂ de {$phFormatted} situa-se na faixa de acidez média a moderada. Embora a toxidez por Al³⁺ seja moderada, a disponibilidade de fósforo lábil e de Molibdênio ainda encontra-se subótima para máxima eficiência simbiótica e nutricional. Recomenda-se calagem de manutenção/correção ajustada à textura ({$textura}) — evitando supercalagem especialmente se o solo tiver baixa capacidade tampão — visando V% entre 55% e 65% e pH em CaCl₂ na faixa de 5,6 a 6,0.";
        } else {
            $analisePh = "O pH em CaCl₂ de {$phFormatted} encontra-se em faixa agronomicamente adequada (neutralização de Al³⁺ assegurada e boa saturação por bases V%). Nessa condição, a disponibilidade de macronutrientes (N, P, K, Ca, Mg, S) e de Molibdênio é favorável; o manejo químico deve focar na manutenção da fertilidade via ciclagem biológica de nutrientes, reposição gradual pela exportação das colheitas e monitoramento de micronutrientes catiônicos (Zn, Mn, Cu, Fe, B).";
        }

        // 2. Status da Matéria Orgânica e Física do Solo
        $isArenoso = Str::contains(Str::lower($textura), 'arenosa');
        $isArgiloso = Str::contains(Str::lower($textura), 'argilosa');

        if ($mos < 1.5 || ($isArgiloso && $mos < 2.0)) {
            $statusMos = "O teor de Matéria Orgânica do Solo (MOS) de {$mosFormatted}% é criticamente baixo para um solo de textura {$textura}. Como a MOS responde por 70% a 90% da CTC dos solos tropicais, esse déficit compromete drasticamente a retenção de cátions (K⁺, Ca²⁺, Mg²⁺), reduz a Capacidade de Água Disponível (CAD), desestabiliza os macroagregados e expõe a superfície ao selamento superficial pelo impacto das gotas de chuva. É indispensável adotar o Sistema Plantio Direto (SPD) com aporte mínimo de 8 a 10 t/ha/ano de matéria seca combinando gramíneas de alta relação C/N (> 40:1) e leguminosas.";
        } elseif ($mos < 2.8) {
            $statusMos = "O teor de MOS de {$mosFormatted}% encontra-se em nível intermediário para a classe textural ({$textura}), mas vulnerável à oxidação rápida caso haja revolvimento mecânico ou pousio descoberto. A manutenção da estabilidade de agregados, da macroporosidade de aeração (> 10%) e da infiltração hídrica requer cobertura morta permanente e fluxo contínuo de exsudatos radiculares e glomalina via rotação diversificada.";
        } else {
            $statusMos = "O teor de MOS de {$mosFormatted}% apresenta bom patamar de conservação orgânica, favorecendo a estruturação em grumos, a atividade da microbiota edáfica, a complexação orgânica de Al³⁺ e a capacidade tampão do solo. O objetivo prioritário deve ser preservar esse estoque de carbono evitando o preparo convencional e mantendo a cobertura viva/morta durante todo o ciclo hidrológico.";
        }

        // 3. Potencial de Fixação Biológica de Nitrogênio (FBN)
        if ($ph < 5.2 || $mos < 1.5 || in_array('Baixa Fixação de N', $problemas, true)) {
            $potencialFbn = "O potencial atual de Fixação Biológica de Nitrogênio (FBN) encontra-se severamente limitado. Sob pH {$phFormatted} e MOS de {$mosFormatted}%, há restrição na disponibilidade de Molibdênio (cofator FeMoCo da enzima nitrogenase) e Fósforo (ATP para redução do N₂), além de estresse térmico e hídrico na rizosfera que reduz a sobrevivência de estirpes de Bradyrhizobium e Rhizobium. Após a correção da acidez e cobertura do solo, recomenda-se coinoculação (Bradyrhizobium spp. + Azospirillum brasilense) associada à aplicação de Co e Mo via semente ou foliar.";
        } else {
            $potencialFbn = "O ambiente edáfico (pH {$phFormatted} e MOS {$mosFormatted}%) oferece condições favoráveis ao estabelecimento da simbiose diazotrófica, desde que assegurada a aeração do espaço poroso (ausência de compactação/hipóxia para a respiração nodular) e o suprimento de P, Co e Mo. A introdução de leguminosas de adubação verde potencializará aportes de 120 a 250 kg/ha de N biológico.";
        }

        // 4. Manejo de Bacia e Solo
        $manejoBacia = [];
        if (Str::contains(Str::lower($relevo), 'encosta forte')) {
            $manejoBacia[] = 'Implantação de terraços em gradiente (canais divergentes revestidos com vegetação rasteira) e cordões de contorno vegetados com Capim-vetiver (Chrysopogon zizanioides) para dissipar a energia cinética da enxurrada sem provocar supersaturação pontual que deflagre escorregamentos translacionais em Argissolos/Cambissolos.';
            $manejoBacia[] = 'Conversão das áreas de declividade > 15% para Sistemas Silvipastoris em nível (piqueteamento rotacionado seguindo as cotas topográficas) ou Sistemas Agroflorestais (SAFs) multiestratos, vedando o terço superior das encostas para recarga hídrica.';
        } elseif (Str::contains(Str::lower($relevo), 'meia encosta')) {
            $manejoBacia[] = 'Dimensionamento de terraços de base larga ou embutida em nível combinados com semeadura estritamente em contorno (curvas de nível) para seccionar o comprimento de rampa e maximizar a infiltração de água no perfil.';
            $manejoBacia[] = 'Construção de bacias de captação de enxurrada (barraginhas) nas margens de carreadores e estradas rurais para retenção de sedimentos e recarga do lençol freático da microbacia.';
        } elseif (Str::contains(Str::lower($relevo), 'fundo de vale')) {
            $manejoBacia[] = 'Isolamento e recomposição da Zona Ripária (Mata Ciliar com faixa tampão mínima de 30 a 50 m) utilizando espécies nativas adaptadas a regime hidromórfico sazonal, atuando como filtro biogeoquímico de sedimentos, fósforo particulado e nitrato.';
            $manejoBacia[] = 'Manejo conservacionista de solos hidromórficos sem drenagem predatória, mantendo cobertura vegetal permanente e controle rigoroso da carga animal para evitar pisoteio em solo saturado.';
        } else {
            $manejoBacia[] = 'Adoção integral do Sistema Plantio Direto (SPD) em nível no Topo de Chapada (área prioritária de recarga de aquíferos da bacia hidrográfica), garantindo taxa de infiltração básica elevada e impedindo a geração de escoamento superficial para as vertentes inferiores.';
            $manejoBacia[] = 'Subdivisão da gleba com faixas de retenção vegetadas e quebra-ventos arbóreos (integração lavoura-floresta) para reduzir a evapotranspiração potencial e a erosão laminar/eólica.';
        }

        if (in_array('Compactação', $problemas, true)) {
            $manejoBacia[] = 'Descompactação biológica do perfil ("biodrilling") por meio de plantas de cobertura com raízes pivotantes e fasciculadas agressivas, aliada ao controle de tráfego agrícola e respeito à faixa de friabilidade do solo.';
        }
        if (in_array('Assoreamento de Nascente', $problemas, true)) {
            $manejoBacia[] = 'Cercamento imediato das nascentes (raio mínimo de 50 m), reconformação de taludes erodidos com bioengenharia de solos (biomantas e paliçadas de bambu) e enriquecimento florestal com espécies pioneiras e secundárias.';
        }
        if (in_array('Baixa Palhada', $problemas, true) || $isArenoso) {
            $manejoBacia[] = 'Consorciação de gramíneas forrageiras tropicais (Sistema Santa Fé / Integração Lavoura-Pecuária) para garantir aporte mínimo de 8 a 10 t/ha/ano de matéria seca com relação C/N equilibrada e alta persistência sobre o solo.';
        }

        // 5. Arranjo de Rotação de Culturas customizado por Bioma/Textura
        if ($bioma === 'Mata Atlântica' && Str::contains(Str::lower($relevo), 'encosta')) {
            $rotacao = [
                [
                    'etapa' => '1ª Etapa — Recuperação de Base e Cobertura Viva em Nível (Primavera/Verão)',
                    'especies' => 'Consórcio de Braquiária-brizantha cv. Marandu (Urochloa brizantha) + Amendoim-forrageiro (Arachis pintoi) + Feijão-guandu (Cajanus cajan)',
                    'objetivo_agronomico' => 'Estabelecimento de tapete herbáceo perene de alta cobertura contra impacto da gota de chuva, descompactação biológica pelo sistema pivotante do Guandu e aporte contínuo de N via FBN do Arachis pintoi (120–180 kg N/ha/ano).',
                ],
                [
                    'etapa' => '2ª Etapa — Faixas de Adubação Verde e Ciclagem de Fósforo (Outono/Inverno)',
                    'especies' => 'Nabo-forrageiro (Raphanus sativus var. oleiferus) + Aveia-preta (Avena strigosa) + Ervilhaca (Vicia sativa)',
                    'objetivo_agronomico' => 'Rompimento de camadas adensadas superficiais causadas por pisoteio animal, proteção do horizonte A contra chuvas frontais e imobilização temporária de nutrientes evitando lixiviação na encosta.',
                ],
                [
                    'etapa' => '3ª Etapa — Estrato Arbustivo-Arbóreo / Sistema Silvipastoril ou SAF em Contorno',
                    'especies' => 'Alamedas em nível de Gliricídia (Gliricidia sepium), Margaridão (Tithonia diversifolia), Inga edulis e espécies nativas da Mata Atlântica',
                    'objetivo_agronomico' => 'Ancoragem mecânica profunda do solo no contato A/Bt do Argissolo (prevenção de escorregamentos), poda periódica para adubação verde rica em N, P e K e conforto térmico.',
                ],
            ];
        } elseif ($isArenoso) {
            $rotacao = [
                [
                    'etapa' => '1ª Etapa — Adubação Verde e Estruturação Inicial (Primavera/Verão)',
                    'especies' => 'Consórcio de Milheto (Pennisetum glaucum) + Crotalária-ochroleuca (Crotalaria ochroleuca) ou Feijão-caupi (Vigna unguiculata)',
                    'objetivo_agronomico' => 'Formação rápida de biomassa aérea e radicular em solo arenoso de baixa fertilidade, fixação biológica de N (100–150 kg/ha), supressão de nematoides e criação de "esponja orgânica" para elevar a retenção hídrica (CAD).',
                ],
                [
                    'etapa' => '2ª Etapa — Safra Principal Conservacionista sob Palhada Densa',
                    'especies' => 'Cultura principal (Soja Glycine max, Milho Zea mays ou Mandioca Manihot esculenta em faixas) inoculada com Bradyrhizobium + Azospirillum brasilense',
                    'objetivo_agronomico' => 'Produção econômica sobre espessa camada de palhada de alta relação C/N do Milheto, reduzindo a temperatura superficial do Neossolo Quartzarênico em até 12 °C e minimizando perdas de N e K por lixiviação.',
                ],
                [
                    'etapa' => '3ª Etapa — Segunda Safra / Entressafra de Persistência (Sistema Santa Fé)',
                    'especies' => 'Consórcio de Urochloa ruziziensis (ou Urochloa brizantha cv. Piatã) + Feijão-guandu-anão (Cajanus cajan)',
                    'objetivo_agronomico' => 'Aporte maciço de raízes finas e exsudatos micorrízicos (glomalina) para agregação dos grãos de quartzo, elevação gradual da MOS (> 1,5%) e cobertura permanente durante a estação seca.',
                ],
            ];
        } else {
            $rotacao = [
                [
                    'etapa' => '1ª Etapa — Adubação Verde Pré-Safra / Descompactação e FBN (Início das Águas)',
                    'especies' => 'Crotalaria juncea (ou Crotalaria spectabilis) consorciada com Milheto (Pennisetum glaucum)',
                    'objetivo_agronomico' => 'Aporte rápido de Nitrogênio via FBN (150–220 kg N/ha), manejo biológico de fitonematoides e produção de palhada de relação C/N intermediária (~25:1) para destravar a atividade microbiana do solo.',
                ],
                [
                    'etapa' => '2ª Etapa — Safra Principal de Verão em Sistema Plantio Direto (SPD)',
                    'especies' => 'Soja (Glycine max) ou Feijão com coinoculação (Bradyrhizobium japonicum/elkanii + Azospirillum brasilense) e aplicação de Co + Mo',
                    'objetivo_agronomico' => 'Maximização da eficiência simbiótica da nitrogenase após correção da acidez (pH > 5,5), aproveitando a estrutura física preservada e a umidade retida pela palhada antecessora.',
                ],
                [
                    'etapa' => '3ª Etapa — Segunda Safra / Entressafra Consorciada (Sistema Santa Fé)',
                    'especies' => 'Milho (Zea mays) ou Sorgo (Sorghum bicolor) consorciado com Urochloa ruziziensis e Feijão-guandu (Cajanus cajan)',
                    'objetivo_agronomico' => 'Descompactação biológica subsuperficial pela raiz pivotante do Guandu, intensa formação de bioporos e deposição de 8 a 12 t/ha de palhada de alta relação C/N (> 40:1) pela Braquiária para proteção durante o período seco.',
                ],
            ];
        }

        $referencias = array_map(
            fn (array $c): string => "{$c['source']} — {$c['title']}",
            $ragChunks
        );

        return [
            'resumo_executivo' => "Diagnóstico agroecológico e edafoclimático para o bioma {$bioma} em condição de {$relevo}, solo de textura {$textura} com pH em CaCl₂ {$phFormatted} e MOS de {$mosFormatted}% (histórico: {$cultura}; problemas: {$problemasStr}). A estratégia prioritária integra a correção da acidez e neutralização do Al³⁺ no perfil à reconstrução da infraestrutura biológica do solo via Sistema Plantio Direto (SPD), consórcio de gramíneas e leguminosas fixadoras de N e contenção hidrossedimentológica na microbacia.",
            'diagnostico_fisico_quimico' => [
                'analise_ph_nutrientes' => $analisePh,
                'status_materia_organica' => $statusMos,
                'potencial_fbn' => $potencialFbn,
            ],
            'plano_manejo_conservacionista' => [
                'manejo_bacia_solo' => $manejoBacia,
                'arranjo_rotacao_culturas' => $rotacao,
            ],
            'relacao_condicionantes_ambientais' => "A tomada de decisão agronômica neste cenário decorre diretamente da interação entre os fatores de formação e uso do solo: no bioma {$bioma}, a posição topográfica de {$relevo} associada à textura {$textura} governa o balanço entre infiltração vertical e escoamento superficial (enxurrada). Quimicamente, o pH {$phFormatted} e o teor de MOS de {$mosFormatted}% ditam a carga líquida dos coloides (CTC efetiva), a solubilidade do fósforo e do molibdênio e a viabilidade da simbiose com Bradyrhizobium. Por isso, práticas mecânicas na bacia (como controle de comprimento de rampa e proteção ripária) são indissociáveis de práticas edáficas e vegetativas (calagem, aporte de palhada com alta relação C/N e descompactação radicular), convertendo um agroecossistema degradado em um sistema resiliente conservador de água, carbono e biodiversidade.",
            'referencias_utilizadas' => $referencias,
        ];
    }
}
