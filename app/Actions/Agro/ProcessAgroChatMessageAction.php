<?php

namespace App\Actions\Agro;

use App\Actions\Knowledge\RetrieveChatRagChunksAction;
use App\Services\Ai\OllamaClient;
use Illuminate\Support\Str;

class ProcessAgroChatMessageAction
{
    public function __construct(
        protected RetrieveChatRagChunksAction $retrieveChatRagChunks,
        protected OllamaClient $ollamaClient
    ) {}

    /**
     * Process conversational message with RAG contextual retrieval and local SLM inference.
     *
     * @param  list<array{role: string, content: string}>  $history
     * @param  array<string, mixed>|null  $edaphicContext
     * @return array{
     *   reply: string,
     *   model: string,
     *   rag_chunks: list<array<string, mixed>>,
     *   metrics: array<string, mixed>,
     *   status: string
     * }
     */
    public function execute(string $message, array $history = [], ?array $edaphicContext = null): array
    {
        ['ragChunks' => $ragChunks, 'systemPrompt' => $systemPrompt, 'messages' => $messages] = $this->prepareChatContext(
            $message,
            $history,
            $edaphicContext
        );

        $startTime = microtime(true);
        try {
            $ollamaResult = $this->ollamaClient->chat(
                messages: $messages,
                systemPrompt: $systemPrompt
            );
        } catch (\Throwable $e) {
            $ollamaResult = [
                'success' => false,
                'used_model' => (string) config('ai.drivers.ollama.chat_model', 'qwen2.5:3b'),
                'content' => null,
                'metrics' => [],
                'error' => $e->getMessage(),
            ];
        }

        if ($ollamaResult['success'] && ! empty($ollamaResult['content'])) {
            return [
                'reply' => $ollamaResult['content'],
                'model' => $ollamaResult['used_model'],
                'rag_chunks' => $ragChunks,
                'metrics' => $ollamaResult['metrics'],
                'status' => 'online',
            ];
        }

        return $this->buildFallbackResult($message, $ragChunks, $edaphicContext, $startTime);
    }

    /**
     * Stream conversational message with RAG contextual retrieval and live token callbacks.
     *
     * @param  list<array{role: string, content: string}>  $history
     * @param  array<string, mixed>|null  $edaphicContext
     * @param  callable(list<array<string, mixed>>): void  $onMeta
     * @param  callable(string): void  $onChunk
     * @return array{
     *   reply: string,
     *   model: string,
     *   rag_chunks: list<array<string, mixed>>,
     *   metrics: array<string, mixed>,
     *   status: string
     * }
     */
    public function streamExecute(
        string $message,
        array $history,
        ?array $edaphicContext,
        callable $onMeta,
        callable $onChunk
    ): array {
        ['ragChunks' => $ragChunks, 'systemPrompt' => $systemPrompt, 'messages' => $messages] = $this->prepareChatContext(
            $message,
            $history,
            $edaphicContext
        );

        $onMeta($ragChunks);

        $startTime = microtime(true);
        try {
            $ollamaResult = $this->ollamaClient->streamChat(
                messages: $messages,
                onChunk: $onChunk,
                systemPrompt: $systemPrompt
            );
        } catch (\Throwable $e) {
            $ollamaResult = [
                'success' => false,
                'used_model' => (string) config('ai.drivers.ollama.chat_model', 'qwen2.5:3b'),
                'content' => null,
                'metrics' => [],
                'error' => $e->getMessage(),
            ];
        }

        if ($ollamaResult['success'] && ! empty($ollamaResult['content'])) {
            return [
                'reply' => $ollamaResult['content'],
                'model' => $ollamaResult['used_model'],
                'rag_chunks' => $ragChunks,
                'metrics' => $ollamaResult['metrics'],
                'status' => 'online',
            ];
        }

        $fallbackResult = $this->buildFallbackResult($message, $ragChunks, $edaphicContext, $startTime);
        $onChunk($fallbackResult['reply']);

        return $fallbackResult;
    }

