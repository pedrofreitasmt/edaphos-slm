<?php

namespace App\Actions\Agro;

use App\Models\KnowledgeChunk;
use Database\Seeders\KnowledgeBaseSeeder;
use Illuminate\Support\Str;

class RetrieveRagChunksAction
{
    /**
     * Retrieve the most relevant knowledge chunks based on edaphic and environmental parameters.
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
    public function execute(array $params, int $limit = 4): array
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

            $keywords = $chunk->keywords;
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

            // Baseline score for foundational soil chemistry & SOM chunks
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
                'keywords' => array_values($keywords),
                'relevance_score' => $score,
                'match_reasons' => array_values(array_unique($reasons)),
            ];
        }

        usort($scored, fn (array $a, array $b): int => $b['relevance_score'] <=> $a['relevance_score']);

        return array_slice($scored, 0, $limit);
    }
}
