<?php

namespace Tests\Feature;

use App\Actions\Knowledge\RetrieveChatRagChunksAction;
use App\Models\KnowledgeChunk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AgroChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_visit_agro_chat_page(): void
    {
        KnowledgeChunk::create([
            'source' => 'Embrapa',
            'title' => 'Manejo de Solos no Cerrado',
            'topic' => 'Biomas',
            'content' => 'O bioma Cerrado exige calagem e adubação equilibrada com fósforo.',
            'keywords' => ['cerrado', 'calagem', 'fósforo'],
        ]);

        $response = $this->get(route('agro-slm.chat'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('AgroSlm/Chat')
            ->has('ollamaHealth')
            ->has('knowledgeBase')
            ->has('suggestedQuestions')
            ->has('availablePresets')
        );
    }

    public function test_post_chat_message_requires_message_content(): void
    {
        $response = $this->postJson(route('agro-slm.chat.message'), [
            'message' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['message']);
    }

    public function test_post_chat_message_returns_json_response_with_rag_and_reply(): void
    {
        KnowledgeChunk::create([
            'source' => 'Embrapa',
            'title' => 'Correção de Acidez e Calagem',
            'topic' => 'Físico-Química do Solo',
            'content' => 'O calcário dolomítico neutraliza o alumínio trocável e eleva o pH.',
            'keywords' => ['calagem', 'calcário', 'alumínio', 'ph'],
        ]);

        $payload = [
            'message' => 'Como o calcário neutraliza o alumínio do solo?',
            'history' => [
                ['role' => 'user', 'content' => 'Olá'],
                ['role' => 'assistant', 'content' => 'Olá! Como posso ajudar?'],
            ],
            'edaphic_context' => [
                'bioma' => 'Cerrado',
                'ph' => 4.6,
                'mos' => 1.4,
                'textura_solo' => 'Argilosa (>35% argila)',
            ],
        ];

        $response = $this->postJson(route('agro-slm.chat.message'), $payload);

        $response->assertOk();
        $response->assertJsonStructure([
            'reply',
            'model',
            'status',
            'rag_chunks',
            'metrics' => [
                'total_duration_ms',
                'eval_count',
            ],
        ]);
    }

    public function test_simple_non_agronomic_question_does_not_attach_rag_chunks(): void
    {
        KnowledgeChunk::create([
            'source' => 'Embrapa',
            'title' => 'Manejo de Solos no Cerrado',
            'topic' => 'Biomas',
            'content' => 'O bioma Cerrado exige calagem e adubação equilibrada com fósforo.',
            'keywords' => ['cerrado', 'calagem', 'fósforo'],
        ]);

        $retrieveAction = app(RetrieveChatRagChunksAction::class);
        $chunks = $retrieveAction->execute('quanto é 2 + 2?', [
            'bioma' => 'Cerrado',
            'ph' => 4.6,
        ]);

        $this->assertEmpty($chunks);
    }

    public function test_stream_chat_message_returns_sse_events_with_rag_and_reply(): void
    {
        KnowledgeChunk::create([
            'source' => 'Embrapa',
            'title' => 'Ciência do Solo e Fertilidade',
            'topic' => 'Ciência do Solo',
            'content' => 'A ciência do solo estuda a gênese, morfologia, física, química e biologia dos solos.',
            'keywords' => ['solo', 'ciencia do solo', 'fertilidade'],
        ]);

        $response = $this->post(route('agro-slm.chat.stream'), [
            'message' => 'O que é ciência do solo?',
        ], [
            'Accept' => 'text/event-stream',
        ]);

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('event: meta', $content);
        $this->assertStringContainsString('event: chunk', $content);
        $this->assertStringContainsString('event: done', $content);
    }
}
