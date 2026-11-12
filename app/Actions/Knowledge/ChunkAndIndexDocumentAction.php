<?php

namespace App\Actions\Knowledge;

use App\Models\KnowledgeChunk;
use Illuminate\Support\Str;

class ChunkAndIndexDocumentAction
{
    /**
     * Dicionário de termos agronômicos para extração automática de palavras-chave.
     *
     * @var list<string>
     */
    public const DOMAIN_KEYWORDS = [
        'solo', 'solos', 'pedologia', 'acidez', 'calagem', 'calcário', 'calcario', 'gesso', 'gessagem',
        'ph', 'aluminio', 'alumínio', 'al3+', 'fósforo', 'fosforo', 'nitrogênio', 'nitrogenio',
        'potássio', 'potassio', 'cálcio', 'calcio', 'magnésio', 'magnesio', 'enxofre',
        'matéria orgânica', 'materia organica', 'húmus', 'humus', 'mos', 'ctc', 'v%',
        'saturação por bases', 'capacidade de troca catiônica', 'intemperismo', 'lixiviação',
        'cerrado', 'amazônia', 'caatinga', 'pampa', 'pantanal', 'mata atlântica',
        'latossolo', 'argissolo', 'neossolo', 'nitossolo', 'cambissolo', 'gleissolo', 'planossolo',
        'arenoso', 'argiloso', 'plantio direto', 'palhada', 'erosão', 'erosao', 'voçoroca',
        'compactação', 'compactacao', 'infiltração', 'infiltracao', 'terraceamento', 'curvas de nível',
        'crotalária', 'crotalaria', 'braquiária', 'braquiaria', 'milheto', 'feijão-guandu', 'nabo-forrageiro',
        'soja', 'milho', 'algodão', 'café', 'cana-de-açúcar', 'trigo', 'pastagem', 'ilpf',
        'adubação', 'adubacao', 'adubação verde', 'fixação biológica de nitrogênio', 'fbn',
        'bradyrhizobium', 'azospirillum', 'inoculante', 'rizóbio', 'micorriza',
        'ferrugem', 'ferrugem asiática', 'phakopsora', 'fungicida', 'fungicidas', 'multissítio',
        'nematoide', 'nematoides', 'galha', 'cisto', 'lesões', 'pratylenchus', 'meloidogyne',
        'bacillus', 'purpureocillium', 'trichoderma', 'controle biológico', 'bioinsumo', 'biomaphos',
        'boro', 'zinco', 'cobre', 'manganês', 'molibdênio', 'cobalto', 'ferro', 'micronutrientes',
        'reforma tributária', 'ibs', 'cbs', 'crédito presumido', 'lcdpr', 'funrural',
    ];

    /**
     * Common Portuguese & English stop words ignored during automatic keyword extraction.
     *
     * @var list<string>
     */
    protected const STOP_WORDS = [
        'para', 'como', 'mais', 'menos', 'muito', 'muita', 'muitos', 'muitas', 'sobre',
        'entre', 'após', 'apos', 'antes', 'onde', 'quando', 'porque', 'qual', 'quais',
        'quem', 'este', 'esta', 'estes', 'estas', 'esse', 'essa', 'esses', 'essas',
        'isso', 'isto', 'aquilo', 'aquele', 'aquela', 'pelo', 'pela', 'pelos', 'pelas',
        'numa', 'numas', 'neste', 'nesta', 'nesse', 'nessa', 'dele', 'dela', 'deles',
        'delas', 'seus', 'suas', 'nosso', 'nossa', 'pode', 'podem', 'sido', 'esta',
        'está', 'estão', 'estao', 'será', 'sera', 'foram', 'tem', 'têm', 'como',
        'também', 'tambem', 'ainda', 'mesmo', 'mesma', 'outro', 'outra', 'outros',
        'outras', 'cada', 'toda', 'todo', 'todos', 'todas', 'parte', 'forma', 'modo',
        'através', 'atraves', 'atualmente', 'geralmente', 'durante', 'contra', 'sempre',
        'with', 'that', 'this', 'from', 'they', 'have', 'been', 'were', 'which',
        'their', 'there', 'about', 'into', 'than', 'then', 'only', 'such', 'also',
    ];

