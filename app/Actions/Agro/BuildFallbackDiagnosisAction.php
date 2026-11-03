<?php

namespace App\Actions\Agro;

use Illuminate\Support\Str;

class BuildFallbackDiagnosisAction
{
    /**
     * Build a rich, scientifically grounded agronomic diagnosis using the retrieved RAG chunks
     * when Ollama is offline or for instant preview.
     *
     * @param  array{
     *   bioma: string,
     *   relevo: string,
     *   textura_solo: string,
     *   ph: float|int|string,
     *   mos: float|int|string,
     *   cultura_atual: string,
     *   problemas: list<string>
     * }  $params
     * @param  list<array{
     *   id: int,
     *   source: string,
     *   title: string,
     *   topic: string,
     *   content: string,
     *   keywords: list<string>,
     *   relevance_score?: int,
     *   match_reasons?: list<string>
     * }>  $ragChunks
     * @return array{
     *   resumo_executivo: string,
     *   diagnostico_fisico_quimico: array{
     *     analise_ph_nutrientes: string,
     *     status_materia_organica: string,
     *     potencial_fbn: string
     *   },
     *   plano_manejo_conservacionista: array{
     *     manejo_bacia_solo: list<string>,
     *     arranjo_rotacao_culturas: list<array{
     *       etapa: string,
     *       especies: string,
     *       objetivo_agronomico: string
     *     }>
     *   },
     *   relacao_condicionantes_ambientais: string,
     *   referencias_utilizadas: list<string>
     * }
     */
    public function execute(array $params, array $ragChunks): array
    {
        $bioma = $params['bioma'];
        $relevo = $params['relevo'];
        $textura = $params['textura_solo'];
        $ph = (float) $params['ph'];
        $mos = (float) $params['mos'];
        $cultura = $params['cultura_atual'];
        $problemas = $params['problemas'];
        $problemasStr = count($problemas) > 0 ? implode(', ', $problemas) : 'degradação edáfica incipiente';

        $phFormatted = number_format($ph, 1, ',', '');
        $mosFormatted = number_format($mos, 1, ',', '');

        // 1. Análise de pH e Nutrientes
        if ($ph < 4.9) {
            $analisePh = "O pH em CaCl₂ de {$phFormatted} caracteriza acidez ativa muito elevada, com forte atividade de alumínio trocável (Al³⁺) tóxico aos meristemas radiculares e intensa adsorção específica/precipitação de fósforo (P) nos óxidos de Fe e Al. Há severa deficiência de bases trocáveis (Ca²⁺ e Mg²⁺), baixa CTC efetiva nas cargas variáveis e insolubilização do Molibdênio (MoO₄²⁻). Recomenda-se calagem corretiva com calcário dolomítico (PRNT > 80%) para elevar a saturação por bases (V%) a 60–70% e situar o pH em CaCl₂ entre 5,5 e 6,0, associada à gessagem agrícola (CaSO₄·2H₂O) para fornecer Ca²⁺ e neutralizar Al³⁺ em subsuperfície (20–60 cm).";
        } elseif ($ph < 5.5) {
            $analisePh = "O pH em CaCl₂ de {$phFormatted} situa-se na faixa de acidez média a moderada. Embora a toxidez por Al³⁺ seja moderada, a disponibilidade de fósforo lábil e de Molibdênio ainda encontra-se subótima para máxima eficiência simbiótica e nutricional. Recomenda-se calagem de manutenção/correção ajustada à textura ({$textura}) — evitando supercalagem especialmente se o solo tiver baixa capacidade tampão — visando V% entre 55% e 65% e pH em CaCl₂ na faixa de 5,6 a 6,0.";
        } else {
            $analisePh = "O pH em CaCl₂ de {$phFormatted} encontra-se em faixa agronomicamente adequada (neutralização de Al³⁺ assegurada e boa saturação por bases V%). Nessa condição, a disponibilidade de macronutrientes (N, P, K, Ca, Mg, S) e de Molibdênio é favorável; o manejo químico deve focar na manutenção da fertilidade via ciclagem biológica de nutrientes, reposição gradual pela exportação das colheitas e monitoramento de micronutrientes catiônicos (Zn, Mn, Cu, Fe, B).";
        }

        // 2. Status da Matéria Orgânica e Física do Solo
        $isArenoso = Str::contains(Str::lower($textura), 'arenosa');
        $isArgiloso = Str::contains(Str::lower($textura), 'argilosa');

        if ($mos < 1.5 || ($isArgiloso && $mos < 2.0)) {
            $statusMos = "O teor de Matéria Orgânica do Solo (MOS) de {$mosFormatted}% é criticamente baixo para um solo de textura {$textura}. Como a MOS responde por 70% a 90% da CTC dos solos tropicais, esse déficit compromete drasticamente a retenção de cátions (K⁺, Ca²⁺, Mg²⁺), reduz a Capacidade de Água Disponível (CAD), desestabiliza os macroagregados e expõe a superfície ao selamento superficial pelo impacto das gotas de chuva. É indispensável adotar o Sistema Plantio Direto (SPD) com aporte mínimo de 8 a 10 t/ha/ano de matéria seca combinando gramíneas de alta relação C/N (> 40:1) e leguminosas.";
        } elseif ($mos < 2.8) {
            $statusMos = "O teor de MOS de {$mosFormatted}% encontra-se em nível intermediário para a classe textural ({$textura}), mas vulnerável à oxidação rápida caso haja revolvimento mecânico ou pousio descoberto. A manutenção da estabilidade de agregados, da macroporosidade de aeração (> 10%) e da infiltração hídrica requer cobertura morta permanente e fluxo contínuo de exsudatos radiculares e glomalina via rotação diversificada.";
        } else {
            $statusMos = "O teor de MOS de {$mosFormatted}% apresenta bom patamar de conservação orgânica, favorecendo a estruturação em grumos, a atividade da microbiota edáfica, a complexação orgânica de Al³⁺ e a capacidade tampão do solo. O objetivo prioritário deve ser preservar esse estoque de carbono evitando o preparo convencional e mantendo a cobertura viva/morta durante todo o ciclo hidrológico.";
        }

        // 3. Potencial de Fixação Biológica de Nitrogênio (FBN)
        if ($ph < 5.2 || $mos < 1.5 || in_array('Baixa Fixação de N', $problemas, true)) {
            $potencialFbn = "O potencial atual de Fixação Biológica de Nitrogênio (FBN) encontra-se severamente limitado. Sob pH {$phFormatted} e MOS de {$mosFormatted}%, há restrição na disponibilidade de Molibdênio (cofator FeMoCo da enzima nitrogenase) e Fósforo (ATP para redução do N₂), além de estresse térmico e hídrico na rizosfera que reduz a sobrevivência de estirpes de Bradyrhizobium e Rhizobium. Após a correção da acidez e cobertura do solo, recomenda-se coinoculação (Bradyrhizobium spp. + Azospirillum brasilense) associada à aplicação de Co e Mo via semente ou foliar.";
        } else {
            $potencialFbn = "O ambiente edáfico (pH {$phFormatted} e MOS {$mosFormatted}%) oferece condições favoráveis ao estabelecimento da simbiose diazotrófica, desde que assegurada a aeração do espaço poroso (ausência de compactação/hipóxia para a respiração nodular) e o suprimento de P, Co e Mo. A introdução de leguminosas de adubação verde potencializará aportes de 120 a 250 kg/ha de N biológico.";
        }

        // 4. Manejo de Bacia e Solo
        $manejoBacia = [];
        if (Str::contains(Str::lower($relevo), 'encosta forte')) {
            $manejoBacia[] = 'Implantação de terraços em gradiente (canais divergentes revestidos com vegetação rasteira) e cordões de contorno vegetados com Capim-vetiver (Chrysopogon zizanioides) para dissipar a energia cinética da enxurrada sem provocar supersaturação pontual que deflagre escorregamentos translacionais em Argissolos/Cambissolos.';
            $manejoBacia[] = 'Conversão das áreas de declividade > 15% para Sistemas Silvipastoris em nível (piqueteamento rotacionado seguindo as cotas topográficas) ou Sistemas Agroflorestais (SAFs) multiestratos, vedando o terço superior das encostas para recarga hídrica.';
        } elseif (Str::contains(Str::lower($relevo), 'meia encosta')) {
            $manejoBacia[] = 'Dimensionamento de terraços de base larga ou embutida em nível combinados com semeadura estritamente em contorno (curvas de nível) para seccionar o comprimento de rampa e maximizar a infiltração de água no perfil.';
            $manejoBacia[] = 'Construção de bacias de captação de enxurrada (barraginhas) nas margens de carreadores e estradas rurais para retenção de sedimentos e recarga do lençol freático da microbacia.';
        } elseif (Str::contains(Str::lower($relevo), 'fundo de vale')) {
            $manejoBacia[] = 'Isolamento e recomposição da Zona Ripária (Mata Ciliar com faixa tampão mínima de 30 a 50 m) utilizando espécies nativas adaptadas a regime hidromórfico sazonal, atuando como filtro biogeoquímico de sedimentos, fósforo particulado e nitrato.';
            $manejoBacia[] = 'Manejo conservacionista de solos hidromórficos sem drenagem predatória, mantendo cobertura vegetal permanente e controle rigoroso da carga animal para evitar pisoteio em solo saturado.';
        } else {
            $manejoBacia[] = 'Adoção integral do Sistema Plantio Direto (SPD) em nível no Topo de Chapada (área prioritária de recarga de aquíferos da bacia hidrográfica), garantindo taxa de infiltração básica elevada e impedindo a geração de escoamento superficial para as vertentes inferiores.';
            $manejoBacia[] = 'Subdivisão da gleba com faixas de retenção vegetadas e quebra-ventos arbóreos (integração lavoura-floresta) para reduzir a evapotranspiração potencial e a erosão laminar/eólica.';
        }

        if (in_array('Compactação', $problemas, true)) {
            $manejoBacia[] = 'Descompactação biológica do perfil ("biodrilling") por meio de plantas de cobertura com raízes pivotantes e fasciculadas agressivas, aliada ao controle de tráfego agrícola e respeito à faixa de friabilidade do solo.';
        }
        if (in_array('Assoreamento de Nascente', $problemas, true)) {
            $manejoBacia[] = 'Cercamento imediato das nascentes (raio mínimo de 50 m), reconformação de taludes erodidos com bioengenharia de solos (biomantas e paliçadas de bambu) e enriquecimento florestal com espécies pioneiras e secundárias.';
        }
        if (in_array('Baixa Palhada', $problemas, true) || $isArenoso) {
            $manejoBacia[] = 'Consorciação de gramíneas forrageiras tropicais (Sistema Santa Fé / Integração Lavoura-Pecuária) para garantir aporte mínimo de 8 a 10 t/ha/ano de matéria seca com relação C/N equilibrada e alta persistência sobre o solo.';
        }

        // 5. Arranjo de Rotação de Culturas customizado por Bioma/Textura
        if ($bioma === 'Mata Atlântica' && Str::contains(Str::lower($relevo), 'encosta')) {
            $rotacao = [
                [
                    'etapa' => '1ª Etapa — Recuperação de Base e Cobertura Viva em Nível (Primavera/Verão)',
                    'especies' => 'Consórcio de Braquiária-brizantha cv. Marandu (Urochloa brizantha) + Amendoim-forrageiro (Arachis pintoi) + Feijão-guandu (Cajanus cajan)',
                    'objetivo_agronomico' => 'Estabelecimento de tapete herbáceo perene de alta cobertura contra impacto da gota de chuva, descompactação biológica pelo sistema pivotante do Guandu e aporte contínuo de N via FBN do Arachis pintoi (120–180 kg N/ha/ano).',
                ],
                [
                    'etapa' => '2ª Etapa — Faixas de Adubação Verde e Ciclagem de Fósforo (Outono/Inverno)',
                    'especies' => 'Nabo-forrageiro (Raphanus sativus var. oleiferus) + Aveia-preta (Avena strigosa) + Ervilhaca (Vicia sativa)',
                    'objetivo_agronomico' => 'Rompimento de camadas adensadas superficiais causadas por pisoteio animal, proteção do horizonte A contra chuvas frontais e imobilização temporária de nutrientes evitando lixiviação na encosta.',
                ],
                [
                    'etapa' => '3ª Etapa — Estrato Arbustivo-Arbóreo / Sistema Silvipastoril ou SAF em Contorno',
                    'especies' => 'Alamedas em nível de Gliricídia (Gliricidia sepium), Margaridão (Tithonia diversifolia), Inga edulis e espécies nativas da Mata Atlântica',
                    'objetivo_agronomico' => 'Ancoragem mecânica profunda do solo no contato A/Bt do Argissolo (prevenção de escorregamentos), poda periódica para adubação verde rica em N, P e K e conforto térmico.',
                ],
            ];
        } elseif ($isArenoso) {
            $rotacao = [
                [
                    'etapa' => '1ª Etapa — Adubação Verde e Estruturação Inicial (Primavera/Verão)',
                    'especies' => 'Consórcio de Milheto (Pennisetum glaucum) + Crotalária-ochroleuca (Crotalaria ochroleuca) ou Feijão-caupi (Vigna unguiculata)',
                    'objetivo_agronomico' => 'Formação rápida de biomassa aérea e radicular em solo arenoso de baixa fertilidade, fixação biológica de N (100–150 kg/ha), supressão de nematoides e criação de "esponja orgânica" para elevar a retenção hídrica (CAD).',
                ],
                [
                    'etapa' => '2ª Etapa — Safra Principal Conservacionista sob Palhada Densa',
                    'especies' => 'Cultura principal (Soja Glycine max, Milho Zea mays ou Mandioca Manihot esculenta em faixas) inoculada com Bradyrhizobium + Azospirillum brasilense',
                    'objetivo_agronomico' => 'Produção econômica sobre espessa camada de palhada de alta relação C/N do Milheto, reduzindo a temperatura superficial do Neossolo Quartzarênico em até 12 °C e minimizando perdas de N e K por lixiviação.',
                ],
                [
                    'etapa' => '3ª Etapa — Segunda Safra / Entressafra de Persistência (Sistema Santa Fé)',
                    'especies' => 'Consórcio de Urochloa ruziziensis (ou Urochloa brizantha cv. Piatã) + Feijão-guandu-anão (Cajanus cajan)',
                    'objetivo_agronomico' => 'Aporte maciço de raízes finas e exsudatos micorrízicos (glomalina) para agregação dos grãos de quartzo, elevação gradual da MOS (> 1,5%) e cobertura permanente durante a estação seca.',
                ],
            ];
        } else {
            $rotacao = [
                [
                    'etapa' => '1ª Etapa — Adubação Verde Pré-Safra / Descompactação e FBN (Início das Águas)',
                    'especies' => 'Crotalaria juncea (ou Crotalaria spectabilis) consorciada com Milheto (Pennisetum glaucum)',
                    'objetivo_agronomico' => 'Aporte rápido de Nitrogênio via FBN (150–220 kg N/ha), manejo biológico de fitonematoides e produção de palhada de relação C/N intermediária (~25:1) para destravar a atividade microbiana do solo.',
                ],
                [
                    'etapa' => '2ª Etapa — Safra Principal de Verão em Sistema Plantio Direto (SPD)',
                    'especies' => 'Soja (Glycine max) ou Feijão com coinoculação (Bradyrhizobium japonicum/elkanii + Azospirillum brasilense) e aplicação de Co + Mo',
                    'objetivo_agronomico' => 'Maximização da eficiência simbiótica da nitrogenase após correção da acidez (pH > 5,5), aproveitando a estrutura física preservada e a umidade retida pela palhada antecessora.',
                ],
                [
                    'etapa' => '3ª Etapa — Segunda Safra / Entressafra Consorciada (Sistema Santa Fé)',
                    'especies' => 'Milho (Zea mays) ou Sorgo (Sorghum bicolor) consorciado com Urochloa ruziziensis e Feijão-guandu (Cajanus cajan)',
                    'objetivo_agronomico' => 'Descompactação biológica subsuperficial pela raiz pivotante do Guandu, intensa formação de bioporos e deposição de 8 a 12 t/ha de palhada de alta relação C/N (> 40:1) pela Braquiária para proteção durante o período seco.',
                ],
            ];
        }

        $referencias = array_map(
            fn (array $c): string => "{$c['source']} — {$c['title']}",
            $ragChunks
        );

        return [
            'resumo_executivo' => "Diagnóstico agroecológico e edafoclimático para o bioma {$bioma} em condição de {$relevo}, solo de textura {$textura} com pH em CaCl₂ {$phFormatted} e MOS de {$mosFormatted}% (histórico: {$cultura}; problemas: {$problemasStr}). A estratégia prioritária integra a correção da acidez e neutralização do Al³⁺ no perfil à reconstrução da infraestrutura biológica do solo via Sistema Plantio Direto (SPD), consórcio de gramíneas e leguminosas fixadoras de N e contenção hidrossedimentológica na microbacia.",
            'diagnostico_fisico_quimico' => [
                'analise_ph_nutrientes' => $analisePh,
                'status_materia_organica' => $statusMos,
                'potencial_fbn' => $potencialFbn,
            ],
            'plano_manejo_conservacionista' => [
                'manejo_bacia_solo' => $manejoBacia,
                'arranjo_rotacao_culturas' => $rotacao,
            ],
            'relacao_condicionantes_ambientais' => "A tomada de decisão agronômica neste cenário decorre diretamente da interação entre os fatores de formação e uso do solo: no bioma {$bioma}, a posição topográfica de {$relevo} associada à textura {$textura} governa o balanço entre infiltração vertical e escoamento superficial (enxurrada). Quimicamente, o pH {$phFormatted} e o teor de MOS de {$mosFormatted}% ditam a carga líquida dos coloides (CTC efetiva), a solubilidade do fósforo e do molibdênio e a viabilidade da simbiose com Bradyrhizobium. Por isso, práticas mecânicas na bacia (como controle de comprimento de rampa e proteção ripária) são indissociáveis de práticas edáficas e vegetativas (calagem, aporte de palhada com alta relação C/N e descompactação radicular), convertendo um agroecossistema degradado em um sistema resiliente conservador de água, carbono e biodiversidade.",
            'referencias_utilizadas' => $referencias,
        ];
    }
}
