import { Head } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    BookOpen,
    Check,
    CheckCircle2,
    Code2,
    Copy,
    Cpu,
    Database,
    Droplets,
    FileCode2,
    FlaskConical,
    Layers,
    Leaf,
    Microscope,
    Mountain,
    RefreshCw,
    Sparkles,
    Sprout,
    Terminal,
    Trees,
    Zap,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

interface RotacaoItem {
    etapa: string;
    especies: string;
    objetivo_agronomico: string;
}

interface LaudoAgronomico {
    resumo_executivo: string;
    diagnostico_fisico_quimico: {
        analise_ph_nutrientes: string;
        status_materia_organica: string;
        potencial_fbn: string;
    };
    plano_manejo_conservacionista: {
        manejo_bacia_solo: string[];
        arranjo_rotacao_culturas: RotacaoItem[];
    };
    relacao_condicionantes_ambientais: string;
    referencias_utilizadas: string[];
}

interface RagChunk {
    id: number;
    source: 'Embrapa' | 'FAO' | 'Wikipedia' | string;
    title: string;
    topic: string;
    content: string;
    keywords: string[];
    relevance_score?: number;
    match_reasons?: string[];
}

interface SlmMetrics {
    total_duration_ms: number;
    load_duration_ms: number;
    prompt_eval_count: number;
    eval_count: number;
    eval_duration_ms: number;
    tokens_per_second: number;
}

interface SlmXray {
    model: string;
    configured_model: string;
    base_url: string;
    status: 'online' | 'ready' | 'fallback_offline' | string;
    status_label: string;
    ollama_reachable: boolean;
    fallback_reason: string | null;
    metrics: SlmMetrics;
    rag_chunks: RagChunk[];
    raw_prompt: string;
    system_prompt: string;
    json_schema: Record<string, unknown>;
}

interface DiagnosisPayload {
    laudo: LaudoAgronomico;
    xray: SlmXray;
}

interface OllamaHealth {
    online: boolean;
    base_url: string;
    configured_model: string;
    model_installed: boolean;
    available_models: string[];
    message: string;
}

interface KnowledgeBaseInfo {
    total: number;
    sources: {
        Embrapa: number;
        FAO: number;
        Wikipedia: number;
    };
    chunks: RagChunk[];
}

interface SoilFormParams {
    bioma: string;
    relevo: string;
    textura_solo: string;
    ph: number;
    mos: number;
    cultura_atual: string;
    problemas: string[];
}

interface ScenarioPreset {
    id: string;
    badge: string;
    title: string;
    subtitle: string;
    params: SoilFormParams;
}

interface DashboardProps {
    ollamaHealth: OllamaHealth;
    knowledgeBase?: KnowledgeBaseInfo;
    initialDiagnosis: DiagnosisPayload;
}

const BIOMAS = [
    'Cerrado',
    'Mata Atlântica',
    'Caatinga',
    'Pampa',
    'Amazônia',
    'Pantanal',
];

const RELEVOS = [
    'Topo de Chapada',
    'Meia Encosta Suave (3-8%)',
    'Encosta Forte (>15%)',
    'Fundo de Vale / Várzea',
];

const TEXTURAS = [
    'Arenosa (<15% argila)',
    'Média (15-35% argila)',
    'Argilosa (>35% argila)',
];

const PROBLEMAS_DISPONIVEIS = [
    'Compactação',
    'Erosão Laminar',
    'Baixa Fixação de N',
    'Assoreamento de Nascente',
    'Baixa Palhada',
];

const SCENARIO_PRESETS: ScenarioPreset[] = [
    {
        id: 'cerrado-degradado',
        badge: 'Cenário 1',
        title: 'Cerrado Degradado',
        subtitle:
            'Latossolo, pH 4.6, MOS 1.4%, Monocultura de Soja, Erosão Laminar em relevo suave-ondulado, Baixa FBN',
        params: {
            bioma: 'Cerrado',
            relevo: 'Meia Encosta Suave (3-8%)',
            textura_solo: 'Argilosa (>35% argila)',
            ph: 4.6,
            mos: 1.4,
            cultura_atual:
                'Latossolo sob Monocultura de Soja sucessiva sem rotação',
            problemas: [
                'Erosão Laminar',
                'Baixa Fixação de N',
                'Baixa Palhada',
            ],
        },
    },
    {
        id: 'mata-atlantica-encosta',
        badge: 'Cenário 2',
        title: 'Encosta na Mata Atlântica',
        subtitle:
            'Argissolo, Declividade 22%, pH 5.1, MOS 2.3%, Pastagem degradada, Risco de escorregamento e perda de água na bacia',
        params: {
            bioma: 'Mata Atlântica',
            relevo: 'Encosta Forte (>15%)',
            textura_solo: 'Média (15-35% argila)',
            ph: 5.1,
            mos: 2.3,
            cultura_atual:
                'Argissolo (declividade 22%) sob Pastagem degradada de Brachiaria decumbens',
            problemas: [
                'Erosão Laminar',
                'Compactação',
                'Assoreamento de Nascente',
            ],
        },
    },
    {
        id: 'transicao-solo-arenoso',
        badge: 'Cenário 3',
        title: 'Transição Agroecológica / Solo Arenoso',
        subtitle:
            'Neossolo Quartzarênico, pH 5.3, MOS 0.9%, Baixa retenção hídrica, Deficiência severa de Nitrogênio',
        params: {
            bioma: 'Cerrado',
            relevo: 'Topo de Chapada',
            textura_solo: 'Arenosa (<15% argila)',
            ph: 5.3,
            mos: 0.9,
            cultura_atual:
                'Neossolo Quartzarênico em Transição Agroecológica com baixa retenção hídrica e deficiência severa de N',
            problemas: ['Baixa Fixação de N', 'Baixa Palhada'],
        },
    },
];

function getPhClassification(ph: number): {
    label: string;
    color: string;
    detail: string;
} {
    if (ph < 4.5) {
        return {
            label: 'Acidez Extrema',
            color: 'bg-red-500/15 text-red-700 border-red-500/30 dark:text-red-300',
            detail: 'Al³⁺ altamente tóxico e severa fixação de P',
        };
    }
    if (ph < 5.0) {
        return {
            label: 'Acidez Muito Alta',
            color: 'bg-orange-500/15 text-orange-700 border-orange-500/30 dark:text-orange-300',
            detail: 'Inibição de Mo/FBN e presença de Al³⁺ trocável',
        };
    }
    if (ph < 5.5) {
        return {
            label: 'Acidez Média',
            color: 'bg-amber-500/15 text-amber-700 border-amber-500/30 dark:text-amber-300',
            detail: 'Demanda calagem para elevar V% a 60%',
        };
    }
    if (ph <= 6.2) {
        return {
            label: 'Faixa Ideal (5.5–6.2)',
            color: 'bg-emerald-500/15 text-emerald-700 border-emerald-500/30 dark:text-emerald-300',
            detail: 'Máxima disponibilidade nutricional e FBN',
        };
    }

    return {
        label: 'Acidez Fraca / Neutro',
        color: 'bg-sky-500/15 text-sky-700 border-sky-500/30 dark:text-sky-300',
        detail: 'Atenção a micronutrientes catiônicos (Zn, Mn, Fe)',
    };
}

