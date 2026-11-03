import { Database, RefreshCw, Sprout } from 'lucide-react';
import type { KnowledgeBaseInfo, OllamaHealth } from './types';

interface DashboardHeaderProps {
    ollamaHealth: OllamaHealth;
    knowledgeBase?: KnowledgeBaseInfo;
    checkingHealth: boolean;
    onRefreshHealth: () => void;
    onOpenKnowledgeBase: () => void;
}

export function DashboardHeader({
    ollamaHealth,
    knowledgeBase,
    checkingHealth,
    onRefreshHealth,
    onOpenKnowledgeBase,
}: DashboardHeaderProps) {
    return (
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
                            onClick={onOpenKnowledgeBase}
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
                            onClick={onRefreshHealth}
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
        </header>
    );
}

