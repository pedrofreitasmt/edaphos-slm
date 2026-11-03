<?php

namespace Tests\Feature;

use App\Models\KnowledgeChunk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AgroDiagnosisTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_access_agro_dashboard_home(): void
    {
        KnowledgeChunk::create([
            'source' => 'Embrapa',
            'title' => 'Manejo de Solos Arenosos',
            'topic' => 'textura_arenosa',
            'content' => 'Solos arenosos necessitam de cobertura permanente para evitar perdas hídricas.',
            'keywords' => ['Plantio direto', 'Palhada densa'],
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('AgroSlm/Dashboard')
            ->has('ollamaHealth')
            ->has('knowledgeBase')
            ->has('initialDiagnosis')
        );
    }

    public function test_get_on_agro_slm_diagnose_redirects_to_dashboard(): void
    {
        $response = $this->get('/agro-slm/diagnose');

        $response->assertRedirect(route('agro-slm.dashboard'));
    }

    public function test_post_on_agro_slm_diagnose_generates_diagnosis_and_flashes_success(): void
    {
        $payload = [
            'bioma' => 'Cerrado',
            'relevo' => 'Meia Encosta Suave (3-8%)',
            'textura_solo' => 'Argilosa (>35% argila)',
            'ph' => 4.8,
            'mos' => 1.5,
            'cultura_atual' => 'Monocultura de Soja',
            'problemas' => ['Erosão Laminar', 'Baixa Palhada'],
        ];

        $response = $this->post('/agro-slm/diagnose', $payload);

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('AgroSlm/Dashboard')
            ->where('success', 'Diagnóstico realizado com sucesso!')
            ->has('ollamaHealth')
            ->has('knowledgeBase')
            ->has('initialDiagnosis')
            ->has('initialDiagnosis.laudo')
            ->has('initialDiagnosis.xray')
        );
    }

    public function test_status_endpoint_returns_json(): void
    {
        $response = $this->get(route('agro-slm.status'));

        $response->assertOk();
        $response->assertJsonStructure([
            'online',
            'configured_model',
            'base_url',
            'available_models',
            'model_installed',
            'message',
        ]);
    }
}
