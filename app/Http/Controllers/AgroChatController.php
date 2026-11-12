<?php

namespace App\Http\Controllers;

use App\Actions\Agro\ProcessAgroChatMessageAction;
use App\Http\Requests\AgroChatMessageRequest;
use App\Services\Agro\AgroSlmService;
use App\Services\Ai\OllamaClient;
use App\ViewModels\AgroSlm\AgroChatMessageResponseViewModel;
use App\ViewModels\AgroSlm\AgroChatViewModel;
use App\ViewModels\AgroSlm\OllamaHealthViewModel;
use Illuminate\Http\JsonResponse;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgroChatController extends Controller
{
    public function __construct(
        protected AgroSlmService $service,
        protected OllamaClient $ollamaClient,
        protected ProcessAgroChatMessageAction $processChatMessage
    ) {}

    /**
     * Render the dedicated AgroSolo AI Conversational Chat view.
     */
    public function index(): Response
    {
        $ollamaHealth = OllamaHealthViewModel::fromArray($this->ollamaClient->checkHealth());
        $knowledgeBase = $this->service->getKnowledgeBaseSummary();

        $suggestedQuestions = [
            'Como o pH 4.6 e o alumínio tóxico afetam a fixação de nitrogênio na soja?',
            'Qual a diferença entre a relação C/N de leguminosas (Crotalária) e gramíneas (Braquiária)?',
            'Como fazer o terraceamento em nível versus em gradiente para conter a erosão laminar?',
            'Por que solos arenosos (Neossolos Quartzarênicos) exigem parcelamento da calagem e adubação?',
            'Como a gessagem subsuperficial atua contra veranicos no Cerrado?',
        ];

        $availablePresets = [
            [
                'id' => 'cerrado-degradado',
                'badge' => 'Cenário 1',
                'title' => 'Cerrado Degradado (Latossolo)',
                'subtitle' => 'pH 4.6 • MOS 1.4% • Monocultura de Soja • Baixa Palhada',
                'params' => [
                    'bioma' => 'Cerrado',
                    'relevo' => 'Meia Encosta Suave (3-8%)',
                    'textura_solo' => 'Argilosa (>35% argila)',
                    'ph' => 4.6,
                    'mos' => 1.4,
                    'cultura_atual' => 'Latossolo sob Monocultura de Soja sucessiva',
                    'problemas' => ['Erosão Laminar', 'Baixa Fixação de N', 'Baixa Palhada'],
                ],
            ],
            [
                'id' => 'mata-atlantica-encosta',
                'badge' => 'Cenário 2',
                'title' => 'Encosta na Mata Atlântica (Argissolo)',
                'subtitle' => 'Declividade 22% • pH 5.1 • Pastagem degradada • Risco de erosão',
                'params' => [
                    'bioma' => 'Mata Atlântica',
                    'relevo' => 'Encosta Forte (>15%)',
                    'textura_solo' => 'Média (15-35% argila)',
                    'ph' => 5.1,
                    'mos' => 2.3,
                    'cultura_atual' => 'Argissolo sob Pastagem degradada de Brachiaria decumbens',
                    'problemas' => ['Erosão Laminar', 'Compactação', 'Assoreamento de Nascente'],
                ],
            ],
            [
                'id' => 'transicao-solo-arenoso',
                'badge' => 'Cenário 3',
                'title' => 'Solo Arenoso (Neossolo Quartzarênico)',
                'subtitle' => 'pH 5.3 • MOS 0.9% • Baixa retenção hídrica • Transição Agroecológica',
                'params' => [
                    'bioma' => 'Cerrado',
                    'relevo' => 'Topo de Chapada',
                    'textura_solo' => 'Arenosa (<15% argila)',
                    'ph' => 5.3,
                    'mos' => 0.9,
                    'cultura_atual' => 'Neossolo Quartzarênico em Transição Agroecológica',
                    'problemas' => ['Baixa Fixação de N', 'Baixa Palhada'],
                ],
            ],
        ];

        $viewModel = new AgroChatViewModel(
            ollamaHealth: $ollamaHealth,
            knowledgeBase: $knowledgeBase,
            suggestedQuestions: $suggestedQuestions,
            availablePresets: $availablePresets
        );

        return inertia()->render('AgroSlm/Chat', $viewModel->toArray());
    }

    /**
     * Process a chat message with local RAG grounding and return assistant reply.
     */
    public function message(AgroChatMessageRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->processChatMessage->execute(
            message: (string) $validated['message'],
            history: $validated['history'] ?? [],
            edaphicContext: $validated['edaphic_context'] ?? null
        );

        $responseVm = AgroChatMessageResponseViewModel::fromArray($result);

        return response()->json($responseVm->jsonSerialize());
    }

    /**
     * Stream a chat message response token-by-token via Server-Sent Events (SSE).
     */
    public function stream(AgroChatMessageRequest $request): StreamedResponse
    {
        $validated = $request->validated();

        return response()->stream(function () use ($validated): void {
            $sendEvent = static function (string $event, array $payload): void {
                echo "event: {$event}\n";
                echo 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE)."\n\n";

                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            $result = $this->processChatMessage->streamExecute(
                message: (string) $validated['message'],
                history: $validated['history'] ?? [],
                edaphicContext: $validated['edaphic_context'] ?? null,
                onMeta: static function (array $ragChunks) use ($sendEvent): void {
                    $sendEvent('meta', ['rag_chunks' => $ragChunks]);
                },
                onChunk: static function (string $delta) use ($sendEvent): void {
                    $sendEvent('chunk', ['delta' => $delta]);
                }
            );

            $sendEvent('done', [
                'model' => $result['model'],
                'status' => $result['status'],
                'metrics' => $result['metrics'],
            ]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