    /**
     * Sanitize, split into overlapping chunks, tag keywords, and persist idempotently into knowledge_chunks.
     *
     * @param  list<string>  $extraKeywords
     * @return list<array{
     *   id: int,
     *   source: string,
     *   title: string,
     *   topic: string,
     *   keywords_count: int,
     *   words_count: int
     * }>
     */
    public function execute(
        string $rawText,
        string $baseTitle,
        string $source = 'Web',
        string $topic = 'Conhecimento Geral',
        int $wordsPerChunk = 350,
        int $overlap = 50,
        array $extraKeywords = []
    ): array {
        $cleanSource = trim($source) !== '' ? Str::limit(trim($source), 60, '') : 'Web';
        $cleanTopic = trim($topic) !== '' ? Str::limit(trim($topic), 120, '') : 'Conhecimento Geral';
        $cleanedText = $this->sanitizeText($rawText);

        if (mb_strlen($cleanedText) < 80) {
            return [];
        }

        $chunks = $this->splitIntoOverlappingChunks($cleanedText, $wordsPerChunk, $overlap);
        $totalParts = count($chunks);
        $results = [];

        foreach ($chunks as $index => $chunkText) {
            $partNum = $index + 1;
            $chunkTitle = $totalParts > 1
                ? "{$baseTitle} (Parte {$partNum}/{$totalParts})"
                : $baseTitle;

            $keywords = $this->extractKeywords($chunkText, $extraKeywords, $baseTitle, $cleanTopic);

            $dbChunk = KnowledgeChunk::updateOrCreate(
                ['title' => $chunkTitle],
                [
                    'source' => $cleanSource,
                    'topic' => $cleanTopic,
                    'content' => $chunkText,
                    'keywords' => $keywords,
                ]
            );

            $results[] = [
                'id' => $dbChunk->id,
                'source' => $dbChunk->source,
                'title' => $dbChunk->title,
                'topic' => $dbChunk->topic,
                'keywords_count' => count($keywords),
                'words_count' => str_word_count($chunkText),
            ];
        }

        return $results;
    }

    /**
     * Sanitize raw text by stripping noisy footer sections, normalizing whitespace and line breaks.
     */
    public function sanitizeText(string $text): string
    {
        // Strip Wikipedia/article boilerplate footer sections (Ver também, Referências, Ligações externas, Bibliografia)
        $text = preg_replace('/\n==+\s*(Ver também|Referências|Ligações externas|Bibliografia|Notas)\s*==+.*$/isu', '', $text) ?? $text;

        // Convert Wikipedia section headers (=== Título ===) into clean readable headers
        $text = preg_replace('/==+\s*(.*?)\s*==+/u', "\n[$1]\n", $text) ?? $text;

        // Normalize spaces and multiple blank lines
        $normalized = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $normalized = preg_replace('/(\r\n|\r|\n){3,}/u', "\n\n", $normalized) ?? $normalized;

        return trim($normalized);
    }

    /**
     * Split long text into overlapping chunks based on word count.
     *
     * @return list<string>
     */
    public function splitIntoOverlappingChunks(string $text, int $chunkSize = 350, int $overlap = 50): array
    {
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $totalWords = count($words);

        if ($totalWords <= $chunkSize) {
            return [$text];
        }

        $chunks = [];
        $step = max(1, $chunkSize - $overlap);

        for ($i = 0; $i < $totalWords; $i += $step) {
            $slice = array_slice($words, $i, $chunkSize);
            $chunkString = trim(implode(' ', $slice));

            if (mb_strlen($chunkString) > 100) {
                $chunks[] = $chunkString;
            }

            if ($i + $chunkSize >= $totalWords) {
                break;
            }
        }

        return $chunks;
    }

    /**
     * Extract relevant keywords from text using both automatic term frequency and optional domain dictionary.
     *
     * @param  list<string>  $extraKeywords
     * @return list<string>
     */
    public function extractKeywords(
        string $text,
        array $extraKeywords = [],
        string $title = '',
        string $topic = ''
    ): array {
        $lower = Str::lower($text);
        $found = array_map(fn (string $k) => Str::lower(trim($k)), $extraKeywords);

        foreach (self::DOMAIN_KEYWORDS as $kw) {
            if (Str::contains($lower, $kw)) {
                $found[] = $kw;
            }
        }

        // Domain-agnostic extraction: include significant title/topic terms + top frequent terms in chunk
        $headerWords = preg_split('/[^\p{L}\p{N}-]+/u', Str::lower("{$title} {$topic}"), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($headerWords as $word) {
            if (mb_strlen($word) >= 4 && ! is_numeric($word) && ! in_array($word, self::STOP_WORDS, true)) {
                $found[] = $word;
            }
        }

        $bodyWords = preg_split('/[^\p{L}\p{N}-]+/u', $lower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $frequencies = [];
        foreach ($bodyWords as $word) {
            if (mb_strlen($word) < 4 || is_numeric($word) || in_array($word, self::STOP_WORDS, true)) {
                continue;
            }
            $frequencies[$word] = ($frequencies[$word] ?? 0) + 1;
        }

        arsort($frequencies);
        $topDynamicTerms = array_slice(array_keys($frequencies), 0, 10);
        foreach ($topDynamicTerms as $term) {
            $found[] = $term;
        }

        return array_values(array_unique(array_filter($found)));
    }
}
