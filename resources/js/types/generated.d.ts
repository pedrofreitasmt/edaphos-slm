declare namespace App {
namespace ViewModels {
namespace AgroSlm {
export type AgroDashboardViewModel = {
ollamaHealth: App.ViewModels.AgroSlm.OllamaHealthViewModel,
knowledgeBase: App.ViewModels.AgroSlm.KnowledgeBaseSummaryViewModel,
initialDiagnosis: App.ViewModels.AgroSlm.DiagnosisPayloadViewModel,
};
export type DiagnosisPayloadViewModel = {
laudo: App.ViewModels.AgroSlm.LaudoAgronomicoViewModel,
xray: App.ViewModels.AgroSlm.SlmXrayViewModel,
};
export type DiagnosticoFisicoQuimicoViewModel = {
analise_ph_nutrientes: string,
status_materia_organica: string,
potencial_fbn: string,
};
export type KnowledgeBaseSummaryViewModel = {
total: number,
sources: {
Embrapa: number,
FAO: number,
Wikipedia: number,
},
chunks: App.ViewModels.KnowledgeChunk.KnowledgeChunkListItemViewModel[],
};
export type LaudoAgronomicoViewModel = {
resumo_executivo: string,
diagnostico_fisico_quimico: App.ViewModels.AgroSlm.DiagnosticoFisicoQuimicoViewModel,
plano_manejo_conservacionista: App.ViewModels.AgroSlm.PlanoManejoViewModel,
relacao_condicionantes_ambientais: string,
referencias_utilizadas: string[],
};
export type OllamaHealthViewModel = {
online: boolean,
base_url: string,
configured_model: string,
model_installed: boolean,
available_models: string[],
message: string,
};
export type PlanoManejoViewModel = {
manejo_bacia_solo: string[],
arranjo_rotacao_culturas: App.ViewModels.AgroSlm.RotacaoEtapaViewModel[],
};
export type RotacaoEtapaViewModel = {
etapa: string,
especies: string,
objetivo_agronomico: string,
};
export type SlmMetricsViewModel = {
total_duration_ms: number,
load_duration_ms: number,
prompt_eval_count: number,
eval_count: number,
eval_duration_ms: number,
tokens_per_second: number,
};
export type SlmXrayViewModel = {
model: string,
configured_model: string,
base_url: string,
status: string,
status_label: string,
ollama_reachable: boolean,
fallback_reason: string | null,
metrics: App.ViewModels.AgroSlm.SlmMetricsViewModel,
rag_chunks: App.ViewModels.KnowledgeChunk.KnowledgeChunkListItemViewModel[],
raw_prompt: string,
system_prompt: string,
json_schema: Record<string, any>,
};
}
namespace KnowledgeChunk {
export type KnowledgeChunkListItemViewModel = {
id: number,
source: string,
title: string,
topic: string,
content: string,
keywords: string[],
relevance_score: number | null,
match_reasons: string[] | null,
};
}
}
}