function getMosClassification(mos: number): {
    label: string;
    color: string;
    detail: string;
} {
    if (mos < 1.5) {
        return {
            label: 'Crítica (< 1.5%)',
            color: 'bg-red-500/15 text-red-700 border-red-500/30 dark:text-red-300',
            detail: 'Baixa CTC efetiva e desagregação física',
        };
    }
    if (mos < 2.5) {
        return {
            label: 'Moderada (1.5–2.5%)',
            color: 'bg-amber-500/15 text-amber-700 border-amber-500/30 dark:text-amber-300',
            detail: 'Requer aporte contínuo de palhada (SPD)',
        };
    }

    return {
        label: 'Adequada / Alta (> 2.5%)',
        color: 'bg-emerald-500/15 text-emerald-700 border-emerald-500/30 dark:text-emerald-300',
        detail: 'Boa capacidade tampão, agregação e retenção hídrica',
    };
}

function getSourceBadgeStyle(source: string): string {
    switch (source) {
        case 'Embrapa':
            return 'bg-emerald-600/15 text-emerald-800 border-emerald-600/30 dark:bg-emerald-500/20 dark:text-emerald-300';
        case 'FAO':
            return 'bg-sky-600/15 text-sky-800 border-sky-600/30 dark:bg-sky-500/20 dark:text-sky-300';
        case 'Wikipedia':
            return 'bg-amber-600/15 text-amber-800 border-amber-600/30 dark:bg-amber-500/20 dark:text-amber-300';
        default:
            return 'bg-slate-500/15 text-slate-700 border-slate-500/30 dark:text-slate-300';
    }
}

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
    const [isGenerating, setIsGenerating] = useState<boolean>(false);
    const [checkingHealth, setCheckingHealth] = useState<boolean>(false);
    const [xrayTab, setXrayTab] = useState<'rag' | 'prompt' | 'schema'>('rag');
    const [showAllChunksModal, setShowAllChunksModal] =
        useState<boolean>(false);
    const [copiedPrompt, setCopiedPrompt] = useState<boolean>(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const phInfo = getPhClassification(form.ph);
    const mosInfo = getMosClassification(form.mos);

    const toggleProblema = (problema: string) => {
        setActivePresetId('custom');
        setForm((prev) => {
            const exists = prev.problemas.includes(problema);
            return {
                ...prev,
                problemas: exists
                    ? prev.problemas.filter((p) => p !== problema)
                    : [...prev.problemas, problema],
            };
        });
    };

    const runDiagnosis = async (paramsToRun: SoilFormParams) => {
        setIsGenerating(true);
        setErrorMessage(null);

        try {
            const csrfToken =
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content') ?? '';

            const response = await fetch('/agro-slm/diagnose', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(paramsToRun),
            });

            if (!response.ok) {
                throw new Error(
                    `Falha na requisição (${response.status}): verifique os parâmetros informados.`,
                );
            }

            const data = (await response.json()) as DiagnosisPayload;
            setDiagnosis(data);
        } catch (err) {
            setErrorMessage(
                err instanceof Error
                    ? err.message
                    : 'Erro inesperado ao processar o diagnóstico agronômico.',
            );
        } finally {
            setIsGenerating(false);
        }
    };

    const handleSelectPreset = (preset: ScenarioPreset) => {
        setActivePresetId(preset.id);
        setForm(preset.params);
        void runDiagnosis(preset.params);
    };

    const handleSubmitForm = (e: React.FormEvent) => {
        e.preventDefault();
        void runDiagnosis(form);
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

    const copyToClipboard = async (text: string) => {
        try {
            await navigator.clipboard.writeText(text);
            setCopiedPrompt(true);
            setTimeout(() => setCopiedPrompt(false), 2000);
        } catch {
            // Ignore clipboard errors in restricted contexts
        }
    };

    const { laudo, xray } = diagnosis;

    return (
        <div className="min-h-screen bg-stone-50 text-stone-900 dark:bg-stone-950 dark:text-stone-100">
            <Head title="AgroSolo AI — SADA & SLM Local" />

            {/* Top Scientific Header */}
            <header className="sticky top-0 z-30 border-b border-emerald-900/15 bg-white/95 backdrop-blur-md dark:border-emerald-500/20 dark:bg-stone-900/95">
                <div className="mx-auto flex max-w-[1720px] flex-wrap items-center justify-between gap-4 px-4 py-3 lg:px-6">
                    <div className="flex items-center gap-3.5">
                        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-600 to-teal-800 text-white shadow-sm shadow-emerald-950/20">
                            <Sprout className="h-6 w-6" />
                        </div>
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-lg font-bold tracking-tight text-stone-900 dark:text-white">
                                    AgroSolo AI
                                </h1>
                                <span className="rounded-md bg-emerald-500/15 px-2 py-0.5 text-[11px] font-semibold tracking-wide text-emerald-800 uppercase dark:text-emerald-300">
                                    SADA Acadêmico • 100% Local
                                </span>
                            </div>
                            <p className="text-xs text-stone-600 dark:text-stone-400">
                                Small Language Model (SLM) & RAG Edafoclimático
                                aplicado à Ciência do Solo, Manejo de Bacias e
                                Agroecologia
                            </p>
                        </div>
                    </div>

                    {/* Right Header Telemetry & Base Status */}
                    <div className="flex flex-wrap items-center gap-2.5">
                        {knowledgeBase && (
                            <button
                                type="button"
                                onClick={() => setShowAllChunksModal(true)}
                                className="inline-flex items-center gap-2 rounded-lg border border-stone-200 bg-stone-100/80 px-3 py-1.5 text-xs font-medium text-stone-700 transition hover:border-emerald-500/40 hover:bg-emerald-50/50 dark:border-stone-800 dark:bg-stone-800/80 dark:text-stone-300 dark:hover:bg-stone-800"
                            >
                                <Database className="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                                <span>
                                    Base RAG Local:{' '}
                                    <strong>
                                        {knowledgeBase.total} fragmentos
                                    </strong>{' '}
                                    (Embrapa / FAO / Wiki)
                                </span>
                            </button>
                        )}

                        <div
                            className={`inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-medium ${
                                ollamaHealth.online
                                    ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300'
                                    : 'border-amber-500/30 bg-amber-500/10 text-amber-800 dark:text-amber-300'
                            }`}
                        >
                            <span
                                className={`h-2 w-2 rounded-full ${
                                    ollamaHealth.online
                                        ? 'animate-pulse bg-emerald-500'
                                        : 'bg-amber-500'
                                }`}
                            />
                            <span>
                                {ollamaHealth.online
                                    ? `Ollama Online (${ollamaHealth.configured_model})`
                                    : 'Ollama Offline (Contingência RAG Ativa)'}
                            </span>
                            <button
                                type="button"
                                onClick={() => void refreshOllamaStatus()}
                                title="Verificar conexão com o Ollama (localhost:11434)"
                                className="ml-1 rounded p-0.5 hover:bg-black/10 dark:hover:bg-white/10"
                            >
                                <RefreshCw
                                    className={`h-3.5 w-3.5 ${checkingHealth ? 'animate-spin' : ''}`}
                                />
                            </button>
                        </div>
                    </div>
                </div>

                {/* AREA A: Barra Superior de Cenários Pré-Carregados (1-Click Presets para Apresentação) */}
                <div className="border-t border-stone-200/80 bg-stone-100/70 dark:border-stone-800 dark:bg-stone-900/60">
                    <div className="mx-auto max-w-[1720px] px-4 py-2.5 lg:px-6">
                        <div className="mb-2 flex items-center justify-between">
                            <div className="flex items-center gap-2 text-xs font-semibold tracking-wider text-stone-600 uppercase dark:text-stone-400">
                                <Sparkles className="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                                <span>
                                    Cenários Edafoclimáticos Pré-Carregados
                                    (1-Click Presets para Apresentação)
                                </span>
                            </div>
                            <span className="hidden text-[11px] text-stone-500 sm:inline dark:text-stone-400">
                                Clique em um cenário para preencher os
                                condicionantes e disparar a análise
                            </span>
                        </div>

                        <div className="grid grid-cols-1 gap-2.5 md:grid-cols-3">
                            {SCENARIO_PRESETS.map((preset) => {
                                const isSelected = activePresetId === preset.id;
                                return (
                                    <button
                                        key={preset.id}
                                        type="button"
                                        disabled={isGenerating}
                                        onClick={() =>
                                            handleSelectPreset(preset)
                                        }
                                        className={`group flex flex-col justify-between rounded-xl border p-3 text-left transition-all ${
                                            isSelected
                                                ? 'border-emerald-600 bg-emerald-50/90 shadow-sm ring-1 ring-emerald-600/30 dark:border-emerald-500 dark:bg-emerald-950/40'
                                                : 'border-stone-200/90 bg-white hover:border-emerald-500/50 hover:bg-stone-50 dark:border-stone-800 dark:bg-stone-900 dark:hover:border-emerald-500/40'
                                        }`}
                                    >
                                        <div>
                                            <div className="mb-1 flex items-center justify-between gap-2">
                                                <span
                                                    className={`inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-bold ${
                                                        isSelected
                                                            ? 'bg-emerald-600 text-white'
                                                            : 'bg-stone-200 text-stone-700 dark:bg-stone-800 dark:text-stone-300'
                                                    }`}
                                                >
                                                    {preset.badge}
                                                </span>
                                                <span className="text-[11px] font-medium text-emerald-700 dark:text-emerald-400">
                                                    pH {preset.params.ph} • MOS{' '}
                                                    {preset.params.mos}%
                                                </span>
                                            </div>
                                            <h2 className="text-sm font-bold text-stone-900 group-hover:text-emerald-700 dark:text-stone-100 dark:group-hover:text-emerald-400">
                                                {preset.title}
                                            </h2>
                                            <p className="mt-0.5 line-clamp-2 text-xs leading-relaxed text-stone-600 dark:text-stone-400">
                                                {preset.subtitle}
                                            </p>
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                </div>
            </header>

            {/* Main 3-Column Scientific Dashboard Grid */}
            <main className="mx-auto max-w-[1720px] px-4 py-5 lg:px-6">
                {errorMessage && (
                    <div className="mb-4 flex items-center gap-3 rounded-xl border border-red-500/40 bg-red-500/10 p-3.5 text-sm text-red-800 dark:text-red-200">
                        <AlertTriangle className="h-5 w-5 shrink-0 text-red-600" />
                        <span>{errorMessage}</span>
                    </div>
                )}

                <div className="grid grid-cols-1 gap-6 xl:grid-cols-12">
                    {/* AREA B: Painel Esquerdo — Formulário de Condicionantes Ambientais e Edáficos (3 cols) */}
                    <section className="xl:col-span-3">
                        <div className="rounded-2xl border border-stone-200 bg-white p-5 shadow-xs dark:border-stone-800 dark:bg-stone-900">
                            <div className="mb-4 flex items-center gap-2.5 border-b border-stone-100 pb-3 dark:border-stone-800">
                                <FlaskConical className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                                <div>
                                    <h2 className="text-sm font-bold tracking-tight text-stone-900 uppercase dark:text-white">
                                        Condicionantes Edáficos
                                    </h2>
                                    <p className="text-xs text-stone-500 dark:text-stone-400">
                                        Parâmetros de entrada para o RAG e SLM
                                    </p>
                                </div>
                            </div>

                            <form
                                onSubmit={handleSubmitForm}
                                className="space-y-4"
                            >
                                {/* Bioma */}
                                <div>
                                    <label
                                        htmlFor="bioma-select"
                                        className="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-stone-700 dark:text-stone-300"
                                    >
                                        <Trees className="h-3.5 w-3.5 text-emerald-600" />
                                        Bioma Brasileiro
                                    </label>
                                    <select
                                        id="bioma-select"
                                        value={form.bioma}
                                        onChange={(e) => {
                                            setActivePresetId('custom');
                                            setForm({
                                                ...form,
                                                bioma: e.target.value,
                                            });
                                        }}
                                        className="w-full rounded-lg border border-stone-300 bg-stone-50 px-3 py-2 text-sm text-stone-900 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none dark:border-stone-700 dark:bg-stone-800 dark:text-stone-100"
                                    >
                                        {BIOMAS.map((bioma) => (
                                            <option key={bioma} value={bioma}>
                                                {bioma}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                {/* Posição na Bacia / Relevo */}
                                <div>
                                    <label
                                        htmlFor="relevo-select"
                                        className="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-stone-700 dark:text-stone-300"
                                    >
                                        <Mountain className="h-3.5 w-3.5 text-emerald-600" />
                                        Posição na Bacia / Relevo
                                    </label>
                                    <select
                                        id="relevo-select"
                                        value={form.relevo}
                                        onChange={(e) => {
                                            setActivePresetId('custom');
                                            setForm({
                                                ...form,
                                                relevo: e.target.value,
                                            });
                                        }}
                                        className="w-full rounded-lg border border-stone-300 bg-stone-50 px-3 py-2 text-sm text-stone-900 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none dark:border-stone-700 dark:bg-stone-800 dark:text-stone-100"
                                    >
                                        {RELEVOS.map((relevo) => (
                                            <option key={relevo} value={relevo}>
                                                {relevo}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                {/* Textura do Solo */}
                                <div>
                                    <label
                                        htmlFor="textura-select"
                                        className="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-stone-700 dark:text-stone-300"
                                    >
                                        <Layers className="h-3.5 w-3.5 text-emerald-600" />
                                        Textura do Solo
                                    </label>
                                    <select
                                        id="textura-select"
                                        value={form.textura_solo}
                                        onChange={(e) => {
                                            setActivePresetId('custom');
                                            setForm({
                                                ...form,
                                                textura_solo: e.target.value,
                                            });
                                        }}
                                        className="w-full rounded-lg border border-stone-300 bg-stone-50 px-3 py-2 text-sm text-stone-900 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none dark:border-stone-700 dark:bg-stone-800 dark:text-stone-100"
                                    >
                                        {TEXTURAS.map((tex) => (
                                            <option key={tex} value={tex}>
                                                {tex}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                {/* pH em CaCl2 */}
                                <div className="rounded-xl border border-stone-200/80 bg-stone-50/70 p-3 dark:border-stone-800 dark:bg-stone-800/40">
                                    <div className="mb-1.5 flex items-center justify-between">
                                        <label
                                            htmlFor="ph-input"
                                            className="text-xs font-semibold text-stone-700 dark:text-stone-300"
                                        >
                                            pH em CaCl₂ (3.5 a 7.5)
                                        </label>
                                        <input
                                            id="ph-input"
                                            type="number"
                                            min={3.5}
                                            max={7.5}
                                            step={0.1}
                                            value={form.ph}
                                            onChange={(e) => {
                                                const val = parseFloat(
                                                    e.target.value,
                                                );
                                                if (!Number.isNaN(val)) {
                                                    setActivePresetId('custom');
                                                    setForm({
                                                        ...form,
                                                        ph: Math.min(
                                                            7.5,
                                                            Math.max(3.5, val),
                                                        ),
                                                    });
                                                }
                                            }}
                                            className="w-20 rounded-md border border-stone-300 bg-white px-2 py-1 text-right font-mono text-sm font-bold text-stone-900 dark:border-stone-700 dark:bg-stone-900 dark:text-white"
                                        />
                                    </div>
                                    <input
                                        type="range"
                                        min={3.5}
                                        max={7.5}
                                        step={0.1}
                                        value={form.ph}
                                        onChange={(e) => {
                                            setActivePresetId('custom');
                                            setForm({
                                                ...form,
                                                ph: parseFloat(e.target.value),
                                            });
                                        }}
                                        className="w-full accent-emerald-600"
                                    />
                                    <div className="mt-2 flex flex-col gap-1">
                                        <span
                                            className={`inline-flex w-fit items-center rounded-md border px-2 py-0.5 text-[11px] font-semibold ${phInfo.color}`}
                                        >
                                            {phInfo.label}
                                        </span>
                                        <span className="text-[11px] text-stone-500 dark:text-stone-400">
                                            {phInfo.detail}
                                        </span>
                                    </div>
                                </div>

                                {/* Matéria Orgânica - MOS % */}
                                <div className="rounded-xl border border-stone-200/80 bg-stone-50/70 p-3 dark:border-stone-800 dark:bg-stone-800/40">
                                    <div className="mb-1.5 flex items-center justify-between">
                                        <label
                                            htmlFor="mos-input"
                                            className="text-xs font-semibold text-stone-700 dark:text-stone-300"
                                        >
                                            Matéria Orgânica — MOS %
                                        </label>
                                        <div className="flex items-center gap-1">
                                            <input
                                                id="mos-input"
                                                type="number"
                                                min={0.5}
                                                max={6.0}
                                                step={0.1}
                                                value={form.mos}
                                                onChange={(e) => {
                                                    const val = parseFloat(
                                                        e.target.value,
                                                    );
                                                    if (!Number.isNaN(val)) {
                                                        setActivePresetId(
                                                            'custom',
                                                        );
                                                        setForm({
                                                            ...form,
                                                            mos: Math.min(
                                                                6.0,
                                                                Math.max(
                                                                    0.5,
                                                                    val,
                                                                ),
                                                            ),
                                                        });
                                                    }
                                                }}
                                                className="w-20 rounded-md border border-stone-300 bg-white px-2 py-1 text-right font-mono text-sm font-bold text-stone-900 dark:border-stone-700 dark:bg-stone-900 dark:text-white"
                                            />
                                            <span className="text-xs font-semibold text-stone-500">
                                                %
                                            </span>
                                        </div>
                                    </div>
                                    <input
                                        type="range"
                                        min={0.5}
                                        max={6.0}
                                        step={0.1}
                                        value={form.mos}
                                        onChange={(e) => {
                                            setActivePresetId('custom');
                                            setForm({
                                                ...form,
                                                mos: parseFloat(e.target.value),
                                            });
                                        }}
                                        className="w-full accent-emerald-600"
                                    />
                                    <div className="mt-2 flex flex-col gap-1">
                                        <span
                                            className={`inline-flex w-fit items-center rounded-md border px-2 py-0.5 text-[11px] font-semibold ${mosInfo.color}`}
                                        >
                                            {mosInfo.label}
                                        </span>
                                        <span className="text-[11px] text-stone-500 dark:text-stone-400">
                                            {mosInfo.detail}
                                        </span>
                                    </div>
                                </div>

                                {/* Histórico de Uso / Cultura Atual */}
                                <div>
                                    <label
                                        htmlFor="cultura-input"
                                        className="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-stone-700 dark:text-stone-300"
                                    >
                                        <Leaf className="h-3.5 w-3.5 text-emerald-600" />
                                        Histórico de Uso / Cultura Atual
                                    </label>
                                    <textarea
                                        id="cultura-input"
                                        rows={2}
                                        value={form.cultura_atual}
                                        onChange={(e) => {
                                            setActivePresetId('custom');
                                            setForm({
                                                ...form,
                                                cultura_atual: e.target.value,
                                            });
                                        }}
                                        placeholder="Ex: Monocultura de soja, pastagem degradada..."
                                        className="w-full rounded-lg border border-stone-300 bg-stone-50 px-3 py-2 text-xs leading-relaxed text-stone-900 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none dark:border-stone-700 dark:bg-stone-800 dark:text-stone-100"
                                    />
                                </div>

                                {/* Problemas Ambientais Observados */}
                                <div>
                                    <span className="mb-2 block text-xs font-semibold text-stone-700 dark:text-stone-300">
                                        Problemas Ambientais Observados
                                    </span>
                                    <div className="flex flex-wrap gap-1.5">
                                        {PROBLEMAS_DISPONIVEIS.map((prob) => {
                                            const checked =
                                                form.problemas.includes(prob);
                                            return (
                                                <button
                                                    key={prob}
                                                    type="button"
                                                    onClick={() =>
                                                        toggleProblema(prob)
                                                    }
                                                    className={`inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-medium transition ${
                                                        checked
                                                            ? 'border-emerald-600 bg-emerald-600 text-white shadow-2xs'
                                                            : 'border-stone-300 bg-stone-50 text-stone-700 hover:border-emerald-500/50 dark:border-stone-700 dark:bg-stone-800 dark:text-stone-300'
                                                    }`}
                                                >
                                                    <span
                                                        className={`flex h-3.5 w-3.5 items-center justify-center rounded-xs border ${
                                                            checked
                                                                ? 'border-white bg-white/20'
                                                                : 'border-stone-400'
                                                        }`}
                                                    >
                                                        {checked && (
                                                            <Check className="h-2.5 w-2.5" />
                                                        )}
                                                    </span>
                                                    {prob}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>

                                <Button
                                    type="submit"
                                    disabled={isGenerating}
                                    className="w-full bg-emerald-700 py-5 text-xs font-bold tracking-wide text-white uppercase shadow-sm hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500"
                                >
                                    {isGenerating ? (
                                        <span className="flex items-center gap-2">
                                            <RefreshCw className="h-4 w-4 animate-spin" />
                                            Consultando RAG + SLM Local...
                                        </span>
                                    ) : (
                                        <span className="flex items-center gap-2">
                                            <Cpu className="h-4 w-4" />
                                            Gerar Laudo no SLM Local
                                        </span>
                                    )}
                                </Button>
                            </form>
                        </div>
                    </section>

                    {/* AREA C (Parte 1): Painel Central — Laudo Agronômico Conservacionista (5 cols) */}
                    <section className="space-y-5 xl:col-span-5">
                        {/* Resumo Executivo Card */}
                        <div className="relative overflow-hidden rounded-2xl border border-emerald-500/30 bg-gradient-to-br from-emerald-950 via-emerald-900 to-teal-950 p-5 text-white shadow-sm">
                            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <div className="flex items-center gap-2">
                                    <Microscope className="h-5 w-5 text-emerald-400" />
                                    <h2 className="text-sm font-bold tracking-wider text-emerald-200 uppercase">
                                        Laudo Agronômico Conservacionista
                                    </h2>
                                </div>
                                <div className="flex flex-wrap gap-1.5">
                                    <Badge className="border-emerald-400/30 bg-emerald-500/20 text-emerald-100">
                                        {form.bioma}
                                    </Badge>
                                    <Badge className="border-emerald-400/30 bg-emerald-500/20 text-emerald-100">
                                        pH {form.ph}
                                    </Badge>
                                    <Badge className="border-emerald-400/30 bg-emerald-500/20 text-emerald-100">
                                        MOS {form.mos}%
                                    </Badge>
                                </div>
                            </div>
                            <p className="text-sm leading-relaxed text-emerald-50/95">
                                {laudo.resumo_executivo}
                            </p>
                        </div>

                        {/* Diagnóstico Físico-Químico e Biológico */}
                        <div className="rounded-2xl border border-stone-200 bg-white p-5 shadow-xs dark:border-stone-800 dark:bg-stone-900">
                            <div className="mb-4 flex items-center gap-2 border-b border-stone-100 pb-3 dark:border-stone-800">
                                <Activity className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                                <h3 className="text-sm font-bold tracking-tight text-stone-900 uppercase dark:text-white">
                                    Diagnóstico Físico-Químico e Simbiótico
                                </h3>
                            </div>

                            <div className="space-y-3.5">
                                <div className="rounded-xl border border-amber-500/25 bg-amber-50/50 p-3.5 dark:border-amber-500/20 dark:bg-amber-950/20">
                                    <div className="mb-1 flex items-center gap-2 text-xs font-bold text-amber-900 uppercase dark:text-amber-300">
                                        <FlaskConical className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                        1. Análise de pH, Acidez (Al³⁺) e
                                        Disponibilidade de Nutrientes
                                    </div>
                                    <p className="text-xs leading-relaxed text-stone-700 dark:text-stone-300">
                                        {
                                            laudo.diagnostico_fisico_quimico
                                                .analise_ph_nutrientes
                                        }
                                    </p>
                                </div>

                                <div className="rounded-xl border border-emerald-500/25 bg-emerald-50/50 p-3.5 dark:border-emerald-500/20 dark:bg-emerald-950/20">
                                    <div className="mb-1 flex items-center gap-2 text-xs font-bold text-emerald-900 uppercase dark:text-emerald-300">
                                        <Layers className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                        2. Status da Matéria Orgânica (MOS), CTC
                                        e Estrutura Física
                                    </div>
                                    <p className="text-xs leading-relaxed text-stone-700 dark:text-stone-300">
                                        {
                                            laudo.diagnostico_fisico_quimico
                                                .status_materia_organica
                                        }
                                    </p>
                                </div>

                                <div className="rounded-xl border border-sky-500/25 bg-sky-50/50 p-3.5 dark:border-sky-500/20 dark:bg-sky-950/20">
                                    <div className="mb-1 flex items-center gap-2 text-xs font-bold text-sky-900 uppercase dark:text-sky-300">
                                        <Sprout className="h-4 w-4 text-sky-600 dark:text-sky-400" />
                                        3. Potencial de Fixação Biológica de
                                        Nitrogênio (FBN — Bradyrhizobium)
                                    </div>
                                    <p className="text-xs leading-relaxed text-stone-700 dark:text-stone-300">
                                        {
                                            laudo.diagnostico_fisico_quimico
                                                .potencial_fbn
                                        }
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Plano de Manejo Conservacionista: Microbacia e Rotação */}
                        <div className="rounded-2xl border border-stone-200 bg-white p-5 shadow-xs dark:border-stone-800 dark:bg-stone-900">
                            <div className="mb-4 flex items-center gap-2 border-b border-stone-100 pb-3 dark:border-stone-800">
                                <Droplets className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                                <h3 className="text-sm font-bold tracking-tight text-stone-900 uppercase dark:text-white">
                                    Plano de Manejo Conservacionista (Bacia &
                                    Rotação)
                                </h3>
                            </div>

                            {/* Práticas de Manejo de Bacia e Solo */}
                            <div className="mb-5">
                                <h4 className="mb-2.5 text-xs font-bold tracking-wider text-stone-600 uppercase dark:text-stone-400">
                                    Engenharia Conservacionista na Microbacia
                                    Hidrográfica
                                </h4>
                                <ul className="space-y-2">
                                    {laudo.plano_manejo_conservacionista.manejo_bacia_solo.map(
                                        (pratica, index) => (
                                            <li
                                                key={index}
                                                className="flex items-start gap-2.5 rounded-xl border border-stone-200/80 bg-stone-50/80 p-3 text-xs leading-relaxed text-stone-800 dark:border-stone-800 dark:bg-stone-800/50 dark:text-stone-200"
                                            >
                                                <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                                <span>{pratica}</span>
                                            </li>
                                        ),
                                    )}
                                </ul>
                            </div>

                            {/* Cronograma de Rotação de Culturas e Adubação Verde */}
                            <div>
                                <h4 className="mb-2.5 text-xs font-bold tracking-wider text-stone-600 uppercase dark:text-stone-400">
                                    Arranjo Cronológico de Rotação de Culturas &
                                    Adubação Verde
                                </h4>
                                <div className="space-y-3">
                                    {laudo.plano_manejo_conservacionista.arranjo_rotacao_culturas.map(
                                        (item, index) => (
                                            <div
                                                key={index}
                                                className="rounded-xl border border-emerald-600/20 bg-gradient-to-r from-emerald-50/60 to-stone-50 p-3.5 dark:border-emerald-500/20 dark:from-emerald-950/25 dark:to-stone-900"
                                            >
                                                <div className="mb-1 flex items-center justify-between gap-2">
                                                    <span className="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-800 dark:text-emerald-300">
                                                        <span className="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                                                            {index + 1}
                                                        </span>
                                                        {item.etapa}
                                                    </span>
                                                </div>
                                                <div className="mt-1.5 pl-6">
                                                    <p className="text-xs font-semibold text-stone-900 dark:text-stone-100">
                                                        <span className="text-stone-500 dark:text-stone-400">
                                                            Espécies:{' '}
                                                        </span>
                                                        {item.especies}
                                                    </p>
                                                    <p className="mt-1 text-xs leading-relaxed text-stone-600 dark:text-stone-300">
                                                        <span className="font-semibold text-stone-700 dark:text-stone-300">
                                                            Objetivo Agronômico:{' '}
                                                        </span>
                                                        {
                                                            item.objetivo_agronomico
                                                        }
                                                    </p>
                                                </div>
                                            </div>
                                        ),
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Síntese Didática: Relação Condicionantes Ambientais & Referências */}
                        <div className="rounded-2xl border border-stone-200 bg-white p-5 shadow-xs dark:border-stone-800 dark:bg-stone-900">
                            <div className="mb-3 flex items-center gap-2">
                                <BookOpen className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                                <h3 className="text-sm font-bold tracking-tight text-stone-900 uppercase dark:text-white">
                                    Fundamentação Edafoclimática (Por que estas
                                    escolhas?)
                                </h3>
                            </div>
                            <p className="text-xs leading-relaxed text-stone-700 dark:text-stone-300">
                                {laudo.relacao_condicionantes_ambientais}
                            </p>

                            <div className="mt-4 border-t border-stone-100 pt-3 dark:border-stone-800">
                                <span className="mb-2 block text-[11px] font-bold tracking-wider text-stone-500 uppercase dark:text-stone-400">
                                    Fontes Documentais Citadas (RAG Local):
                                </span>
                                <div className="flex flex-wrap gap-1.5">
                                    {laudo.referencias_utilizadas.map(
                                        (ref, idx) => (
                                            <span
                                                key={idx}
                                                className="inline-flex items-center gap-1 rounded-md border border-stone-200 bg-stone-100 px-2.5 py-1 text-[11px] font-medium text-stone-700 dark:border-stone-700 dark:bg-stone-800 dark:text-stone-300"
                                            >
                                                <BookOpen className="h-3 w-3 text-emerald-600" />
                                                {ref}
                                            </span>
                                        ),
                                    )}
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* AREA C (Parte 2): Painel Direito — "Raio-X do SLM" (Modo Apresentação Didática) (4 cols) */}
                    <section className="xl:col-span-4">
                        <div className="sticky top-36 rounded-2xl border border-stone-200 bg-white p-5 shadow-xs dark:border-stone-800 dark:bg-stone-900">
                            {/* Header Raio-X */}
                            <div className="mb-4 flex items-center justify-between gap-2 border-b border-stone-100 pb-3 dark:border-stone-800">
                                <div className="flex items-center gap-2.5">
                                    <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-stone-900 text-emerald-400 dark:bg-stone-800">
                                        <Terminal className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h2 className="text-sm font-bold tracking-tight text-stone-900 uppercase dark:text-white">
                                            Raio-X do Modelo (Didático)
                                        </h2>
                                        <p className="text-[11px] text-stone-500 dark:text-stone-400">
                                            Inspeção de Telemetria, RAG e
                                            Engenharia de Prompt
                                        </p>
                                    </div>
                                </div>
                                <span className="rounded-md border border-emerald-500/30 bg-emerald-500/10 px-2 py-1 font-mono text-xs font-bold text-emerald-700 dark:text-emerald-300">
                                    {xray.model}
                                </span>
                            </div>

                            {/* Status da Execução */}
                            <div
                                className={`mb-4 rounded-xl border p-3 text-xs ${
                                    xray.ollama_reachable
                                        ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-900 dark:text-emerald-200'
                                        : 'border-amber-500/30 bg-amber-500/10 text-amber-900 dark:text-amber-200'
                                }`}
                            >
                                <div className="flex items-center justify-between font-bold">
                                    <span>Status da Execução:</span>
                                    <span className="font-mono text-[11px]">
                                        {xray.base_url}
                                    </span>
                                </div>
                                <p className="mt-1 font-medium">
                                    {xray.status_label}
                                </p>
                                {xray.fallback_reason && (
                                    <p className="mt-1 text-[11px] opacity-85">
                                        Nota: {xray.fallback_reason}
                                    </p>
                                )}
                            </div>

                            {/* Métricas Nativas do Ollama */}
                            <div className="mb-4 grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                                <div className="rounded-xl border border-stone-200 bg-stone-50 p-2.5 dark:border-stone-800 dark:bg-stone-800/60">
                                    <span className="block text-[10px] font-semibold tracking-wider text-stone-500 uppercase dark:text-stone-400">
                                        Latência Total
                                    </span>
                                    <span className="mt-0.5 block font-mono text-sm font-bold text-stone-900 dark:text-white">
                                        {xray.metrics.total_duration_ms.toLocaleString(
                                            'pt-BR',
                                        )}{' '}
                                        ms
                                    </span>
                                </div>

                                <div className="rounded-xl border border-stone-200 bg-stone-50 p-2.5 dark:border-stone-800 dark:bg-stone-800/60">
                                    <span className="block text-[10px] font-semibold tracking-wider text-stone-500 uppercase dark:text-stone-400">
                                        Tokens Entrada
                                    </span>
                                    <span className="mt-0.5 block font-mono text-sm font-bold text-stone-900 dark:text-white">
                                        {xray.metrics.prompt_eval_count}{' '}
                                        <span className="text-[10px] font-normal text-stone-500">
                                            tok
                                        </span>
                                    </span>
                                </div>

                                <div className="rounded-xl border border-stone-200 bg-stone-50 p-2.5 dark:border-stone-800 dark:bg-stone-800/60">
                                    <span className="block text-[10px] font-semibold tracking-wider text-stone-500 uppercase dark:text-stone-400">
                                        Tokens Gerados
                                    </span>
                                    <span className="mt-0.5 block font-mono text-sm font-bold text-stone-900 dark:text-white">
                                        {xray.metrics.eval_count}{' '}
                                        <span className="text-[10px] font-normal text-stone-500">
                                            tok
                                        </span>
                                    </span>
                                </div>

                                <div className="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-2.5">
                                    <span className="flex items-center gap-1 text-[10px] font-semibold tracking-wider text-emerald-800 uppercase dark:text-emerald-300">
                                        <Zap className="h-3 w-3" /> Velocidade
                                    </span>
                                    <span className="mt-0.5 block font-mono text-sm font-bold text-emerald-800 dark:text-emerald-300">
                                        {xray.metrics.tokens_per_second > 0
                                            ? `${xray.metrics.tokens_per_second} tok/s`
                                            : 'RAG Sync'}
                                    </span>
                                </div>
                            </div>

                            {/* Seletor de Abas do Raio-X */}
                            <div className="mb-3 grid grid-cols-3 gap-1 rounded-xl bg-stone-100 p-1 dark:bg-stone-800">
                                <button
                                    type="button"
                                    onClick={() => setXrayTab('rag')}
                                    className={`flex items-center justify-center gap-1.5 rounded-lg py-2 text-xs font-semibold transition ${
                                        xrayTab === 'rag'
                                            ? 'bg-white text-emerald-700 shadow-2xs dark:bg-stone-900 dark:text-emerald-400'
                                            : 'text-stone-600 hover:text-stone-900 dark:text-stone-400 dark:hover:text-white'
                                    }`}
                                >
                                    <Database className="h-3.5 w-3.5" />
                                    RAG ({xray.rag_chunks.length})
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setXrayTab('prompt')}
                                    className={`flex items-center justify-center gap-1.5 rounded-lg py-2 text-xs font-semibold transition ${
                                        xrayTab === 'prompt'
                                            ? 'bg-white text-emerald-700 shadow-2xs dark:bg-stone-900 dark:text-emerald-400'
                                            : 'text-stone-600 hover:text-stone-900 dark:text-stone-400 dark:hover:text-white'
                                    }`}
                                >
                                    <FileCode2 className="h-3.5 w-3.5" />
                                    Prompt Bruto
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setXrayTab('schema')}
                                    className={`flex items-center justify-center gap-1.5 rounded-lg py-2 text-xs font-semibold transition ${
                                        xrayTab === 'schema'
                                            ? 'bg-white text-emerald-700 shadow-2xs dark:bg-stone-900 dark:text-emerald-400'
                                            : 'text-stone-600 hover:text-stone-900 dark:text-stone-400 dark:hover:text-white'
                                    }`}
                                >
                                    <Code2 className="h-3.5 w-3.5" />
                                    JSON Schema
                                </button>
                            </div>

                            {/* Conteúdo da Aba Selecionada */}
                            {xrayTab === 'rag' && (
                                <div className="max-h-[540px] space-y-3 overflow-y-auto pr-1">
                                    <div className="flex items-center justify-between text-[11px] text-stone-500 dark:text-stone-400">
                                        <span>
                                            Top {xray.rag_chunks.length}{' '}
                                            fragmentos injetados no prompt:
                                        </span>
                                        {knowledgeBase && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setShowAllChunksModal(true)
                                                }
                                                className="font-semibold text-emerald-700 underline hover:text-emerald-800 dark:text-emerald-400"
                                            >
                                                Ver todos ({knowledgeBase.total}
                                                )
                                            </button>
                                        )}
                                    </div>

                                    {xray.rag_chunks.map((chunk, i) => (
                                        <div
                                            key={chunk.id}
                                            className="rounded-xl border border-stone-200 bg-stone-50/70 p-3 dark:border-stone-800 dark:bg-stone-800/50"
                                        >
                                            <div className="mb-1.5 flex flex-wrap items-center justify-between gap-1.5">
                                                <div className="flex items-center gap-1.5">
                                                    <span className="rounded bg-stone-200 px-1.5 py-0.5 font-mono text-[10px] font-bold text-stone-700 dark:bg-stone-700 dark:text-stone-200">
                                                        #{i + 1}
                                                    </span>
                                                    <span
                                                        className={`rounded-md border px-2 py-0.5 text-[10px] font-bold ${getSourceBadgeStyle(
                                                            chunk.source,
                                                        )}`}
                                                    >
                                                        {chunk.source}
                                                    </span>
                                                    <span className="text-[11px] font-medium text-stone-500 dark:text-stone-400">
                                                        {chunk.topic}
                                                    </span>
                                                </div>
                                                {chunk.relevance_score !==
                                                    undefined && (
                                                    <span className="rounded bg-emerald-500/15 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-emerald-800 dark:text-emerald-300">
                                                        Score:{' '}
                                                        {chunk.relevance_score}
                                                    </span>
                                                )}
                                            </div>

                                            <h4 className="text-xs font-bold text-stone-900 dark:text-stone-100">
                                                {chunk.title}
                                            </h4>

                                            {chunk.match_reasons &&
                                                chunk.match_reasons.length >
                                                    0 && (
                                                    <div className="my-1.5 flex flex-wrap gap-1">
                                                        {chunk.match_reasons.map(
                                                            (reason, rIdx) => (
                                                                <span
                                                                    key={rIdx}
                                                                    className="rounded bg-emerald-600/10 px-1.5 py-0.5 text-[10px] font-medium text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300"
                                                                >
                                                                    ✓ {reason}
                                                                </span>
                                                            ),
                                                        )}
                                                    </div>
                                                )}

                                            <p className="mt-1.5 text-[11px] leading-relaxed text-stone-600 dark:text-stone-300">
                                                {chunk.content}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            )}

                            {xrayTab === 'prompt' && (
                                <div className="space-y-3">
                                    <div className="flex items-center justify-between">
                                        <span className="text-[11px] font-semibold text-stone-500 dark:text-stone-400">
                                            Engenharia de Prompt + Contexto RAG
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                void copyToClipboard(
                                                    `SYSTEM:\n${xray.system_prompt}\n\nUSER PROMPT:\n${xray.raw_prompt}`,
                                                )
                                            }
                                            className="inline-flex items-center gap-1 rounded-md border border-stone-200 bg-stone-100 px-2 py-1 text-[11px] font-medium text-stone-700 hover:bg-stone-200 dark:border-stone-700 dark:bg-stone-800 dark:text-stone-300"
                                        >
                                            {copiedPrompt ? (
                                                <>
                                                    <Check className="h-3 w-3 text-emerald-600" />
                                                    Copiado!
                                                </>
                                            ) : (
                                                <>
                                                    <Copy className="h-3 w-3" />
                                                    Copiar Prompt
                                                </>
                                            )}
                                        </button>
                                    </div>

                                    <div className="max-h-[500px] space-y-2.5 overflow-y-auto rounded-xl border border-stone-800 bg-stone-950 p-3 font-mono text-[11px] leading-relaxed text-stone-200">
                                        <div>
                                            <span className="font-bold text-emerald-400">
                                                [SYSTEM PROMPT — Ph.D.
                                                Embrapa/FAO]
                                            </span>
                                            <pre className="mt-1 whitespace-pre-wrap text-stone-300">
                                                {xray.system_prompt}
                                            </pre>
                                        </div>
                                        <hr className="border-stone-800" />
                                        <div>
                                            <span className="font-bold text-amber-400">
                                                [USER PROMPT COM INJEÇÃO RAG]
                                            </span>
                                            <pre className="mt-1 whitespace-pre-wrap text-stone-200">
                                                {xray.raw_prompt}
                                            </pre>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {xrayTab === 'schema' && (
                                <div className="space-y-2">
                                    <p className="text-[11px] text-stone-500 dark:text-stone-400">
                                        Contrato JSON Schema injetado no
                                        parâmetro{' '}
                                        <code className="rounded bg-stone-200 px-1 py-0.5 font-mono dark:bg-stone-800">
                                            format
                                        </code>{' '}
                                        da API do Ollama para garantir saída
                                        estruturada determinística:
                                    </p>
                                    <div className="max-h-[500px] overflow-y-auto rounded-xl border border-stone-800 bg-stone-950 p-3 font-mono text-[11px] leading-relaxed text-emerald-300">
                                        <pre className="whitespace-pre-wrap">
                                            {JSON.stringify(
                                                xray.json_schema,
                                                null,
                                                2,
                                            )}
                                        </pre>
                                    </div>
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </main>

            {/* Modal para Explorar Todos os 10 Fragmentos da Base de Conhecimento Local */}
            {showAllChunksModal && knowledgeBase && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs">
                    <div className="flex max-h-[88vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-2xl dark:border-stone-800 dark:bg-stone-900">
                        <div className="flex items-center justify-between border-b border-stone-200 px-6 py-4 dark:border-stone-800">
                            <div className="flex items-center gap-2.5">
                                <Database className="h-5 w-5 text-emerald-600" />
                                <div>
                                    <h3 className="text-base font-bold text-stone-900 dark:text-white">
                                        Base de Conhecimento Local (RAG —{' '}
                                        {knowledgeBase.total} Fragmentos
                                        Científicos)
                                    </h3>
                                    <p className="text-xs text-stone-500 dark:text-stone-400">
                                        Embrapa ({knowledgeBase.sources.Embrapa}
                                        ) • FAO ({knowledgeBase.sources.FAO}) •
                                        Wikipedia (
                                        {knowledgeBase.sources.Wikipedia})
                                    </p>
                                </div>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setShowAllChunksModal(false)}
                            >
                                Fechar
                            </Button>
                        </div>

                        <div className="space-y-3 overflow-y-auto p-6">
                            {knowledgeBase.chunks.map((chunk) => (
                                <div
                                    key={chunk.id}
                                    className="rounded-xl border border-stone-200 bg-stone-50 p-4 dark:border-stone-800 dark:bg-stone-800/50"
                                >
                                    <div className="mb-1.5 flex flex-wrap items-center justify-between gap-2">
                                        <div className="flex items-center gap-2">
                                            <span
                                                className={`rounded-md border px-2 py-0.5 text-xs font-bold ${getSourceBadgeStyle(
                                                    chunk.source,
                                                )}`}
                                            >
                                                {chunk.source}
                                            </span>
                                            <span className="text-xs font-semibold text-stone-500 dark:text-stone-400">
                                                Tópico: {chunk.topic}
                                            </span>
                                        </div>
                                        <span className="font-mono text-xs text-stone-400">
                                            Chunk #{chunk.id}
                                        </span>
                                    </div>
                                    <h4 className="text-sm font-bold text-stone-900 dark:text-white">
                                        {chunk.title}
                                    </h4>
                                    <p className="mt-1.5 text-xs leading-relaxed text-stone-700 dark:text-stone-300">
                                        {chunk.content}
                                    </p>
                                    <div className="mt-2.5 flex flex-wrap gap-1">
                                        {chunk.keywords.map((kw, kIdx) => (
                                            <span
                                                key={kIdx}
                                                className="rounded bg-stone-200/80 px-1.5 py-0.5 font-mono text-[10px] text-stone-600 dark:bg-stone-700 dark:text-stone-300"
                                            >
                                                #{kw}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
