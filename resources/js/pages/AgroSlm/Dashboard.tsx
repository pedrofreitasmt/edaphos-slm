import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Database,
    Microscope,
    Terminal,
    Zap,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import {
    AgronomicReportSection,
    DashboardHeader,
    DiagnosisLoadingState,
    KnowledgeBaseSection,
    ModelXraySection,
    SCENARIO_PRESETS,
    SoilConditionsForm,
} from './components';
import type {
    DashboardProps,
    DiagnosisPayload,
    OllamaHealth,
    ScenarioPreset,
    SoilFormParams,
    WorkspaceTab,
} from './components';

export default function Dashboard({
    ollamaHealth: initialOllamaHealth,
    knowledgeBase,
    initialDiagnosis,
}: DashboardProps) {
    const [activePresetId, setActivePresetId] = useState<string>(
        SCENARIO_PRESETS[0].id,
    );
    const [form, setForm] = useState<SoilFormParams>(
        SCENARIO_PRESETS[0].params,
    );
    const [diagnosis, setDiagnosis] =
        useState<DiagnosisPayload>(initialDiagnosis);
    const [ollamaHealth, setOllamaHealth] =
        useState<OllamaHealth>(initialOllamaHealth);
    const [activeTab, setActiveTab] = useState<WorkspaceTab>('report');
    const [isGenerating, setIsGenerating] = useState<boolean>(false);
    const [checkingHealth, setCheckingHealth] = useState<boolean>(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const runDiagnosis = (paramsToRun: SoilFormParams) => {
        setIsGenerating(true);
        setErrorMessage(null);

        router.post(
            '/agro-slm/diagnose',
            { ...paramsToRun },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: (page) => {
                    const pageProps = page.props as unknown as {
                        initialDiagnosis?: DiagnosisPayload;
                        ollamaHealth?: OllamaHealth;
                    };
                    if (pageProps?.initialDiagnosis) {
                        setDiagnosis(pageProps.initialDiagnosis);
                    }
                    if (pageProps?.ollamaHealth) {
                        setOllamaHealth(pageProps.ollamaHealth);
                    }
                    setActiveTab('report');
                },
                onError: (errors) => {
                    const firstError = Object.values(errors)[0] as
                        | string
                        | undefined;
                    setErrorMessage(
                        firstError || 'Erro ao processar diagnóstico.',
                    );
                },
                onFinish: () => {
                    setIsGenerating(false);
                },
            },
        );
    };

    const handleSelectPreset = (preset: ScenarioPreset) => {
        setActivePresetId(preset.id);
        setForm(preset.params);
        void runDiagnosis(preset.params);
    };

    const handleSubmitForm = (e: FormEvent) => {
        e.preventDefault();
        void runDiagnosis(form);
    };

    const handleFormChange = (newForm: SoilFormParams) => {
        setActivePresetId('custom');
        setForm(newForm);
    };

    const refreshOllamaStatus = async () => {
        setCheckingHealth(true);
        try {
            const response = await fetch('/agro-slm/status', {
                headers: { Accept: 'application/json' },
            });
            if (response.ok) {
                const health = (await response.json()) as OllamaHealth;
                setOllamaHealth(health);
            }
        } finally {
            setCheckingHealth(false);
        }
    };

    const { laudo, xray } = diagnosis;
    const activeChunkIds = xray.rag_chunks.map((c) => c.id);

    return (
        <div className="min-h-screen bg-stone-100/70 text-stone-900 dark:bg-stone-950 dark:text-stone-100">
            <Head title="AgroSolo AI — SADA & SLM Local" />

            {/* Header Principal */}
            <DashboardHeader
                ollamaHealth={ollamaHealth}
                knowledgeBase={knowledgeBase}
                checkingHealth={checkingHealth}
                onRefreshHealth={() => void refreshOllamaStatus()}
                onOpenKnowledgeBase={() => setActiveTab('knowledge')}
            />

            {/* Container Principal: Layout 2 Colunas Limpo */}
            <main className="mx-auto max-w-[1600px] px-4 py-6 lg:px-6">
                {errorMessage && (
                    <div className="mb-5 flex items-center gap-3 rounded-xl border border-red-500/40 bg-red-500/10 p-3.5 text-sm text-red-800 dark:text-red-200">
                        <AlertTriangle className="h-5 w-5 shrink-0 text-red-600" />
                        <span>{errorMessage}</span>
                    </div>
                )}

                <div className="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
                    {/* Coluna Esquerda: Cenários Rápidos + Formulário Edáfico (4 colunas) */}
                    <aside className="lg:col-span-4 xl:col-span-4">
                        <SoilConditionsForm
                            form={form}
                            activePresetId={activePresetId}
                            isGenerating={isGenerating}
                            onSelectPreset={handleSelectPreset}
                            onChangeForm={handleFormChange}
                            onSubmit={handleSubmitForm}
                        />
                    </aside>

                    {/* Coluna Direita: Área Principal com Abas (8 colunas) */}
                    <div className="space-y-4 lg:col-span-8 xl:col-span-8">
                        {/* Barra de Navegação por Abas do Workspace */}
                        <div className="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-stone-200 bg-white p-2 shadow-2xs dark:border-stone-800 dark:bg-stone-900">
                            <div className="flex flex-wrap items-center gap-1.5">
                                <button
                                    type="button"
                                    onClick={() => setActiveTab('report')}
                                    className={`flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold transition ${
                                        activeTab === 'report'
                                            ? 'bg-emerald-600 text-white shadow-xs'
                                            : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900 dark:text-stone-400 dark:hover:bg-stone-800 dark:hover:text-white'
                                    }`}
                                >
                                    <Microscope className="h-4 w-4" />
                                    <span>Laudo Agronômico</span>
                                </button>

                                <button
                                    type="button"
                                    onClick={() => setActiveTab('xray')}
                                    className={`flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold transition ${
                                        activeTab === 'xray'
                                            ? 'bg-emerald-600 text-white shadow-xs'
                                            : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900 dark:text-stone-400 dark:hover:bg-stone-800 dark:hover:text-white'
                                    }`}
                                >
                                    <Terminal className="h-4 w-4" />
                                    <span>Raio-X do SLM</span>
                                    <span
                                        className={`rounded-md px-1.5 py-0.5 font-mono text-[10px] ${
                                            activeTab === 'xray'
                                                ? 'bg-white/20 text-white'
                                                : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                        }`}
                                    >
                                        {xray.metrics.tokens_per_second > 0
                                            ? `${xray.metrics.tokens_per_second} tok/s`
                                            : `${xray.rag_chunks.length} RAG`}
                                    </span>
                                </button>

                                {knowledgeBase && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setActiveTab('knowledge')
                                        }
                                        className={`flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold transition ${
                                            activeTab === 'knowledge'
                                                ? 'bg-emerald-600 text-white shadow-xs'
                                                : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900 dark:text-stone-400 dark:hover:bg-stone-800 dark:hover:text-white'
                                        }`}
                                    >
                                        <Database className="h-4 w-4" />
                                        <span>Base RAG</span>
                                        <span
                                            className={`rounded-md px-1.5 py-0.5 font-mono text-[10px] ${
                                                activeTab === 'knowledge'
                                                    ? 'bg-white/20 text-white'
                                                    : 'bg-stone-100 text-stone-600 dark:bg-stone-800 dark:text-stone-400'
                                            }`}
                                        >
                                            {knowledgeBase.total}
                                        </span>
                                    </button>
                                )}
                            </div>

                            {/* Indicador Rápido de Performance no canto direito da barra de abas */}
                            <div className="hidden items-center gap-2 pr-2 text-xs text-stone-500 sm:flex dark:text-stone-400">
                                <Zap className="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                                <span className="font-mono text-[11px]">
                                    {xray.metrics.total_duration_ms > 0
                                        ? `Inferência: ${(xray.metrics.total_duration_ms / 1000).toFixed(1)}s (${xray.metrics.eval_count} tok)`
                                        : 'Pronto para diagnosticar'}
                                </span>
                            </div>
                        </div>

                        {/* Conteúdo Principal ou Tela de Progresso da IA */}
                        {isGenerating ? (
                            <DiagnosisLoadingState
                                form={form}
                                modelName={ollamaHealth.configured_model}
                            />
                        ) : (
                            <>
                                {activeTab === 'report' && (
                                    <AgronomicReportSection
                                        laudo={laudo}
                                        form={form}
                                        onOpenXray={() => setActiveTab('xray')}
                                        onOpenKnowledgeBase={() =>
                                            setActiveTab('knowledge')
                                        }
                                    />
                                )}

                                {activeTab === 'xray' && (
                                    <ModelXraySection
                                        xray={xray}
                                        knowledgeBase={knowledgeBase}
                                        onOpenAllChunks={() =>
                                            setActiveTab('knowledge')
                                        }
                                    />
                                )}

                                {activeTab === 'knowledge' && knowledgeBase && (
                                    <KnowledgeBaseSection
                                        knowledgeBase={knowledgeBase}
                                        activeChunkIds={activeChunkIds}
                                    />
                                )}
                            </>
                        )}
                    </div>
                </div>
            </main>
        </div>
    );
}
