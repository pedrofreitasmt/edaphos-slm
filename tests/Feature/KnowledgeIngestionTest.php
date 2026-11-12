<?php

namespace Tests\Feature;

use App\Actions\Knowledge\ChunkAndIndexDocumentAction;
use App\Models\KnowledgeChunk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KnowledgeIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_chunk_and_index_action_sanitizes_and_splits_document_idempotently(): void
    {
        $action = app(ChunkAndIndexDocumentAction::class);

        $rawText = str_repeat(
            'A calagem em Latossolo do Cerrado neutraliza o alumínio trocável Al3+ e eleva o pH e a saturação por bases V% facilitando a fixação biológica de nitrogênio na soja. ',
            30
        )."\n== Referências ==\nTexto de rodapé que deve ser ignorado.";

        $chunks = $action->execute(
            rawText: $rawText,
            baseTitle: 'Artigo Teste sobre Calagem',
            source: 'Embrapa',
            topic: 'Físico-Química do Solo',
            wordsPerChunk: 150,
            overlap: 30
        );

        $this->assertGreaterThan(1, count($chunks));
        $this->assertDatabaseHas('knowledge_chunks', [
            'title' => 'Artigo Teste sobre Calagem (Parte 1/'.count($chunks).')',
            'source' => 'Embrapa',
        ]);

        // Ensure footer after "== Referências ==" was stripped
        $lastChunk = KnowledgeChunk::query()->latest('id')->first();
        $this->assertNotNull($lastChunk);
        $this->assertStringNotContainsString('Texto de rodapé que deve ser ignorado', $lastChunk->content);

        // Running again must be idempotent (same total count)
        $countBefore = KnowledgeChunk::count();
        $action->execute(
            rawText: $rawText,
            baseTitle: 'Artigo Teste sobre Calagem',
            source: 'Embrapa',
            topic: 'Físico-Química do Solo',
            wordsPerChunk: 150,
            overlap: 30
        );
        $this->assertSame($countBefore, KnowledgeChunk::count());
    }

    public function test_fetch_knowledge_command_harvests_from_wikipedia_api(): void
    {
        Http::fake([
            'pt.wikipedia.org/*' => Http::response([
                'query' => [
                    'pages' => [
                        '12345' => [
                            'pageid' => 12345,
                            'title' => 'Latossolo',
                            'extract' => 'Os Latossolos são solos minerais fortemente intemperizados, profundos e bem drenados, muito comuns no bioma Cerrado brasileiro. Exigem calagem com calcário dolomítico e correção de fósforo para alta produtividade de soja e milho.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('knowledge:fetch --provider=wikipedia --articles="Latossolo" --category="Ciência do Solo"')
            ->assertSuccessful();

        $this->assertDatabaseHas('knowledge_chunks', [
            'source' => 'Wikipedia',
            'title' => 'Wikipedia — Latossolo',
            'topic' => 'Ciência do Solo',
        ]);
    }

    public function test_generic_knowledge_fetch_command_supports_any_domain_and_url(): void
    {
        Http::fake([
            'mecatronica.example.com/*' => Http::response(
                '<html><head><title>Controlador PID Industrial</title></head><body><main><p>Um controlador PID combina ação proporcional, integral e derivativa para controle automático em sistemas mecatrônicos, atuadores pneumáticos e robótica industrial com malha fechada.</p></main></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $this->artisan('knowledge:fetch --url="https://mecatronica.example.com/pid" --source="IEEE" --category="Engenharia Mecatrônica"')
            ->assertSuccessful();

        $this->assertDatabaseHas('knowledge_chunks', [
            'source' => 'IEEE',
            'title' => 'Controlador PID Industrial',
            'topic' => 'Engenharia Mecatrônica',
        ]);

        $chunk = KnowledgeChunk::query()->where('title', 'Controlador PID Industrial')->first();
        $this->assertNotNull($chunk);
        $this->assertContains('controlador', $chunk->keywords);
        $this->assertContains('mecatrônica', $chunk->keywords);
    }

    public function test_knowledge_fetch_dry_run_previews_without_persisting(): void
    {
        Http::fake([
            'pt.wikipedia.org/*' => Http::response([
                'query' => [
                    'pages' => [
                        '999' => [
                            'pageid' => 999,
                            'title' => 'Direito tributário',
                            'extract' => 'O direito tributário brasileiro disciplina a criação, fiscalização e arrecadação de tributos entre a União, Estados, Municípios e o contribuinte.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('knowledge:fetch --provider=wikipedia --articles="Direito tributário" --category="Tributação" --dry-run')
            ->assertSuccessful();

        $this->assertDatabaseCount('knowledge_chunks', 0);
    }

    public function test_knowledge_fetch_rejects_invalid_provider(): void
    {
        $this->artisan('knowledge:fetch --provider=desconhecido')
            ->assertFailed();
    }

    public function test_knowledge_fetch_rejects_all_as_source_label(): void
    {
        $this->artisan('knowledge:fetch --source=all')
            ->assertFailed();
    }
}
