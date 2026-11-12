import { Head, Link } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    BookOpen,
    Bot,
    ChevronDown,
    Cpu,
    Database,
    Eraser,
    Layers,
    MessageSquare,
    RefreshCw,
    Send,
    Sparkles,
    Sprout,
    User,
    Zap,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent, KeyboardEvent } from 'react';
import { getSourceBadgeStyle } from './components/constants';
import { MarkdownMessage } from './components/markdown-message';

interface ChatMessage {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    timestamp: string;
    rag_sources?: Array<{
        id: number;
        source: string;
        title: string;
        topic?: string;
        relevance_score?: number;
    }>;
    metrics?: {
        total_duration_ms?: number;
        tokens_per_second?: number;
        eval_count?: number;
        used_model?: string;
    };
    status?: 'online' | 'fallback_offline';
}

export default function Chat({
    ollamaHealth: initialOllamaHealth,
    knowledgeBase,
    suggestedQuestions,
    availablePresets,
}: App.ViewModels.AgroSlm.AgroChatViewModel) {
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [input, setInput] = useState<string>('');
    const [isLoading, setIsLoading] = useState<boolean>(false);
    const [streamingMessageId, setStreamingMessageId] = useState<string | null>(
        null,
    );
    const [selectedPresetId, setSelectedPresetId] = useState<string>('none');
    const [ollamaHealth, setOllamaHealth] = useState(initialOllamaHealth);
    const [checkingHealth, setCheckingHealth] = useState<boolean>(false);
    const [elapsedTime, setElapsedTime] = useState<number>(0);
    const [expandedSources, setExpandedSources] = useState<
        Record<string, boolean>
    >({});

    const messagesEndRef = useRef<HTMLDivElement>(null);
    const textareaRef = useRef<HTMLTextAreaElement>(null);

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    useEffect(() => {
        scrollToBottom();
    }, [messages, isLoading]);

    // Timer during generation
    useEffect(() => {
        let timer: NodeJS.Timeout | null = null;
        if (isLoading) {
            setElapsedTime(0);
            timer = setInterval(() => {
                setElapsedTime((prev) => prev + 1);
            }, 1000);
        } else {
            setElapsedTime(0);
        }

        return () => {
            if (timer) clearInterval(timer);
        };
    }, [isLoading]);

    const activePreset = availablePresets.find((p) => p.id === selectedPresetId);

    const refreshOllamaStatus = async () => {
        setCheckingHealth(true);
        try {
            const res = await fetch('/agro-slm/status', {
                headers: { Accept: 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                setOllamaHealth(data);
            }
        } finally {
            setCheckingHealth(false);
        }
    };

    const toggleExpandSources = (msgId: string) => {
        setExpandedSources((prev) => ({
            ...prev,
            [msgId]: !prev[msgId],
        }));
    };

    const handleSendMessage = async (textToSend?: string) => {
        const text = (textToSend ?? input).trim();
        if (!text || isLoading) return;

        const userMessageId = `user-${Date.now()}`;
        const assistantMessageId = `assistant-${Date.now()}`;
        const now = new Date();
        const timeStr = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;

        const newUserMessage: ChatMessage = {
            id: userMessageId,
            role: 'user',
            content: text,
            timestamp: timeStr,
        };

        const updatedMessages = [...messages, newUserMessage];
        setMessages(updatedMessages);
        setInput('');
        setIsLoading(true);

        // Prepare compact request history (last 4 messages for fast CPU prompt evaluation)
        const historyPayload = updatedMessages
            .slice(-4)
            .map((m) => ({ role: m.role, content: m.content }));

        try {
            const csrfToken =
                (
                    document.querySelector(
                        'meta[name="csrf-token"]',
                    ) as HTMLMetaElement
                )?.content || '';

            const response = await fetch('/agro-slm/chat/stream', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'text/event-stream',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    message: text,
                    history: historyPayload,
                    edaphic_context: activePreset ? activePreset.params : null,
                }),
            });

            if (!response.ok || !response.body) {
                throw new Error(`Erro ${response.status} ao processar chat`);
            }

            const assistantTime = new Date();
            const assistantTimeStr = `${String(assistantTime.getHours()).padStart(2, '0')}:${String(assistantTime.getMinutes()).padStart(2, '0')}`;

            const reader = response.body.getReader();
            const decoder = new TextDecoder('utf-8');
            let sseBuffer = '';
            let hasInsertedAssistantMsg = false;
            let pendingRagSources: ChatMessage['rag_sources'] = [];

            const ensureAssistantMessage = () => {
                if (hasInsertedAssistantMsg) return;
                hasInsertedAssistantMsg = true;
                setStreamingMessageId(assistantMessageId);
                setMessages((prev) => [
                    ...prev,
                    {
                        id: assistantMessageId,
                        role: 'assistant',
                        content: '',
                        timestamp: assistantTimeStr,
                        rag_sources: pendingRagSources,
                    },
                ]);
            };

            const applySseBlock = (block: string) => {
                const lines = block.split(/\r?\n/);
                let eventType = 'message';
                const dataLines: string[] = [];

                for (const line of lines) {
                    if (line.startsWith('event:')) {
                        eventType = line.slice(6).trim();
                    } else if (line.startsWith('data:')) {
                        dataLines.push(line.slice(5).trim());
                    }
                }

                if (dataLines.length === 0) return;

                const payload = JSON.parse(dataLines.join('\n')) as Record<
                    string,
                    unknown
                >;

                if (eventType === 'meta') {
                    pendingRagSources =
                        (payload.rag_chunks as ChatMessage['rag_sources']) ??
                        [];
                } else if (eventType === 'chunk') {
                    const delta = String(payload.delta ?? '');
                    if (!delta) return;
                    ensureAssistantMessage();
                    setMessages((prev) =>
                        prev.map((m) =>
                            m.id === assistantMessageId
                                ? { ...m, content: m.content + delta }
                                : m,
                        ),
                    );
                } else if (eventType === 'done') {
                    ensureAssistantMessage();
                    setStreamingMessageId(null);
                    setMessages((prev) =>
                        prev.map((m) =>
                            m.id === assistantMessageId
                                ? {
                                      ...m,
                                      metrics:
                                          payload.metrics as ChatMessage['metrics'],
                                      status: payload.status as
                                          | 'online'
                                          | 'fallback_offline',
                                  }
                                : m,
                        ),
                    );
                }
            };

            while (true) {
                const { value, done } = await reader.read();
                if (done) break;

                sseBuffer += decoder.decode(value, { stream: true });
                const parts = sseBuffer.split(/\r?\n\r?\n/);
                sseBuffer = parts.pop() ?? '';

                for (const block of parts) {
                    if (block.trim()) {
                        applySseBlock(block);
                    }
                }
            }

            if (sseBuffer.trim()) {
                applySseBlock(sseBuffer);
            }
        } catch (err: unknown) {
            const errorText =
                err instanceof Error ? err.message : 'Falha na conexão';
            setMessages((prev) => [
                ...prev,
                {
                    id: `error-${Date.now()}`,
                    role: 'assistant',
                    content: `⚠️ Não foi possível obter resposta no momento (${errorText}). Por favor, verifique se o Ollama está ativo.`,
                    timestamp: timeStr,
                },
            ]);
        } finally {
            setStreamingMessageId(null);
            setIsLoading(false);
            setTimeout(() => textareaRef.current?.focus(), 100);
        }
    };

    const handleKeyDown = (e: KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            void handleSendMessage();
        }
    };

    const handleClearChat = () => {
        setMessages([]);
    };

    return (
        <div className="flex min-h-screen flex-col bg-stone-100/70 text-stone-900 dark:bg-stone-950 dark:text-stone-100">
            <Head title="Chat com AgroSolo AI — Conversação RAG Edafoclimática" />

            {/* Header Superior */}
            <header className="sticky top-0 z-30 border-b border-stone-200 bg-white/95 backdrop-blur-md dark:border-stone-800 dark:bg-stone-900/95">
                <div className="mx-auto flex max-w-[1400px] flex-wrap items-center justify-between gap-4 px-4 py-3 lg:px-6">
                    <div className="flex items-center gap-3">
                        <Link
                            href="/agro-slm"
                            className="inline-flex items-center gap-1.5 rounded-lg border border-stone-200 bg-stone-50 px-2.5 py-1.5 text-xs font-semibold text-stone-700 transition hover:bg-stone-100 dark:border-stone-800 dark:bg-stone-800 dark:text-stone-300 dark:hover:bg-stone-700"
                        >
                            <ArrowLeft className="h-3.5 w-3.5" />
                            <span>Dashboard SADA</span>
                        </Link>

                        <div className="h-5 w-px bg-stone-200 dark:bg-stone-800" />

                        <div className="flex items-center gap-2.5">
                            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-600 to-teal-800 text-white shadow-xs">
                                <MessageSquare className="h-4 w-4" />
                            </div>
                            <div>
                                <h1 className="flex items-center gap-2 text-sm font-bold text-stone-900 dark:text-white">
                                    <span>AgroSolo AI Chat</span>
                                    <span className="rounded bg-emerald-500/15 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800 uppercase dark:text-emerald-300">
                                        RAG Conversacional
                                    </span>
                                </h1>
                                <p className="text-[11px] text-stone-500 dark:text-stone-400">
                                    Assistente em Ciência do Solo, Manejo de
                                    Bacias e Embrapa/FAO
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Status do Ollama & Base */}
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="hidden items-center gap-1.5 rounded-lg border border-stone-200 bg-stone-50 px-2.5 py-1 text-xs text-stone-600 sm:flex dark:border-stone-800 dark:bg-stone-800/80 dark:text-stone-300">
                            <Database className="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                            <span>{knowledgeBase.total} docs Embrapa/FAO</span>
                        </div>

                        <div
                            className={`inline-flex items-center gap-2 rounded-lg border px-3 py-1 text-xs font-medium ${
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
                                    : 'Ollama Offline (RAG Fallback)'}
                            </span>
                            <button
                                type="button"
                                onClick={refreshOllamaStatus}
                                title="Verificar conexão com o Ollama"
                                className="ml-1 rounded p-0.5 hover:bg-black/10 dark:hover:bg-white/10"
                            >
                                <RefreshCw
                                    className={`h-3 w-3 ${checkingHealth ? 'animate-spin' : ''}`}
                                />
                            </button>
                        </div>

                        {messages.length > 0 && (
                            <button
                                type="button"
                                onClick={handleClearChat}
                                className="inline-flex items-center gap-1 rounded-lg border border-stone-200 bg-white px-2.5 py-1 text-xs text-stone-600 hover:bg-stone-100 hover:text-red-600 dark:border-stone-800 dark:bg-stone-800 dark:text-stone-300 dark:hover:text-red-400"
                                title="Limpar histórico da conversa"
                            >
                                <Eraser className="h-3.5 w-3.5" />
                                <span className="hidden sm:inline">Limpar</span>
                            </button>
                        )}
                    </div>
                </div>

                {/* Barra de Seleção de Contexto do Solo */}
                <div className="border-t border-stone-200/80 bg-stone-50/70 px-4 py-2 dark:border-stone-800 dark:bg-stone-900/60 lg:px-6">
                    <div className="mx-auto flex max-w-[1400px] flex-wrap items-center justify-between gap-3 text-xs">
                        <div className="flex items-center gap-2">
                            <Layers className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                            <span className="font-semibold text-stone-700 dark:text-stone-300">
                                Contexto Edafoclimático Ativo:
                            </span>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                onClick={() => setSelectedPresetId('none')}
                                className={`rounded-lg px-2.5 py-1 text-xs font-semibold transition ${
                                    selectedPresetId === 'none'
                                        ? 'bg-emerald-600 text-white shadow-2xs'
                                        : 'bg-white text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300 dark:hover:bg-stone-700'
                                }`}
                            >
                                Modo Geral (Livre)
                            </button>

                            {availablePresets.map((preset) => (
                                <button
                                    key={preset.id}
                                    type="button"
                                    onClick={() => setSelectedPresetId(preset.id)}
                                    className={`rounded-lg px-2.5 py-1 text-xs font-semibold transition ${
                                        selectedPresetId === preset.id
                                            ? 'bg-emerald-600 text-white shadow-2xs'
                                            : 'bg-white text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300 dark:hover:bg-stone-700'
                                    }`}
                                >
                                    {preset.title}
                                </button>
                            ))}
                        </div>
                    </div>
                </div>
            </header>

            {/* Área Central de Mensagens */}
            <main className="mx-auto flex w-full max-w-[1000px] flex-1 flex-col px-4 py-6">
                {/* Empty State: Nenhuma Mensagem Enviada */}
                {messages.length === 0 && (
                    <div className="my-auto flex flex-col items-center justify-center py-8 text-center">
                        <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 shadow-xs dark:bg-emerald-500/15 dark:text-emerald-400">
                            <Sprout className="h-8 w-8" />
                        </div>
                        <h2 className="text-lg font-bold text-stone-900 dark:text-white">
                            Converse com o Especialista AgroSolo AI
                        </h2>
                        <p className="mt-1 max-w-lg text-xs leading-relaxed text-stone-500 dark:text-stone-400">
                            Tire dúvidas agronômicas sobre correção de solo,
                            dinâmica de nutrientes, adubação verde, recuperação
                            de bacias e Sistema Plantio Direto fundamentadas
                            pela Embrapa e FAO.
                        </p>

                        {activePreset && (
                            <div className="mt-4 inline-flex items-center gap-2 rounded-xl border border-emerald-500/30 bg-emerald-50/60 px-3 py-1.5 text-xs text-emerald-800 dark:bg-emerald-950/20 dark:text-emerald-300">
                                <Sparkles className="h-3.5 w-3.5" />
                                <span>
                                    Contexto ativo:{' '}
                                    <strong>{activePreset.title}</strong> (
                                    {activePreset.subtitle})
                                </span>
                            </div>
                        )}

                        {/* Sugestões Rápidas de Perguntas */}
                        <div className="mt-8 w-full max-w-2xl">
                            <span className="mb-3 block text-xs font-bold tracking-wider text-stone-500 uppercase dark:text-stone-400">
                                Sugestões de Perguntas Rápidas:
                            </span>
                            <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                                {suggestedQuestions.map((q, idx) => (
                                    <button
                                        key={idx}
                                        type="button"
                                        onClick={() => void handleSendMessage(q)}
                                        className="group flex items-start gap-2.5 rounded-xl border border-stone-200 bg-white p-3.5 text-left text-xs transition hover:border-emerald-500/50 hover:bg-emerald-50/40 hover:shadow-2xs dark:border-stone-800 dark:bg-stone-900 dark:hover:bg-stone-800"
                                    >
                                        <Bot className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600 transition group-hover:scale-110" />
                                        <span className="leading-relaxed text-stone-700 group-hover:text-emerald-800 dark:text-stone-300 dark:group-hover:text-emerald-400">
                                            {q}
                                        </span>
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>
                )}

                {/* Thread de Mensagens */}
                {messages.length > 0 && (
                    <div className="space-y-6 pb-24">
                        {messages.map((msg) => {
                            const isUser = msg.role === 'user';
                            const hasSources =
                                msg.rag_sources && msg.rag_sources.length > 0;
                            const isExpanded = expandedSources[msg.id] ?? false;

                            return (
                                <div
                                    key={msg.id}
                                    className={`flex items-start gap-3 ${
                                        isUser ? 'justify-end' : 'justify-start'
                                    }`}
                                >
                                    {!isUser && (
                                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-600 to-teal-800 text-white shadow-xs">
                                            <Sprout className="h-4 w-4" />
                                        </div>
                                    )}

                                    <div
                                        className={`flex max-w-[85%] flex-col ${
                                            isUser ? 'items-end' : 'items-start'
                                        }`}
                                    >
                                        <div
                                            className={`rounded-2xl px-4 py-3 text-xs leading-relaxed shadow-xs ${
                                                isUser
                                                    ? 'rounded-tr-xs bg-emerald-600 text-white'
                                                    : 'rounded-tl-xs border border-stone-200 bg-white text-stone-800 dark:border-stone-800 dark:bg-stone-900 dark:text-stone-200'
                                            }`}
                                        >
                                            {/* Conteúdo formatado da mensagem */}
                                            {isUser ? (
                                                <div className="whitespace-pre-wrap">
                                                    {msg.content}
                                                </div>
                                            ) : (
                                                <MarkdownMessage
                                                    content={msg.content}
                                                />
                                            )}

                                            {/* Fontes RAG Utilizadas (para respostas da IA) */}
                                            {hasSources && (
                                                <div className="mt-3 border-t border-stone-100 pt-2.5 dark:border-stone-800">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            toggleExpandSources(
                                                                msg.id,
                                                            )
                                                        }
                                                        className="inline-flex items-center gap-1.5 text-[11px] font-semibold text-emerald-700 hover:text-emerald-800 dark:text-emerald-400"
                                                    >
                                                        <BookOpen className="h-3 w-3" />
                                                        <span>
                                                            {
                                                                msg.rag_sources
                                                                    ?.length
                                                            }{' '}
                                                            Fontes RAG
                                                            Consultadas
                                                        </span>
                                                        <ChevronDown
                                                            className={`h-3 w-3 transition-transform ${
                                                                isExpanded
                                                                    ? 'rotate-180'
                                                                    : ''
                                                            }`}
                                                        />
                                                    </button>

                                                    {isExpanded && (
                                                        <div className="mt-2 space-y-2">
                                                            {msg.rag_sources?.map(
                                                                (s) => (
                                                                    <div
                                                                        key={s.id}
                                                                        className="rounded-lg border border-stone-200/80 bg-stone-50 p-2 text-[11px] dark:border-stone-800 dark:bg-stone-800/60"
                                                                    >
                                                                        <div className="flex items-center gap-1.5">
                                                                            <span
                                                                                className={`rounded px-1.5 py-0.2 font-bold ${getSourceBadgeStyle(
                                                                                    s.source,
                                                                                )}`}
                                                                            >
                                                                                {
                                                                                    s.source
                                                                                }
                                                                            </span>
                                                                            <span className="font-semibold text-stone-800 dark:text-stone-200">
                                                                                {
                                                                                    s.title
                                                                                }
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                ),
                                                            )}
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </div>

                                        {/* Rodapé com Hora e Telemetria */}
                                        <div className="mt-1 flex items-center gap-2 px-1 text-[10px] text-stone-400">
                                            <span>{msg.timestamp}</span>
                                            {isLoading &&
                                                streamingMessageId ===
                                                    msg.id && (
                                                    <span className="inline-flex items-center gap-1 font-mono text-emerald-700 dark:text-emerald-400">
                                                        <RefreshCw className="h-2.5 w-2.5 animate-spin" />
                                                        Gerando... {elapsedTime}s
                                                    </span>
                                                )}
                                            {msg.metrics?.tokens_per_second ? (
                                                <span className="inline-flex items-center gap-1 font-mono text-emerald-700 dark:text-emerald-400">
                                                    <Zap className="h-3 w-3" />
                                                    {msg.metrics.tokens_per_second}{' '}
                                                    tok/s (
                                                    {(
                                                        (msg.metrics
                                                            .total_duration_ms ??
                                                            0) / 1000
                                                    ).toFixed(1)}
                                                    s)
                                                </span>
                                            ) : null}
                                        </div>
                                    </div>

                                    {isUser && (
                                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-stone-800 text-white shadow-xs dark:bg-stone-700">
                                            <User className="h-4 w-4" />
                                        </div>
                                    )}
                                </div>
                            );
                        })}

                        {/* Indicador de Digitação / Carregamento (apenas antes do primeiro token) */}
                        {isLoading && !streamingMessageId && (
                            <div className="flex items-start gap-3">
                                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-600 to-teal-800 text-white shadow-xs">
                                    <Sprout className="h-4 w-4 animate-pulse" />
                                </div>
                                <div className="rounded-2xl rounded-tl-xs border border-emerald-500/30 bg-white px-4 py-3 shadow-xs dark:border-emerald-500/20 dark:bg-stone-900">
                                    <div className="flex items-center gap-2">
                                        <RefreshCw className="h-3.5 w-3.5 animate-spin text-emerald-600" />
                                        <span className="text-xs font-semibold text-stone-700 dark:text-stone-300">
                                            AgroSolo AI consultando RAG e
                                            pensando...
                                        </span>
                                        <span className="rounded bg-emerald-500/10 px-1.5 py-0.5 font-mono text-[10px] font-bold text-emerald-700 dark:text-emerald-400">
                                            {elapsedTime}s
                                        </span>
                                    </div>
                                    <p className="mt-1 text-[11px] text-stone-400">
                                        Processamento local via Ollama (Qwen 2.5
                                        3B • Streaming)
                                    </p>
                                </div>
                            </div>
                        )}

                        <div ref={messagesEndRef} />
                    </div>
                )}
            </main>

            {/* Input Bar Flutuante na Parte Inferior */}
            <div className="sticky bottom-0 border-t border-stone-200 bg-white/90 p-4 backdrop-blur-md dark:border-stone-800 dark:bg-stone-900/90">
                <form
                    onSubmit={(e: FormEvent) => {
                        e.preventDefault();
                        void handleSendMessage();
                    }}
                    className="mx-auto flex max-w-[1000px] items-end gap-2"
                >
                    <div className="relative flex-1">
                        <textarea
                            ref={textareaRef}
                            rows={1}
                            value={input}
                            onChange={(e) => setInput(e.target.value)}
                            onKeyDown={handleKeyDown}
                            disabled={isLoading}
                            placeholder="Faça uma pergunta sobre manejo de solo, calagem, FBN, bacias... (Pressione Enter para enviar)"
                            className="max-h-32 min-h-[46px] w-full resize-none rounded-xl border border-stone-300 bg-stone-50 px-4 py-3 text-xs leading-relaxed text-stone-900 placeholder:text-stone-400 focus:border-emerald-600 focus:bg-white focus:ring-2 focus:ring-emerald-600/20 focus:outline-none dark:border-stone-700 dark:bg-stone-800 dark:text-stone-100"
                        />
                    </div>

                    <button
                        type="submit"
                        disabled={!input.trim() || isLoading}
                        className="flex h-[46px] w-[46px] shrink-0 cursor-pointer items-center justify-center rounded-xl bg-emerald-600 text-white shadow-xs transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                        title="Enviar mensagem"
                    >
                        {isLoading ? (
                            <RefreshCw className="h-4 w-4 animate-spin" />
                        ) : (
                            <Send className="h-4 w-4" />
                        )}
                    </button>
                </form>
            </div>
        </div>
    );
}

