<?php

namespace App\Http\Controllers;

use App\Http\Requests\AgroDiagnosisRequest;
use App\Services\AgroSlmService;
use App\Services\Ollama\OllamaClient;
use App\ViewModels\AgroSlm\AgroDashboardViewModel;
use App\ViewModels\AgroSlm\DiagnosisPayloadViewModel;
use App\ViewModels\AgroSlm\OllamaHealthViewModel;
use Illuminate\Http\JsonResponse;
use Inertia\Response;

class AgroDiagnosisController extends Controller
{
    public function __construct(
        private AgroSlmService $service,
        private OllamaClient $ollamaClient,
    ) {}

    /**
     * Display the AgroSolo AI scientific dashboard using typed ViewModels.
     */
    public function index(): Response
    {
        $dashboardVm = $this->service->getDiagnosisData();

        return inertia()->render('AgroSlm/Dashboard', $dashboardVm->toArray());
    }

    /**
     * Execute RAG retrieval + Local SLM inference via Ollama.
     */
    public function diagnose(AgroDiagnosisRequest $request): JsonResponse|Response
    {
        $result = $this->service->generateDiagnosis($request->validated());

        $diagnosisVm = DiagnosisPayloadViewModel::fromArray($result);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($diagnosisVm->jsonSerialize());
        }

        $dashboardVm = new AgroDashboardViewModel(
            ollamaHealth: OllamaHealthViewModel::fromArray($this->ollamaClient->checkHealth()),
            knowledgeBase: $this->service->getKnowledgeBaseSummary(),
            initialDiagnosis: $diagnosisVm
        );

        return inertia()->render('AgroSlm/Dashboard', $dashboardVm->toArray())
            ->with('success', 'Diagnóstico realizado com sucesso!');
    }

    /**
     * Check live Ollama connection status and available local models.
     */
    public function status(): JsonResponse
    {
        $healthVm = OllamaHealthViewModel::fromArray($this->ollamaClient->checkHealth());

        return response()->json($healthVm->jsonSerialize());
    }
}
