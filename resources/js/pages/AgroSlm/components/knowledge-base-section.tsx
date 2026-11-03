import { BookOpen, Database, Search } from 'lucide-react';
import { useState } from 'react';
import { getSourceBadgeStyle } from './constants';
import type { KnowledgeBaseInfo, RagChunk } from './types';

interface KnowledgeBaseSectionProps {
    knowledgeBase: KnowledgeBaseInfo;
    activeChunkIds?: number[];
}

export function KnowledgeBaseSection({
    knowledgeBase,
    activeChunkIds = [],
}: KnowledgeBaseSectionProps) {
    const [searchQuery, setSearchQuery] = useState<string>('');
    const [selectedSource, setSelectedSource] = useState<
        'ALL' | 'Embrapa' | 'FAO' | 'Wikipedia'
    >('ALL');

    const filteredChunks = knowledgeBase.chunks.filter((chunk: RagChunk) => {
        const matchesSource =
            selectedSource === 'ALL' || chunk.source === selectedSource;
        const q = searchQuery.trim().toLowerCase();
        if (!q) {
            return matchesSource;
        }
        const inTitle = chunk.title.toLowerCase().includes(q);
        const inContent = chunk.content.toLowerCase().includes(q);
        const inTopic = chunk.topic.toLowerCase().includes(q);
        const inKeywords = chunk.keywords.some((kw) =>
            kw.toLowerCase().includes(q),
        );
        return matchesSource && (inTitle || inContent || inTopic || inKeywords);
    });

    return (
        <div className="space-y-4">
            {/* Header & Filtros da Base RAG */}
            <div className="rounded-2xl border border-stone-200 bg-white p-5 shadow-xs dark:border-stone-800 dark:bg-stone-900">
                <div className="flex flex-wrap items-center justify-between gap-4 border-b border-stone-100 pb-4 dark:border-stone-800">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
                            <Database className="h-5 w-5" />
                        </div>
                        <div>
                            <h2 className="text-base font-bold text-stone-900 dark:text-white">
                                Base de Conhecimento Local (RAG —{' '}
                                {knowledgeBase.total} Documentos Indexados)
                            </h2>
                            <p className="text-xs text-stone-500 dark:text-stone-400">
                                Acervo técnico em SQLite consultado pelo motor
                                de busca antes de cada inferência do SLM
                            </p>
                        </div>
                    </div>

                    {/* Filtros por Fonte */}
                    <div className="flex flex-wrap items-center gap-1.5">
                        {(
                            [
                                {
                                    id: 'ALL',
                                    label: `Todos (${knowledgeBase.total})`,
                                },
                                {
                                    id: 'Embrapa',
                                    label: `Embrapa (${knowledgeBase.sources.Embrapa})`,
                                },
                                {
                                    id: 'FAO',
                                    label: `FAO (${knowledgeBase.sources.FAO})`,
                                },
                                {
                                    id: 'Wikipedia',
                                    label: `Wikipedia (${knowledgeBase.sources.Wikipedia})`,
                                },
                            ] as const
                        ).map((tab) => (
                            <button
                                key={tab.id}
                                type="button"
                                onClick={() => setSelectedSource(tab.id)}
                                className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
                                    selectedSource === tab.id
                                        ? 'bg-emerald-600 text-white shadow-2xs'
                                        : 'bg-stone-100 text-stone-600 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300 dark:hover:bg-stone-700'
                                }`}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Barra de Pesquisa */}
                <div className="relative mt-4">
                    <Search className="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-stone-400" />
                    <input
                        type="text"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder="Filtrar fragmentos por título, conceito químico (ex: calagem, FBN, bradrizóbio, latossolo) ou tag..."
                        className="w-full rounded-xl border border-stone-200 bg-stone-50 py-2.5 pr-4 pl-10 text-xs text-stone-900 placeholder:text-stone-400 focus:border-emerald-600 focus:bg-white focus:ring-2 focus:ring-emerald-600/20 focus:outline-none dark:border-stone-700 dark:bg-stone-800 dark:text-stone-100"
                    />
                </div>
            </div>

            {/* Grid de Fragmentos */}
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                {filteredChunks.map((chunk: RagChunk) => {
                    const isUsedInCurrentDiagnosis = activeChunkIds.includes(
                        chunk.id,
                    );
                    return (
                        <div
                            key={chunk.id}
                            className={`flex flex-col justify-between rounded-2xl border p-5 transition ${
                                isUsedInCurrentDiagnosis
                                    ? 'border-emerald-500/50 bg-emerald-50/30 shadow-xs ring-1 ring-emerald-500/20 dark:border-emerald-500/40 dark:bg-emerald-950/15'
                                    : 'border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900'
                            }`}
                        >
                            <div>
                                <div className="mb-2.5 flex flex-wrap items-center justify-between gap-2">
                                    <div className="flex items-center gap-2">
                                        <span
                                            className={`rounded-md border px-2 py-0.5 text-[11px] font-bold ${getSourceBadgeStyle(
                                                chunk.source,
                                            )}`}
                                        >
                                            {chunk.source}
                                        </span>
                                        <span className="rounded bg-stone-100 px-2 py-0.5 font-mono text-[11px] text-stone-600 dark:bg-stone-800 dark:text-stone-400">
                                            {chunk.topic}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {isUsedInCurrentDiagnosis && (
                                            <span className="rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-bold text-white">
                                                Ativo no Diagnóstico Atual
                                            </span>
                                        )}
                                        <span className="font-mono text-xs text-stone-400">
                                            #{chunk.id}
                                        </span>
                                    </div>
                                </div>

                                <h3 className="flex items-start gap-2 text-sm font-bold text-stone-900 dark:text-white">
                                    <BookOpen className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                    <span>{chunk.title}</span>
                                </h3>

                                <p className="mt-2 text-xs leading-relaxed text-stone-600 dark:text-stone-300">
                                    {chunk.content}
                                </p>
                            </div>

                            <div className="mt-4 flex flex-wrap gap-1.5 border-t border-stone-100 pt-3 dark:border-stone-800">
                                {chunk.keywords.map(
                                    (kw: string, kIdx: number) => (
                                        <span
                                            key={kIdx}
                                            className="rounded-md bg-stone-100 px-2 py-0.5 font-mono text-[10px] text-stone-600 dark:bg-stone-800 dark:text-stone-400"
                                        >
                                            #{kw}
                                        </span>
                                    ),
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>

            {filteredChunks.length === 0 && (
                <div className="rounded-2xl border border-stone-200 bg-white p-10 text-center dark:border-stone-800 dark:bg-stone-900">
                    <p className="text-sm font-medium text-stone-600 dark:text-stone-400">
                        Nenhum fragmento encontrado para a busca &ldquo;
                        {searchQuery}&rdquo;.
                    </p>
                </div>
            )}
        </div>
    );
}