    /**
     * Retrieve RAG chunks, build system prompt, and trim conversational history for fast CPU inference.
     *
     * @param  list<array{role: string, content: string}>  $history
     * @param  array<string, mixed>|null  $edaphicContext
     * @return array{
     *   ragChunks: list<array<string, mixed>>,
     *   systemPrompt: string,
     *   messages: list<array{role: string, content: string}>
     * }
     */
    protected function prepareChatContext(string $message, array $history, ?array $edaphicContext): array
    {
        $ragChunks = $this->retrieveChatRagChunks->execute($message, $edaphicContext, 2);
        $systemPrompt = $this->buildSystemPrompt($ragChunks, $edaphicContext);

        $messages = [];
        // Keep last 4 history messages to minimize prompt_eval latency on CPU
        foreach (array_slice($history, -4) as $item) {
            $role = in_array($item['role'] ?? '', ['user', 'assistant'], true) ? $item['role'] : 'user';
            $content = trim((string) ($item['content'] ?? ''));
            if ($content !== '') {
                $messages[] = [
                    'role' => $role,
                    'content' => Str::limit($content, 600),
                ];
            }
        }
        $messages[] = [
            'role' => 'user',
            'content' => $message,
        ];

        return [
            'ragChunks' => $ragChunks,
            'systemPrompt' => $systemPrompt,
            'messages' => $messages,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $ragChunks
     * @param  array<string, mixed>|null  $edaphicContext
     * @return array{
     *   reply: string,
     *   model: string,
     *   rag_chunks: list<array<string, mixed>>,
     *   metrics: array<string, mixed>,
     *   status: string
     * }
     */
    protected function buildFallbackResult(string $message, array $ragChunks, ?array $edaphicContext, float $startTime): array
    {
        $fallbackReply = $this->generateFallbackReply($message, $ragChunks, $edaphicContext);
        $durationMs = round((microtime(true) - $startTime) * 1000, 1);

        return [
            'reply' => $fallbackReply,
            'model' => 'AgroSolo AI (Síntese RAG Local)',
            'rag_chunks' => $ragChunks,
            'metrics' => [
                'total_duration_ms' => $durationMs,
                'load_duration_ms' => 0.0,
                'prompt_eval_count' => (int) round(mb_strlen($message) / 3.8),
                'eval_count' => (int) round(mb_strlen($fallbackReply) / 3.8),
                'eval_duration_ms' => $durationMs,
                'tokens_per_second' => 0.0,
            ],
            'status' => 'fallback_offline',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $ragChunks
     * @param  array<string, mixed>|null  $edaphicContext
     */
    protected function buildSystemPrompt(array $ragChunks, ?array $edaphicContext): string
    {
        $contextSection = '';
        if (! empty($edaphicContext)) {
            $bioma = $edaphicContext['bioma'] ?? 'N/A';
            $ph = isset($edaphicContext['ph']) ? number_format((float) $edaphicContext['ph'], 1, '.', '') : 'N/A';
            $mos = isset($edaphicContext['mos']) ? number_format((float) $edaphicContext['mos'], 1, '.', '') : 'N/A';
            $textura = $edaphicContext['textura_solo'] ?? 'N/A';
            $relevo = $edaphicContext['relevo'] ?? 'N/A';
            $cultura = $edaphicContext['cultura_atual'] ?? 'N/A';

            $contextSection = "CONTEXTO DA ÁREA: Bioma {$bioma} | Relevo {$relevo} | Textura {$textura} | pH {$ph} | MOS {$mos}% | Cultura: {$cultura}\n\n";
        }

        $ragSection = '';
        if (! empty($ragChunks)) {
            $ragText = '';
            foreach ($ragChunks as $idx => $chunk) {
                $num = $idx + 1;
                $snippet = Str::limit(trim((string) $chunk['content']), 1600);
                $ragText .= "[DOC {$num} | {$chunk['source']} | {$chunk['title']}]: {$snippet}\n";
            }

            $ragSection = "BASE RAG DISPONÍVEL:\n{$ragText}\n";
        }

        return <<<PROMPT
Você é o AgroSolo AI, especialista em Solos, Fertilidade e Manejo Sustentável (Embrapa/FAO).

{$contextSection}{$ragSection}REGRAS DE CONTEÚDO E FORMATAÇÃO:
- Responda em pt-BR de forma clara, técnica e objetiva, formatando a resposta em Markdown limpo:
  - Use `###` para subtítulos curtos quando houver mais de um tópico.
  - Use `**negrito**` para destacar termos-chave, nutrientes, culturas e valores.
  - Prefira listas com `-` ou `1.` em vez de blocos longos de texto corrido; use tabela Markdown simples quando comparar opções ou parâmetros.
- Perguntas simples/não-agronômicas (ex: "2+2", "olá"): responda em 1 linha direta, sem citar Embrapa/FAO.
- Perguntas técnicas: se houver BASE RAG DISPONÍVEL pertinente, baseie os fatos técnicos ESTRITAMENTE nos DOCs acima e cite a fonte (Embrapa/FAO/Wikipedia).
- REGRA ANTI-CHUTE (OBRIGATÓRIA): Se não houver BASE RAG DISPONÍVEL sobre o assunto perguntado, ou se os DOCs acima não contiverem o dado específico (leis, tributos, siglas, doses ou alíquotas), NÃO invente nem complete de cabeça. Diga claramente que essa informação específica não consta na base documental indexada no momento.
- Só cite o CONTEXTO DA ÁREA se a pergunta pedir recomendação para a propriedade.
PROMPT;
    }

    /**
     * Deterministic educational fallback when Ollama is unreachable.
     *
     * @param  list<array<string, mixed>>  $ragChunks
     * @param  array<string, mixed>|null  $edaphicContext
     */
    protected function generateFallbackReply(string $question, array $ragChunks, ?array $edaphicContext): string
    {
        if (empty($ragChunks)) {
            $trimmed = trim($question);
            // Handle basic math in offline mode directly
            if (preg_match('/^(\d+(?:\.\d+)?)\s*([\+\-\*\/])\s*(\d+(?:\.\d+)?)\s*\??$/', $trimmed, $m)) {
                $n1 = (float) $m[1];
                $op = $m[2];
                $n2 = (float) $m[3];
                $ans = match ($op) {
                    '+' => $n1 + $n2,
                    '-' => $n1 - $n2,
                    '*' => $n1 * $n2,
                    '/' => $n2 != 0.0 ? round($n1 / $n2, 4) : 'Divisão por zero',
                    default => null,
                };

                if ($ans !== null) {
                    return "{$n1} {$op} {$n2} = {$ans}";
                }
            }

            return 'Olá! Sou o AgroSolo AI. No momento meu serviço local do Ollama está offline. Como sua pergunta é geral e não demandou busca na base de conhecimento técnico de solos, tente novamente em instantes ou pergunte sobre correção de solo, pH, calagem ou biomas.';
        }

        $intro = 'Com base nos manuais técnicos da **Embrapa** e **FAO** indexados na nossa Base RAG Local:';

        $points = [];
        foreach ($ragChunks as $c) {
            $title = $c['title'] ?? 'Documento Técnico';
            $source = $c['source'] ?? 'Embrapa';
            $snippet = Str::limit($c['content'] ?? '', 220);
            $points[] = "• **{$source} ({$title}):** {$snippet}";
        }

        $pointsText = implode("\n\n", $points);

        return "{$intro}\n\n{$pointsText}\n\n*Nota: O serviço local do Ollama está temporariamente em segundo plano ou ocupado. Esta resposta foi sintetizada diretamente a partir dos fragmentos recuperados da Base de Conhecimento.*";
    }
}
