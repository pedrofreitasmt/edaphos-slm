import { CheckCircle2, Cpu, Database, FileCode2, Loader2, Sparkles } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { SoilFormParams } from './types';

interface DiagnosisLoadingStateProps {
    form: SoilFormParams;
    modelName: string;
}

export function DiagnosisLoadingState({
    form,
    modelName,
}: DiagnosisLoadingStateProps) {
    const [elapsedTenths, setElapsedTenths] = useState<number>(0);

    useEffect(() => {
        const interval = setInterval(() => {
            setElapsedTenths((prev) => prev + 1);
        }, 100);

        return () => clearInterval(interval);
    }, []);

    const elapsedSeconds = (elapsedTenths / 10).toFixed(1);
    const step1Done = elapsedTenths >= 6;
    const step2Done = elapsedTenths >= 14;

    return (
        <div className="flex min-h-[540px] flex-col items-center justify-center rounded-2xl border border-emerald-500/30 bg-white p-8 shadow-xs dark:border-emerald-500/20 dark:bg-stone-900">
            <div className="relative mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
                <Cpu className="h-8 w-8 animate-pulse" />
                <span className="absolute -top-1 -right-1 flex h-4 w-4">
                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                    <span className="relative inline-flex h-4 w-4 rounded-full bg-emerald-500" />
                </span>
            </div>

            <div className="mb-2 inline-flex items-center gap-2 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 font-mono text-xs font-bold text-emerald-800 dark:text-emerald-300">
                <Sparkles className="h-3.5 w-3.5" />
                <span>Processando no SLM Local ({modelName})</span>
                <span className="ml-1 rounded bg-emerald-600 px-1.5 py-0.5 text-[11px] text-white">
                    {elapsedSeconds}s
                </span>
            </div>

            <h3 className="mt-2 text-center text-lg font-bold text-stone-900 dark:text-white">
                Elaborando Laudo Agronômico Conservacionista
            </h3>
            <p className="mt-1 max-w-md text-center text-xs text-stone-500 dark:text-stone-400">
                Cruzando os parâmetros de <strong>{form.bioma}</strong> (pH{' '}
                {form.ph}, MOS {form.mos}%, {form.textura_solo}) com os manuais
                da Embrapa e FAO.
            </p>

            {/* Pipeline de Etapas */}
            <div className="mt-8 w-full max-w-lg space-y-3">
                {/* Passo 1: RAG */}
                <div
                    className={`flex items-center gap-3.5 rounded-xl border p-3.5 transition-all ${
                        step1Done
                            ? 'border-emerald-500/30 bg-emerald-50/60 dark:border-emerald-500/20 dark:bg-emerald-950/20'
                            : 'border-stone-200 bg-stone-50 dark:border-stone-800 dark:bg-stone-800/60'
                    }`}
                >
                    {step1Done ? (
                        <CheckCircle2 className="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                    ) : (
                        <Loader2 className="h-5 w-5 shrink-0 animate-spin text-emerald-600" />
                    )}
                    <div className="flex-1 text-left">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-bold text-stone-900 dark:text-white">
                                1. Recuperação Semântica RAG (SQLite Local)
                            </span>
                            <Database className="h-3.5 w-3.5 text-stone-400" />
                        </div>
                        <p className="text-[11px] text-stone-500 dark:text-stone-400">
                            Selecionando os 4 fragmentos científicos de maior
                            score edafoclimático...
                        </p>
                    </div>
                </div>

                {/* Passo 2: Prompt + Schema */}
                <div
                    className={`flex items-center gap-3.5 rounded-xl border p-3.5 transition-all ${
                        step2Done
                            ? 'border-emerald-500/30 bg-emerald-50/60 dark:border-emerald-500/20 dark:bg-emerald-950/20'
                            : step1Done
                              ? 'border-emerald-500/40 bg-white shadow-xs dark:bg-stone-800'
                              : 'border-stone-200/60 bg-stone-50/50 opacity-60 dark:border-stone-800 dark:bg-stone-800/30'
                    }`}
                >
                    {step2Done ? (
                        <CheckCircle2 className="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                    ) : step1Done ? (
                        <Loader2 className="h-5 w-5 shrink-0 animate-spin text-emerald-600" />
                    ) : (
                        <div className="h-5 w-5 shrink-0 rounded-full border-2 border-stone-300 dark:border-stone-700" />
                    )}
                    <div className="flex-1 text-left">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-bold text-stone-900 dark:text-white">
                                2. Construção do Prompt & JSON Schema
                            </span>
                            <FileCode2 className="h-3.5 w-3.5 text-stone-400" />
                        </div>
                        <p className="text-[11px] text-stone-500 dark:text-stone-400">
                            Injetando referências técnicas e contrato de saída
                            estruturada...
                        </p>
                    </div>
                </div>

                {/* Passo 3: Inferência Neural */}
                <div
                    className={`flex items-center gap-3.5 rounded-xl border p-3.5 transition-all ${
                        step2Done
                            ? 'border-emerald-600/50 bg-emerald-50/80 shadow-xs ring-1 ring-emerald-500/20 dark:border-emerald-500/40 dark:bg-emerald-950/30'
                            : 'border-stone-200/60 bg-stone-50/50 opacity-60 dark:border-stone-800 dark:bg-stone-800/30'
                    }`}
                >
                    {step2Done ? (
                        <Loader2 className="h-5 w-5 shrink-0 animate-spin text-emerald-600 dark:text-emerald-400" />
                    ) : (
                        <div className="h-5 w-5 shrink-0 rounded-full border-2 border-stone-300 dark:border-stone-700" />
                    )}
                    <div className="flex-1 text-left">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-bold text-stone-900 dark:text-white">
                                3. Inferência Neural no Ollama (3B Params)
                            </span>
                            <Cpu className="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <p className="text-[11px] text-stone-600 dark:text-stone-300">
                            Sintetizando diagnóstico físico-químico, manejo de
                            bacia e rotação de culturas...
                        </p>
                    </div>
                </div>
            </div>

            <p className="mt-6 text-[11px] text-stone-400 dark:text-stone-500">
                Execução 100% privada no hardware local • Sem envio de dados
                para nuvem externa
            </p>
        </div>
    );
}

