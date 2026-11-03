import type {
    MosClassification,
    PhClassification,
    ScenarioPreset,
} from './types';

export const BIOMAS = [
    'Cerrado',
    'Mata Atlântica',
    'Caatinga',
    'Pampa',
    'Amazônia',
    'Pantanal',
];

export const RELEVOS = [
    'Topo de Chapada',
    'Meia Encosta Suave (3-8%)',
    'Encosta Forte (>15%)',
    'Fundo de Vale / Várzea',
];

export const TEXTURAS = [
    'Arenosa (<15% argila)',
    'Média (15-35% argila)',
    'Argilosa (>35% argila)',
];

export const PROBLEMAS_DISPONIVEIS = [
    'Compactação',
    'Erosão Laminar',
    'Baixa Fixação de N',
    'Assoreamento de Nascente',
    'Baixa Palhada',
];

export const SCENARIO_PRESETS: ScenarioPreset[] = [
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

export function getPhClassification(ph: number): PhClassification {
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

export function getMosClassification(mos: number): MosClassification {
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

export function getSourceBadgeStyle(source: string): string {
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

