<?php

namespace App\Actions\Knowledge;

use App\Models\KnowledgeChunk;
use Database\Seeders\KnowledgeBaseSeeder;
use Illuminate\Support\Str;

class RetrieveChatRagChunksAction
{
    /**
     * Stop words to ignore when scoring user questions.
     *
     * @var list<string>
     */
    protected const STOP_WORDS = [
        'de', 'da', 'do', 'das', 'dos', 'em', 'no', 'na', 'nos', 'nas',
        'para', 'por', 'com', 'sem', 'como', 'qual', 'quais', 'que',
        'quem', 'onde', 'quando', 'porque', 'por que', 'o', 'a', 'os',
        'as', 'um', 'uma', 'uns', 'umas', 'meu', 'minha', 'este', 'esta',
        'esse', 'essa', 'isso', 'isto', 'fazer', 'devo', 'posso', 'sera',
        'sobre', 'qualquer', 'muito', 'pouco', 'mais', 'menos',
        'quanto', 'quantos', 'quanta', 'quantas', 'ola', 'olá', 'oi', 'opa',
        'bom', 'boa', 'dia', 'tarde', 'noite', 'voce', 'você', 'vc', 'tu',
        'ele', 'ela', 'eles', 'elas', 'saber', 'queria', 'gostaria', 'ajuda',
        'ajudar', 'soma', 'resultado', 'calculo', 'calcule', 'diga', 'fale',
    ];

    /**
     * Domain triggers that indicate the query is within agronomy, soils, crops or environmental domain.
     *
     * @var list<string>
     */
    protected const AGRONOMIC_DOMAIN_TRIGGERS = [
        'solo', 'solos', 'terra', 'edafico', 'edáfico', 'bioma', 'cerrado', 'arenoso', 'argiloso',
        'calagem', 'calcario', 'calcário', 'gesso', 'ph', 'acidez', 'aluminio', 'alumínio',
        'adubacao', 'adubação', 'adubo', 'npk', 'nitrogenio', 'nitrogênio', 'fosforo', 'fósforo',
        'potassio', 'potássio', 'materia organica', 'matéria orgânica', 'mos', 'ctc', 'v%',
        'cultura', 'soja', 'milho', 'pastagem', 'crotalaria', 'crotalária', 'braquiaria', 'braquiária',
        'plantio', 'palhada', 'erosao', 'erosão', 'compactacao', 'compactação', 'infiltracao', 'infiltração',
        'embrapa', 'fao', 'fertilidade', 'micronutrientes', 'micronutriente', 'macronutrientes', 'nodulacao', 'nodulação',
        'fbn', 'bactéria', 'bacteria', 'inoculante', 'safra', 'safrinha', 'lavoura', 'manejo',
        'produtor', 'fazenda', 'gleba', 'talhao', 'talhão', 'area', 'área', 'nutriente', 'nutrientes',
        'calcítica', 'dolomítico', 'dolomitico',
        // Proteção de Cultivos e Fitossanidade
        'ferrugem', 'ferrugem asiática', 'phakopsora', 'fungicida', 'fungicidas', 'multissítio', 'multissitio',
        'vazio sanitário', 'vazio sanitario', 'praga', 'pragas', 'doença', 'doenças', 'doenca', 'doencas',
        'nematoide', 'nematoides', 'galha', 'galhas', 'cisto', 'cistos', 'lesoes', 'lesões',
        'meloidogyne', 'pratylenchus', 'heterodera', 'bionematicida', 'bacillus',
        // Micronutrientes
        'boro', 'zinco', 'cobre', 'manganês', 'manganes', 'supercalagem',
        // Gestão Tributária e Financeira no Agro
        'tributaria', 'tributária', 'tributarias', 'tributárias', 'tributario', 'tributário',
        'tributacao', 'tributação', 'reforma tributária', 'reforma tributaria', 'imposto', 'impostos',
        'ibs', 'cbs', 'crédito presumido', 'credito presumido', 'aliquota', 'alíquota', 'insumos',
        'lcdpr', 'funrural', 'itr', 'senar', 'receita rural',
    ];

