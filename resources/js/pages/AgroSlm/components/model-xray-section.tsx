import {
    Check,
    Code2,
    Copy,
    Database,
    FileCode2,
    Terminal,
    Zap,
} from 'lucide-react';
import { useState } from 'react';
import { getSourceBadgeStyle } from './constants';
import type { KnowledgeBaseInfo, RagChunk } from './types';

interface ModelXraySectionProps {
    xray: App.ViewModels.AgroSlm.SlmXrayViewModel;
    knowledgeBase?: KnowledgeBaseInfo;
    onOpenAllChunks: () => void;
}

export function ModelXraySection({
    xray,
    knowledgeBase,
    onOpenAllChunks,
}: ModelXraySectionProps) {
    const [xrayTab, setXrayTab] = useState<'rag' | 'prompt' | 'schema'>('rag');
    const [copiedPrompt, setCopiedPrompt] = useState<boolean>(false);

    const copyToClipboard = async (text: string) => {
        try {
            await navigator.clipboard.writeText(text);
            setCopiedPrompt(true);
            setTimeout(() => setCopiedPrompt(false), 2000);
        } catch {
            // Ignore clipboard errors
        }
    };

    return (
        <div className="space-y-5">
            {/* Card Principal de Telemetria do SLM */}
            <div className="rounded-2xl border border-stone-200 bg-white p-5 shadow-xs dark:border-stone-800 dark:bg-stone-900">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-stone-100 pb-4 dark:border-stone-800">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-stone-900 text-emerald-400 dark:bg-stone-800">
                            <Terminal className="h-5 w-5" />
                        </div>
                        <div>
                            <h2 className="text-sm font-bold tracking-tight text-stone-900 uppercase dark:text-white">
                                Raio-X Técnico da Inferência (SLM + RAG)
                            </h2>
                            <p className="text-xs text-stone-500 dark:text-stone-400">
                                Auditoria transparente dos fragmentos injetados,
                                engenharia de prompt e telemetria do Ollama
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <span className="rounded-lg border border-stone-200 bg-stone-100 px-2.5 py-1 font-mono text-xs text-stone-600 dark:border-stone-700 dark:bg-stone-800 dark:text-stone-300">
                            {xray.base_url}
                        </span>
                        <span className="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 font-mono text-xs font-bold text-emerald-700 dark:text-emerald-300">
                            {xray.model}
                        </span>
                    </div>
                </div>

                {/* Banner de Status da Execução */}
                <div
                    className={`mb-4 flex flex-wrap items-center justify-between gap-2 rounded-xl border px-4 py-3 text-xs ${
                        xray.ollama_reachable
                            ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-900 dark:text-emerald-200'
                            : 'border-amber-500/30 bg-amber-500/10 text-amber-900 dark:text-amber-200'
                    }`}
                >
                    <div className="flex items-center gap-2 font-semibold">
                        <span
                            className={`h-2.5 w-2.5 rounded-full ${
                                xray.ollama_reachable
                                    ? 'bg-emerald-500'
                                    : 'bg-amber-500'
                            }`}
                        />
                        <span>{xray.status_label}</span>
                    </div>
                    {xray.fallback_reason && (
                        <span className="text-[11px] opacity-85">
                            Nota: {xray.fallback_reason}
                        </span>
                    )}
                </div>

                {/* Grid de 4 Métricas Nativas */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div className="rounded-xl border border-stone-200 bg-stone-50 p-3.5 dark:border-stone-800 dark:bg-stone-800/60">
                        <span className="block text-[11px] font-semibold tracking-wider text-stone-500 uppercase dark:text-stone-400">
                            Latência Total
                        </span>
                        <span className="mt-1 block font-mono text-base font-bold text-stone-900 dark:text-white">
                            {xray.metrics.total_duration_ms.toLocaleString(
                                'pt-BR',
                            )}{' '}
                            <span className="text-xs font-normal text-stone-500">
                                ms
                            </span>
                        </span>
                    </div>

                    <div className="rounded-xl border border-stone-200 bg-stone-50 p-3.5 dark:border-stone-800 dark:bg-stone-800/60">
                        <span className="block text-[11px] font-semibold tracking-wider text-stone-500 uppercase dark:text-stone-400">
                            Tokens de Entrada (Prompt)
                        </span>
                        <span className="mt-1 block font-mono text-base font-bold text-stone-900 dark:text-white">
                            {xray.metrics.prompt_eval_count}{' '}
                            <span className="text-xs font-normal text-stone-500">
                                tokens
                            </span>
                        </span>
                    </div>

                    <div className="rounded-xl border border-stone-200 bg-stone-50 p-3.5 dark:border-stone-800 dark:bg-stone-800/60">
                        <span className="block text-[11px] font-semibold tracking-wider text-stone-500 uppercase dark:text-stone-400">
                            Tokens Gerados (Saída)
                        </span>
                        <span className="mt-1 block font-mono text-base font-bold text-stone-900 dark:text-white">
                            {xray.metrics.eval_count}{' '}
                            <span className="text-xs font-normal text-stone-500">
                                tokens
                            </span>
                        </span>
                    </div>

                    <div className="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3.5">
                        <span className="flex items-center gap-1 text-[11px] font-semibold tracking-wider text-emerald-800 uppercase dark:text-emerald-300">
                            <Zap className="h-3.5 w-3.5" /> Velocidade de
                            Geração
                        </span>
                        <span className="mt-1 block font-mono text-base font-bold text-emerald-800 dark:text-emerald-300">
                            {xray.metrics.tokens_per_second > 0
                                ? `${xray.metrics.tokens_per_second} tok/s`
                                : 'RAG Sync'}
                        </span>
                    </div>
                </div>
            </div>

            {/* Abas Internas do Raio-X */}
            <div className="rounded-2xl border border-stone-200 bg-white p-5 shadow-xs dark:border-stone-800 dark:bg-stone-900">
                <div className="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-stone-100 pb-4 dark:border-stone-800">
                    <div className="inline-flex rounded-xl bg-stone-100 p-1 dark:bg-stone-800">
                        <button
                            type="button"
                            onClick={() => setXrayTab('rag')}
                            className={`flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-semibold transition ${
                                xrayTab === 'rag'
                                    ? 'bg-white text-emerald-700 shadow-2xs dark:bg-stone-900 dark:text-emerald-400'
                                    : 'text-stone-600 hover:text-stone-900 dark:text-stone-400 dark:hover:text-white'
                            }`}
                        >
                            <Database className="h-3.5 w-3.5" />
                            <span>
                                Top {xray.rag_chunks.length} Fragmentos RAG
                                Injetados
                            </span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setXrayTab('prompt')}
                            className={`flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-semibold transition ${
                                xrayTab === 'prompt'
                                    ? 'bg-white text-emerald-700 shadow-2xs dark:bg-stone-900 dark:text-emerald-400'
                                    : 'text-stone-600 hover:text-stone-900 dark:text-stone-400 dark:hover:text-white'
                            }`}
                        >
                            <FileCode2 className="h-3.5 w-3.5" />
                            <span>Prompt Bruto Enviado</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setXrayTab('schema')}
                            className={`flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-semibold transition ${
                                xrayTab === 'schema'
                                    ? 'bg-white text-emerald-700 shadow-2xs dark:bg-stone-900 dark:text-emerald-400'
                                    : 'text-stone-600 hover:text-stone-900 dark:text-stone-400 dark:hover:text-white'
                            }`}
                        >
                            <Code2 className="h-3.5 w-3.5" />
                            <span>JSON Schema (Structured Output)</span>
                        </button>
                    </div>

                    {xrayTab === 'rag' && knowledgeBase && (
                        <button
                            type="button"
                            onClick={onOpenAllChunks}
                            className="text-xs font-semibold text-emerald-700 underline hover:text-emerald-800 dark:text-emerald-400"
                        >
                            Ver acervo completo ({knowledgeBase.total}{' '}
                            documentos) →
                        </button>
                    )}

                    {xrayTab === 'prompt' && (
                        <button
                            type="button"
                            onClick={() =>
                                void copyToClipboard(
                                    `SYSTEM:\n${xray.system_prompt}\n\nUSER PROMPT:\n${xray.raw_prompt}`,
                                )
                            }
                            className="inline-flex items-center gap-1.5 rounded-lg border border-stone-200 bg-stone-100 px-3 py-1.5 text-xs font-medium text-stone-700 hover:bg-stone-200 dark:border-stone-700 dark:bg-stone-800 dark:text-stone-300"
                        >
                            {copiedPrompt ? (
                                <>
                                    <Check className="h-3.5 w-3.5 text-emerald-600" />
                                    <span>Prompt Copiado!</span>
                                </>
                            ) : (
                                <>
                                    <Copy className="h-3.5 w-3.5" />
                                    <span>Copiar Prompt Completo</span>
                                </>
                            )}
                        </button>
                    )}
                </div>

                {/* Aba 1: Fragmentos RAG em Grid 2x2 Amplo */}
                {xrayTab === 'rag' && (
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        {xray.rag_chunks.map((chunk: RagChunk, i: number) => (
                            <div
                                key={chunk.id}
                                className="flex flex-col justify-between rounded-xl border border-stone-200 bg-stone-50/70 p-4 dark:border-stone-800 dark:bg-stone-800/50"
                            >
                                <div>
                                    <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                                        <div className="flex items-center gap-2">
                                            <span className="rounded bg-stone-200 px-2 py-0.5 font-mono text-xs font-bold text-stone-700 dark:bg-stone-700 dark:text-stone-200">
                                                #{i + 1}
                                            </span>
                                            <span
                                                className={`rounded-md border px-2 py-0.5 text-xs font-bold ${getSourceBadgeStyle(
                                                    chunk.source,
                                                )}`}
                                            >
                                                {chunk.source}
                                            </span>
                                            <span className="text-xs font-medium text-stone-500 dark:text-stone-400">
                                                {chunk.topic}
                                            </span>
                                        </div>
                                        {chunk.relevance_score !== null &&
                                            chunk.relevance_score !==
                                                undefined && (
                                                <span className="rounded-md bg-emerald-500/15 px-2 py-0.5 font-mono text-xs font-bold text-emerald-800 dark:text-emerald-300">
                                                    Score:{' '}
                                                    {chunk.relevance_score} pts
                                                </span>
                                            )}
                                    </div>

                                    <h4 className="text-sm font-bold text-stone-900 dark:text-stone-100">
                                        {chunk.title}
                                    </h4>

                                    {chunk.match_reasons &&
                                        chunk.match_reasons.length > 0 && (
                                            <div className="my-2 flex flex-wrap gap-1.5">
                                                {chunk.match_reasons.map(
                                                    (reason, rIdx) => (
                                                        <span
                                                            key={rIdx}
                                                            className="rounded-md bg-emerald-600/10 px-2 py-0.5 text-[11px] font-medium text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300"
                                                        >
                                                            ✓ {reason}
                                                        </span>
                                                    ),
                                                )}
                                            </div>
                                        )}

                                    <p className="mt-2 text-xs leading-relaxed text-stone-600 dark:text-stone-300">
                                        {chunk.content}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {/* Aba 2: Prompt Bruto */}
                {xrayTab === 'prompt' && (
                    <div className="space-y-4 rounded-xl border border-stone-800 bg-stone-950 p-4 font-mono text-xs leading-relaxed text-stone-200">
                        <div>
                            <span className="inline-block rounded bg-emerald-500/20 px-2 py-0.5 font-bold text-emerald-400">
                                [SYSTEM PROMPT — Persona Ph.D. Embrapa/FAO]
                            </span>
                            <pre className="mt-2 whitespace-pre-wrap text-stone-300">
                                {xray.system_prompt}
                            </pre>
                        </div>
                        <hr className="border-stone-800" />
                        <div>
                            <span className="inline-block rounded bg-amber-500/20 px-2 py-0.5 font-bold text-amber-400">
                                [USER PROMPT COM INJEÇÃO DE DOCUMENTOS RAG]
                            </span>
                            <pre className="mt-2 whitespace-pre-wrap text-stone-200">
                                {xray.raw_prompt}
                            </pre>
                        </div>
                    </div>
                )}

                {/* Aba 3: JSON Schema */}
                {xrayTab === 'schema' && (
                    <div className="space-y-3">
                        <p className="text-xs text-stone-500 dark:text-stone-400">
                            Contrato JSON Schema enviado no parâmetro{' '}
                            <code className="rounded bg-stone-200 px-1.5 py-0.5 font-mono dark:bg-stone-800">
                                format
                            </code>{' '}
                            da API do Ollama para garantir resposta estruturada
                            determinística sem alucinação de chaves:
                        </p>
                        <div className="rounded-xl border border-stone-800 bg-stone-950 p-4 font-mono text-xs leading-relaxed text-emerald-300">
                            <pre className="whitespace-pre-wrap">
                                {JSON.stringify(xray.json_schema, null, 2)}
                            </pre>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
