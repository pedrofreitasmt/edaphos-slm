import {
    Activity,
    ArrowUpRight,
    BookOpen,
    CheckCircle2,
    Droplets,
    FlaskConical,
    Layers,
    Microscope,
    Sprout,
    Terminal,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import type { LaudoAgronomico, SoilFormParams } from './types';

interface AgronomicReportSectionProps {
    laudo: LaudoAgronomico;
    form: SoilFormParams;
    onOpenXray: () => void;
    onOpenKnowledgeBase: () => void;
}

export function AgronomicReportSection({
    laudo,
    form,
    onOpenXray,
    onOpenKnowledgeBase,
}: AgronomicReportSectionProps) {
    return (
        <div className="space-y-5">
            {/* Resumo Executivo Hero Card */}
            <div className="relative overflow-hidden rounded-2xl border border-emerald-500/30 bg-gradient-to-br from-emerald-950 via-emerald-900 to-teal-950 p-6 text-white shadow-sm">
                <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-2.5">
                        <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-500/20 text-emerald-300">
                            <Microscope className="h-5 w-5" />
                        </div>
                        <div>
                            <h2 className="text-sm font-bold tracking-wider text-emerald-200 uppercase">
                                Síntese Executiva do Diagnóstico
                            </h2>
                            <p className="text-[11px] text-emerald-300/80">
                                {form.relevo} • Textura {form.textura_solo}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-1.5">
                        <Badge className="border-emerald-400/30 bg-emerald-500/20 text-emerald-100">
                            {form.bioma}
                        </Badge>
                        <Badge className="border-emerald-400/30 bg-emerald-500/20 text-emerald-100">
                            pH {form.ph}
                        </Badge>
                        <Badge className="border-emerald-400/30 bg-emerald-500/20 text-emerald-100">
                            MOS {form.mos}%
                        </Badge>
                        <button
                            type="button"
                            onClick={onOpenXray}
                            className="ml-1 inline-flex items-center gap-1 rounded-lg border border-emerald-400/30 bg-white/10 px-2.5 py-1 text-[11px] font-semibold text-emerald-100 transition hover:bg-white/20"
                        >
                            <Terminal className="h-3 w-3" />
                            <span>Ver Raio-X</span>
                        </button>
                    </div>
                </div>

                <p className="text-sm leading-relaxed text-emerald-50/95">
                    {laudo.resumo_executivo}
                </p>
            </div>

            {/* Diagnóstico Físico-Químico e Simbiótico (3 Cards Lado a Lado) */}
            <div className="rounded-2xl border border-stone-200 bg-white p-5 shadow-xs dark:border-stone-800 dark:bg-stone-900">
                <div className="mb-4 flex items-center gap-2 border-b border-stone-100 pb-3 dark:border-stone-800">
                    <Activity className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                    <h3 className="text-sm font-bold tracking-tight text-stone-900 uppercase dark:text-white">
                        Diagnóstico Físico-Químico e Biológico do Solo
                    </h3>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    {/* Card 1: pH e Nutrientes */}
                    <div className="flex flex-col rounded-xl border border-amber-500/25 bg-amber-50/40 p-4 dark:border-amber-500/20 dark:bg-amber-950/20">
                        <div className="mb-2 flex items-center gap-2 text-xs font-bold text-amber-900 uppercase dark:text-amber-300">
                            <FlaskConical className="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                            <span>1. Química, pH & Acidez (Al³⁺)</span>
                        </div>
                        <p className="text-xs leading-relaxed text-stone-700 dark:text-stone-300">
                            {
                                laudo.diagnostico_fisico_quimico
                                    .analise_ph_nutrientes
                            }
                        </p>
                    </div>

                    {/* Card 2: Matéria Orgânica e Física */}
                    <div className="flex flex-col rounded-xl border border-emerald-500/25 bg-emerald-50/40 p-4 dark:border-emerald-500/20 dark:bg-emerald-950/20">
                        <div className="mb-2 flex items-center gap-2 text-xs font-bold text-emerald-900 uppercase dark:text-emerald-300">
                            <Layers className="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                            <span>2. Matéria Orgânica & Física</span>
                        </div>
                        <p className="text-xs leading-relaxed text-stone-700 dark:text-stone-300">
                            {
                                laudo.diagnostico_fisico_quimico
                                    .status_materia_organica
                            }
                        </p>
                    </div>

                    {/* Card 3: FBN */}
                    <div className="flex flex-col rounded-xl border border-sky-500/25 bg-sky-50/40 p-4 dark:border-sky-500/20 dark:bg-sky-950/20">
                        <div className="mb-2 flex items-center gap-2 text-xs font-bold text-sky-900 uppercase dark:text-sky-300">
                            <Sprout className="h-4 w-4 shrink-0 text-sky-600 dark:text-sky-400" />
                            <span>3. Fixação Biológica de N (FBN)</span>
                        </div>
                        <p className="text-xs leading-relaxed text-stone-700 dark:text-stone-300">
                            {laudo.diagnostico_fisico_quimico.potencial_fbn}
                        </p>
                    </div>
                </div>
            </div>

            {/* Plano de Manejo Conservacionista: Bacia e Rotação Lado a Lado */}
            <div className="grid grid-cols-1 gap-5 lg:grid-cols-12">
                {/* Coluna 1: Manejo de Bacia e Solo (5 cols) */}
                <div className="rounded-2xl border border-stone-200 bg-white p-5 shadow-xs lg:col-span-5 dark:border-stone-800 dark:bg-stone-900">
                    <div className="mb-4 flex items-center gap-2 border-b border-stone-100 pb-3 dark:border-stone-800">
                        <Droplets className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                        <div>
                            <h3 className="text-sm font-bold tracking-tight text-stone-900 uppercase dark:text-white">
                                Manejo de Microbacia e Solo
                            </h3>
                            <p className="text-[11px] text-stone-500 dark:text-stone-400">
                                Práticas mecânicas, edáficas e vegetativas
                            </p>
                        </div>
                    </div>

                    <ul className="space-y-2.5">
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

                {/* Coluna 2: Cronograma de Rotação de Culturas e Adubação Verde (7 cols) */}
                <div className="rounded-2xl border border-stone-200 bg-white p-5 shadow-xs lg:col-span-7 dark:border-stone-800 dark:bg-stone-900">
                    <div className="mb-4 flex items-center gap-2 border-b border-stone-100 pb-3 dark:border-stone-800">
                        <Sprout className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                        <div>
                            <h3 className="text-sm font-bold tracking-tight text-stone-900 uppercase dark:text-white">
                                Arranjo Cronológico de Rotação & Adubação Verde
                            </h3>
                            <p className="text-[11px] text-stone-500 dark:text-stone-400">
                                Sequência de sucessão cultural e formação de
                                palhada (SPD)
                            </p>
                        </div>
                    </div>

                    <div className="space-y-3">
                        {laudo.plano_manejo_conservacionista.arranjo_rotacao_culturas.map(
                            (item, index) => (
                                <div
                                    key={index}
                                    className="rounded-xl border border-emerald-600/20 bg-gradient-to-r from-emerald-50/60 to-stone-50 p-3.5 dark:border-emerald-500/20 dark:from-emerald-950/25 dark:to-stone-900"
                                >
                                    <div className="mb-1 flex items-center gap-2">
                                        <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-[11px] font-bold text-white">
                                            {index + 1}
                                        </span>
                                        <span className="text-xs font-bold text-emerald-800 dark:text-emerald-300">
                                            {item.etapa}
                                        </span>
                                    </div>
                                    <div className="mt-1.5 pl-7">
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
                                            {item.objetivo_agronomico}
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
                <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <BookOpen className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                        <h3 className="text-sm font-bold tracking-tight text-stone-900 uppercase dark:text-white">
                            Fundamentação Edafoclimática (Por que estas
                            escolhas?)
                        </h3>
                    </div>
                    <button
                        type="button"
                        onClick={onOpenKnowledgeBase}
                        className="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 hover:text-emerald-800 dark:text-emerald-400"
                    >
                        <span>Explorar Base RAG</span>
                        <ArrowUpRight className="h-3.5 w-3.5" />
                    </button>
                </div>

                <p className="text-xs leading-relaxed text-stone-700 dark:text-stone-300">
                    {laudo.relacao_condicionantes_ambientais}
                </p>

                <div className="mt-4 border-t border-stone-100 pt-3 dark:border-stone-800">
                    <span className="mb-2 block text-[11px] font-bold tracking-wider text-stone-500 uppercase dark:text-stone-400">
                        Fontes Documentais Citadas (RAG Local):
                    </span>
                    <div className="flex flex-wrap gap-1.5">
                        {laudo.referencias_utilizadas.map((ref, idx) => (
                            <button
                                key={idx}
                                type="button"
                                onClick={onOpenKnowledgeBase}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-stone-200 bg-stone-50 px-2.5 py-1 text-[11px] font-medium text-stone-700 transition hover:border-emerald-500/40 hover:bg-emerald-50/50 dark:border-stone-700 dark:bg-stone-800 dark:text-stone-300"
                            >
                                <BookOpen className="h-3 w-3 text-emerald-600" />
                                <span>{ref}</span>
                            </button>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
