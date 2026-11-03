import {
    Check,
    Cpu,
    FlaskConical,
    Layers,
    Leaf,
    Mountain,
    RefreshCw,
    Sparkles,
    Trees,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    BIOMAS,
    getMosClassification,
    getPhClassification,
    PROBLEMAS_DISPONIVEIS,
    RELEVOS,
    SCENARIO_PRESETS,
    TEXTURAS,
} from './constants';
import type { ScenarioPreset, SoilFormParams } from './types';

interface SoilConditionsFormProps {
    form: SoilFormParams;
    activePresetId: string;
    isGenerating: boolean;
    onSelectPreset: (preset: ScenarioPreset) => void;
    onChangeForm: (newForm: SoilFormParams) => void;
    onSubmit: (e: FormEvent) => void;
}

export function SoilConditionsForm({
    form,
    activePresetId,
    isGenerating,
    onSelectPreset,
    onChangeForm,
    onSubmit,
}: SoilConditionsFormProps) {
    const phInfo = getPhClassification(form.ph);
    const mosInfo = getMosClassification(form.mos);

    const toggleProblema = (problema: string) => {
        const exists = form.problemas.includes(problema);
        onChangeForm({
            ...form,
            problemas: exists
                ? form.problemas.filter((p) => p !== problema)
                : [...form.problemas, problema],
        });
    };

    return (
        <div className="rounded-2xl border border-stone-200 bg-white p-5 shadow-xs dark:border-stone-800 dark:bg-stone-900">
            {/* Seletor Compacto de Cenários Pré-Carregados */}
            <div className="mb-5 rounded-xl border border-emerald-500/20 bg-emerald-50/40 p-3 dark:border-emerald-500/15 dark:bg-emerald-950/20">
                <div className="mb-2 flex items-center justify-between">
                    <span className="flex items-center gap-1.5 text-[11px] font-bold tracking-wider text-emerald-800 uppercase dark:text-emerald-300">
                        <Sparkles className="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                        Cenários Rápidos (1-Click)
                    </span>
                    <span className="text-[10px] text-stone-500 dark:text-stone-400">
                        Preenche e diagnostica
                    </span>
                </div>

                <div className="space-y-1.5">
                    {SCENARIO_PRESETS.map((preset) => {
                        const isSelected = activePresetId === preset.id;
                        return (
                            <button
                                key={preset.id}
                                type="button"
                                disabled={isGenerating}
                                onClick={() => onSelectPreset(preset)}
                                className={`flex w-full items-center justify-between rounded-lg border px-3 py-2 text-left transition ${
                                    isSelected
                                        ? 'border-emerald-600 bg-emerald-600 text-white shadow-2xs'
                                        : 'border-stone-200/90 bg-white text-stone-800 hover:border-emerald-500/50 hover:bg-emerald-50/50 dark:border-stone-800 dark:bg-stone-900 dark:text-stone-200 dark:hover:border-emerald-500/40'
                                }`}
                            >
                                <div className="min-w-0 pr-2">
                                    <div className="truncate text-xs font-bold">
                                        {preset.title}
                                    </div>
                                    <div
                                        className={`truncate text-[10px] ${
                                            isSelected
                                                ? 'text-emerald-100'
                                                : 'text-stone-500 dark:text-stone-400'
                                        }`}
                                    >
                                        {preset.params.bioma} •{' '}
                                        {preset.params.relevo}
                                    </div>
                                </div>
                                <span
                                    className={`shrink-0 rounded px-1.5 py-0.5 font-mono text-[10px] font-bold ${
                                        isSelected
                                            ? 'bg-white/20 text-white'
                                            : 'bg-stone-100 text-emerald-700 dark:bg-stone-800 dark:text-emerald-400'
                                    }`}
                                >
                                    pH {preset.params.ph}
                                </span>
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* Título do Formulário */}
            <div className="mb-4 flex items-center gap-2.5 border-b border-stone-100 pb-3 dark:border-stone-800">
                <FlaskConical className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                <div>
                    <h2 className="text-sm font-bold tracking-tight text-stone-900 uppercase dark:text-white">
                        Condicionantes Edáficos
                    </h2>
                    <p className="text-xs text-stone-500 dark:text-stone-400">
                        Ajuste os parâmetros do solo e da microbacia
                    </p>
                </div>
            </div>

            <form onSubmit={onSubmit} className="space-y-4">
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
                        onChange={(e) =>
                            onChangeForm({ ...form, bioma: e.target.value })
                        }
                        className="w-full rounded-lg border border-stone-300 bg-stone-50 px-3 py-2 text-xs font-medium text-stone-900 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none dark:border-stone-700 dark:bg-stone-800 dark:text-stone-100"
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
                        onChange={(e) =>
                            onChangeForm({ ...form, relevo: e.target.value })
                        }
                        className="w-full rounded-lg border border-stone-300 bg-stone-50 px-3 py-2 text-xs font-medium text-stone-900 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none dark:border-stone-700 dark:bg-stone-800 dark:text-stone-100"
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
                        onChange={(e) =>
                            onChangeForm({
                                ...form,
                                textura_solo: e.target.value,
                            })
                        }
                        className="w-full rounded-lg border border-stone-300 bg-stone-50 px-3 py-2 text-xs font-medium text-stone-900 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none dark:border-stone-700 dark:bg-stone-800 dark:text-stone-100"
                    >
                        {TEXTURAS.map((tex) => (
                            <option key={tex} value={tex}>
                                {tex}
                            </option>
                        ))}
                    </select>
                </div>

                {/* Sliders: pH e MOS lado a lado ou empilhados de forma compacta */}
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-1">
                    {/* pH em CaCl2 */}
                    <div className="rounded-xl border border-stone-200/80 bg-stone-50/70 p-3 dark:border-stone-800 dark:bg-stone-800/40">
                        <div className="mb-1.5 flex items-center justify-between">
                            <label
                                htmlFor="ph-input"
                                className="text-xs font-semibold text-stone-700 dark:text-stone-300"
                            >
                                pH em CaCl₂
                            </label>
                            <input
                                id="ph-input"
                                type="number"
                                min={3.5}
                                max={7.5}
                                step={0.1}
                                value={form.ph}
                                onChange={(e) => {
                                    const val = parseFloat(e.target.value);
                                    if (!Number.isNaN(val)) {
                                        onChangeForm({
                                            ...form,
                                            ph: Math.min(
                                                7.5,
                                                Math.max(3.5, val),
                                            ),
                                        });
                                    }
                                }}
                                className="w-16 rounded-md border border-stone-300 bg-white px-2 py-0.5 text-right font-mono text-xs font-bold text-stone-900 dark:border-stone-700 dark:bg-stone-900 dark:text-white"
                            />
                        </div>
                        <input
                            type="range"
                            min={3.5}
                            max={7.5}
                            step={0.1}
                            value={form.ph}
                            onChange={(e) =>
                                onChangeForm({
                                    ...form,
                                    ph: parseFloat(e.target.value),
                                })
                            }
                            className="w-full accent-emerald-600"
                        />
                        <div className="mt-1.5 flex items-center justify-between gap-2">
                            <span
                                className={`inline-flex items-center rounded border px-1.5 py-0.5 text-[10px] font-semibold ${phInfo.color}`}
                            >
                                {phInfo.label}
                            </span>
                            <span className="truncate text-[10px] text-stone-500 dark:text-stone-400">
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
                                Matéria Orgânica (MOS)
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
                                        const val = parseFloat(e.target.value);
                                        if (!Number.isNaN(val)) {
                                            onChangeForm({
                                                ...form,
                                                mos: Math.min(
                                                    6.0,
                                                    Math.max(0.5, val),
                                                ),
                                            });
                                        }
                                    }}
                                    className="w-16 rounded-md border border-stone-300 bg-white px-2 py-0.5 text-right font-mono text-xs font-bold text-stone-900 dark:border-stone-700 dark:bg-stone-900 dark:text-white"
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
                            onChange={(e) =>
                                onChangeForm({
                                    ...form,
                                    mos: parseFloat(e.target.value),
                                })
                            }
                            className="w-full accent-emerald-600"
                        />
                        <div className="mt-1.5 flex items-center justify-between gap-2">
                            <span
                                className={`inline-flex items-center rounded border px-1.5 py-0.5 text-[10px] font-semibold ${mosInfo.color}`}
                            >
                                {mosInfo.label}
                            </span>
                            <span className="truncate text-[10px] text-stone-500 dark:text-stone-400">
                                {mosInfo.detail}
                            </span>
                        </div>
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
                        onChange={(e) =>
                            onChangeForm({
                                ...form,
                                cultura_atual: e.target.value,
                            })
                        }
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
                            const checked = form.problemas.includes(prob);
                            return (
                                <button
                                    key={prob}
                                    type="button"
                                    onClick={() => toggleProblema(prob)}
                                    className={`inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-[11px] font-medium transition ${
                                        checked
                                            ? 'border-emerald-600 bg-emerald-600 text-white shadow-2xs'
                                            : 'border-stone-300 bg-stone-50 text-stone-700 hover:border-emerald-500/50 dark:border-stone-700 dark:bg-stone-800 dark:text-stone-300'
                                    }`}
                                >
                                    <span
                                        className={`flex h-3 w-3 items-center justify-center rounded-xs border ${
                                            checked
                                                ? 'border-white bg-white/20'
                                                : 'border-stone-400'
                                        }`}
                                    >
                                        {checked && (
                                            <Check className="h-2 w-2" />
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
                    className="w-full cursor-pointer bg-emerald-700 py-5 text-xs font-bold tracking-wide text-white uppercase shadow-sm hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500"
                >
                    {isGenerating ? (
                        <span className="flex items-center gap-2">
                            <RefreshCw className="h-4 w-4 animate-spin" />
                            Processando no SLM Local...
                        </span>
                    ) : (
                        <span className="flex items-center gap-2">
                            <Cpu className="h-4 w-4" />
                            Gerar Diagnóstico no SLM
                        </span>
                    )}
                </Button>
            </form>
        </div>
    );
}
