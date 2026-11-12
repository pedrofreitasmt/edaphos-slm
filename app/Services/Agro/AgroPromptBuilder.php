<?php

namespace App\Services\Agro;

use Illuminate\Support\Str;

class AgroPromptBuilder
{
    /**
     * System prompt rigoroso de Doutor em Ciência do Solo e Agroecologia (Embrapa/FAO).
     */
    public const SYSTEM_PROMPT = <<<'PROMPT'
Você é o AgroSolo AI, Pesquisador Ph.D. em Ciência do Solo, Física/Química do Solo e Agroecologia (Embrapa/FAO).
Diretrizes Obrigatórias:
1. Relacione os parâmetros edafoclimáticos (Bioma, Relevo, Textura, pH em CaCl2, MOS %, Cultura e Problemas) às práticas conservacionistas.
2. Explique os mecanismos: atividade de Al3+, V%, CTC efetiva, adsorção de P, FBN (Mo/Co/bactérias diazotróficas) e dinâmica C/N da palhada no SPD.
3. Indique rotação com 3 etapas (Safra Principal, Segunda Safra/Consórcio e Entressafra/Adubação Verde) com nomes científicos.
4. Utilize os fragmentos RAG fornecidos e cite-os em "referencias_utilizadas".
5. Responda em Português do Brasil (pt-BR), de forma densa, concisa, técnica e estritamente aderente ao JSON Schema.
PROMPT;

    /**
     * Strict JSON Schema required for Ollama Structured Output (`format` parameter).
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
     *   ph: float|int|string,
     *   mos: float|int|string,
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
     *   relevance_score?: int,
     *   match_reasons?: list<string>
     * }>  $ragChunks
     */
    public function buildUserPrompt(array $params, array $ragChunks): string
    {
        $problemasTexto = count($params['problemas']) > 0
            ? implode(', ', $params['problemas'])
            : 'Nenhum problema crítico específico reportado';

        $ragContext = '';
        foreach ($ragChunks as $idx => $chunk) {
            $num = $idx + 1;
            $snippet = Str::words(strip_tags((string) ($chunk['content'] ?? '')), 75, '...');
            $ragContext .= "[DOC {$num} | {$chunk['source']} - {$chunk['title']}]: {$snippet}\n\n";
        }

        $phFormatted = number_format((float) $params['ph'], 1, '.', '');
        $mosFormatted = number_format((float) $params['mos'], 1, '.', '');

        return <<<PROMPT
=== CONTEXTO CIENTÍFICO RAG (EMBRAPA / FAO / WIKIPEDIA) ===
{$ragContext}=== CONDICIONANTES EDAFOCLIMÁTICOS ===
- Bioma: {$params['bioma']}
- Relevo: {$params['relevo']}
- Textura do Solo: {$params['textura_solo']}
- pH CaCl2: {$phFormatted}
- MOS: {$mosFormatted}%
- Histórico / Cultura: {$params['cultura_atual']}
- Problemas: {$problemasTexto}

=== INSTRUÇÕES DO LAUDO (ESTRITAMENTE CONCISO E DENSO) ===
Com base nos dados acima e no contexto RAG, gere o JSON estruturado:
1. "resumo_executivo": Síntese técnica direta de 2 a 3 frases conectando bioma, solo e intervenções.
2. "diagnostico_fisico_quimico":
   - "analise_ph_nutrientes": 2 a 3 frases sobre pH {$phFormatted}, Al3+, calagem/gessagem e P/Mo.
   - "status_materia_organica": 2 frases sobre MOS {$mosFormatted}% e retenção hídrica/estrutura.
   - "potencial_fbn": 2 frases sobre viabilidade da FBN e coinoculação sob o pH atual.
3. "plano_manejo_conservacionista":
   - "manejo_bacia_solo": Lista de 3 a 4 práticas conservacionistas de solo e água.
   - "arranjo_rotacao_culturas": Lista de 3 etapas sucessivas (Safra Principal, Segunda Safra/Consórcio e Entressafra) com espécies e objetivos.
4. "relacao_condicionantes_ambientais": Explicação concisa (2 a 3 frases) da correlação técnica.
5. "referencias_utilizadas": Títulos e fontes dos documentos RAG citados.
PROMPT;
    }
}
