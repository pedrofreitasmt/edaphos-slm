<?php

namespace App\Services\Agro;

class AgroPromptBuilder
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
}
