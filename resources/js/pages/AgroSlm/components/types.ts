export type LaudoAgronomico = App.ViewModels.AgroSlm.LaudoAgronomicoViewModel;
export type DiagnosisPayload = App.ViewModels.AgroSlm.DiagnosisPayloadViewModel;
export type OllamaHealth = App.ViewModels.AgroSlm.OllamaHealthViewModel;
export type KnowledgeBaseInfo = App.ViewModels.AgroSlm.KnowledgeBaseSummaryViewModel;
export type RagChunk = App.ViewModels.KnowledgeChunk.KnowledgeChunkListItemViewModel;
export type DashboardProps = App.ViewModels.AgroSlm.AgroDashboardViewModel;
export type WorkspaceTab = 'report' | 'xray' | 'knowledge';

export interface SoilFormParams {
    bioma: string;
    relevo: string;
    textura_solo: string;
    ph: number;
    mos: number;
    cultura_atual: string;
    problemas: string[];
}

export interface ScenarioPreset {
    id: string;
    badge: string;
    title: string;
    subtitle: string;
    params: SoilFormParams;
}

export interface PhClassification {
    label: string;
    color: string;
    detail: string;
}

export interface MosClassification {
    label: string;
    color: string;
    detail: string;
}