    /**
     * Retrieve the most relevant knowledge chunks based on the user's conversational question and optional context.
     *
     * @param  array<string, mixed>|null  $edaphicContext
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
    public function execute(string $question, ?array $edaphicContext = null, int $limit = 3): array
    {
        if (KnowledgeChunk::query()->count() === 0) {
            (new KnowledgeBaseSeeder)->run();
        }

        $chunks = KnowledgeChunk::query()->get();

        $questionLower = Str::lower($question);
        $rawWords = preg_split('/[^\p{L}\p{N}]+/u', $questionLower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $searchTerms = array_values(array_filter(
            $rawWords,
            fn (string $w): bool => mb_strlen($w) >= 3 && ! in_array($w, self::STOP_WORDS, true)
        ));

        // Check if question mentions any domain-relevant trigger
        $hasDomainTrigger = false;
        foreach (self::AGRONOMIC_DOMAIN_TRIGGERS as $trigger) {
            if (Str::contains($questionLower, $trigger)) {
                $hasDomainTrigger = true;
                break;
            }
        }

        $contextBiome = isset($edaphicContext['bioma']) ? Str::lower((string) $edaphicContext['bioma']) : null;
        $contextPh = isset($edaphicContext['ph']) ? (float) $edaphicContext['ph'] : null;

        $scored = [];

        foreach ($chunks as $chunk) {
            $score = 0;
            $reasons = [];

            $titleLower = Str::lower($chunk->title);
            $topicLower = Str::lower($chunk->topic);
            $contentLower = Str::lower($chunk->content);

            $keywords = $chunk->keywords;
            $keywordsLower = array_map(fn (string $k): string => Str::lower($k), $keywords);

            // 1. Direct Term Matches in Keywords and Title
            $termMatches = 0;
            foreach ($searchTerms as $term) {
                // Exact match in keywords (e.g. "calagem", "fbn", "palhada", "crotalaria", "latossolo")
                if (in_array($term, $keywordsLower, true)) {
                    $score += 35;
                    $termMatches++;
                    $reasons[] = "Palavra-chave exata: '{$term}'";
                } elseif (Str::contains($titleLower, $term)) {
                    $score += 25;
                    $termMatches++;
                    $reasons[] = "Presente no título: '{$term}'";
                } elseif (Str::contains($topicLower, $term)) {
                    $score += 15;
                    $termMatches++;
                    $reasons[] = "Tópico técnico: '{$term}'";
                } elseif (Str::contains($contentLower, $term)) {
                    $score += 10;
                    $termMatches++;
                }
            }

            // 2. Edaphic Context Boost: ONLY applies if query has term matches OR explicit agronomic domain triggers
            if ($termMatches > 0 || $hasDomainTrigger) {
                if ($contextBiome && (in_array($contextBiome, $keywordsLower, true) || Str::contains($titleLower, $contextBiome))) {
                    $score += 15;
                    $reasons[] = "Alinhado ao bioma {$edaphicContext['bioma']}";
                }

                if ($contextPh !== null && $contextPh < 5.2 && in_array('acidez', $keywordsLower, true)) {
                    $score += 10;
                    $reasons[] = 'Acidez do solo contextual';
                }
            }

            // Keep only chunks that actually met the minimum relevance threshold
            if ($score >= 20) {
                $scored[] = [
                    'id' => $chunk->id,
                    'source' => $chunk->source,
                    'title' => $chunk->title,
                    'topic' => $chunk->topic,
                    'content' => $chunk->content,
                    'keywords' => $chunk->keywords,
                    'relevance_score' => $score,
                    'match_reasons' => array_values(array_unique($reasons)),
                ];
            }
        }

        // If no chunk reached minimum relevance or query is general (e.g., 2+2, greetings), return empty
        if (empty($scored)) {
            return [];
        }

        usort($scored, fn (array $a, array $b): int => $b['relevance_score'] <=> $a['relevance_score']);

        return array_slice($scored, 0, $limit);
    }
}
