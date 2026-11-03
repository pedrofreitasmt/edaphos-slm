<?php

namespace Database\Seeders;

use App\Models\KnowledgeChunk;
use Illuminate\Database\Seeder;

class KnowledgeBaseSeeder extends Seeder
{
    public function run(): void
    {
        $chunks = self::getChunksData();

        foreach ($chunks as $chunk) {
            KnowledgeChunk::query()->updateOrCreate(
                ['title' => $chunk['title']],
                $chunk
            );
        }
    }

    /**
     * Return the 10 dense agronomic knowledge chunks from Embrapa, FAO and Wikipedia.
     *
     * @return list<array{source: 'Embrapa'|'FAO'|'Wikipedia', title: string, topic: string, content: string, keywords: list<string>}>
     */
    public static function getChunksData(): array
    {
        return [
            [
                'source' => 'Embrapa',
                'title' => 'Embrapa Cerrados — Correção da Acidez do Solo, Calagem, Gessagem e Disponibilidade de Nutrientes',
                'topic' => 'Físico-Química do Solo',
                'content' => 'Em solos tropicais fortemente intemperizados (Latossolos e Argissolos), valores de pH em CaCl2 inferiores a 4,8 indicam acidez ativa elevada e predominância de alumínio trocável (Al3+) tóxico, que inibe a divisão celular nos meristemas radiculares (raízes curtas, grossas e quebradiças) e promove a precipitação do fósforo lábil na forma de fosfatos de ferro e alumínio de baixa solubilidade. A calagem com calcário dolomítico (CaCO3 + MgCO3, relação Ca:Mg entre 3:1 e 4:1) visa elevar a saturação por bases (V%) para 50% a 70% conforme a exigência da cultura, neutralizar o Al3+ (saturação por alumínio m% < 10%) e situar o pH em CaCl2 na faixa ideal de 5,5 a 6,0. Nessa faixa, maximiza-se a disponibilidade de macronutrientes (N, P, K, Ca, Mg, S), eleva-se a CTC efetiva (dependente de pH nas cargas variáveis dos coloides orgânicos e caulinita) e garante-se a solubilidade do Molibdênio (MoO4^2-), micronutriente indispensável à redutase do nitrato e à nitrogenase. A gessagem agrícola (CaSO4.2H2O) atua de forma complementar na subsuperfície (20–60 cm), fornecendo Ca2+ e reduzindo a atividade do Al3+ em profundidade, aprofundando o sistema radicular contra veranicos.',
                'keywords' => [
                    'ph', 'acidez', 'calagem', 'calcário', 'alumínio', 'al3+', 'saturação por bases',
                    'v%', 'fósforo', 'cálcio', 'magnésio', 'molibdênio', 'gessagem', 'ctc', 'latossolo',
                ],
            ],
            [
                'source' => 'Embrapa',
                'title' => 'Embrapa Agrobiologia — Fixação Biológica de Nitrogênio (FBN) e Adubação Verde com Leguminosas',
                'topic' => 'Rotação de Culturas',
                'content' => 'A Fixação Biológica de Nitrogênio (FBN) mediada pela simbiose entre bactérias diazotróficas (gêneros Bradyrhizobium, Rhizobium e Azospirillum) e leguminosas permite aportes de 80 a 300 kg/ha/ano de N, substituindo fertilizantes nitrogenados sintéticos de alta pegada de carbono. O complexo enzimático da nitrogenase exige alto investimento energético (ATP), proteção contra o O2 livre via leghemoglobina nos nódulos e disponibilidade adequada de Fósforo (ATP), Cobalto (síntese de cobalamina/vitamina B12 no bacterioide) e Molibdênio (cofator FeMoCo da nitrogenase). Sob pH em CaCl2 < 5,0, o Molibdênio torna-se fortemente adsorvido aos óxidos de ferro e alumínio, inviabilizando a FBN eficiente mesmo com inoculação. Na adubação verde e consórcios conservacionistas, destacam-se Crotalaria juncea (alto aporte de biomassa e supressão de nematoides de galha e cisto), Crotalaria spectabilis e Crotalaria ochroleuca (rústica em solos ácidos e arenosos), Cajanus cajan (Feijão-guandu, com raiz pivotante agressiva capaz de romper camadas adensadas e ciclar P) e Mucuna pruriens (Mucuna-preta).',
                'keywords' => [
                    'fbn', 'fixação biológica de nitrogênio', 'baixa fixação de n', 'nitrogênio',
                    'bradyrhizobium', 'leguminosas', 'crotalária', 'crotalaria juncea', 'feijão-guandu',
                    'cajanus cajan', 'molibdênio', 'cobalto', 'adubação verde', 'inoculação', 'ph',
                ],
            ],
            [
                'source' => 'FAO',
                'title' => 'FAO Soils Bulletin — Dinâmica da Matéria Orgânica do Solo (MOS), Relação C/N e Sistema Plantio Direto (SPD)',
                'topic' => 'Físico-Química do Solo',
                'content' => 'Nos solos tropicais e subtropicais, a Matéria Orgânica do Solo (MOS) responde por 70% a 90% da Capacidade de Troca Catiônica (CTC) total, além de governar a agregação física, a estabilidade de macroagregados em água, a infiltração hídrica e a complexação de Al3+ livre por ácidos húmicos e fúlvicos. Teores de MOS abaixo de 1,5% em solos arenosos ou 2,0% em solos argilosos indicam degradação edáfica severa. O manejo sustentável via Sistema Plantio Direto (SPD) exige três pilares inegociáveis: ausência de revolvimento do solo, cobertura permanente (mínimo de 6 a 10 t/ha/ano de matéria seca) e rotação/consórcio de culturas. A velocidade de decomposição da palhada depende da relação Carbono/Nitrogênio (C/N): leguminosas possuem relação C/N baixa (12:1 a 20:1), promovendo rápida mineralização e liberação de N para a cultura sucessora, mas baixa persistência física sobre o solo; já gramíneas forrageiras (Urochloa ruziziensis, Urochloa brizantha, Panicum maximum, Pennisetum glaucum / Milheto) possuem relação C/N alta (35:1 a 60:1) e teores elevados de lignina, garantindo cobertura duradoura contra o impacto da gota de chuva e redução da temperatura superficial do solo.',
                'keywords' => [
                    'mos', 'matéria orgânica', 'relação c/n', 'plantio direto', 'spd', 'palhada',
                    'baixa palhada', 'ctc', 'urochloa', 'braquiária', 'milheto', 'cobertura do solo',
                    'agregação', 'carbono',
                ],
            ],
            [
                'source' => 'Embrapa',
                'title' => 'Embrapa Solos — Manejo Integrado de Microbacias Hidrográficas, Terraceamento e Controle da Erosão Hídrica',
                'topic' => 'Manejo de Bacias',
                'content' => 'A microbacia hidrográfica constitui a unidade geomorfológica e hidrológica fundamental para o planejamento conservacionista. A erosão laminar remove seletivamente a fração coloidal (argila e húmus) rica em nutrientes da camada superficial (0–10 cm), enquanto a erosão em sulcos e voçorocas decorre da concentração da enxurrada com energia cinética superior à tensão crítica de cisalhamento do solo. O controle integrado associa práticas vegetativas, edáficas e mecânicas: (1) Em relevo suave-ondulado (3–8% de declividade), adota-se semeadura em contorno (curvas de nível), terraços de base larga em nível (em solos permeáveis como Latossolos) para interceptar o comprimento de rampa e forçar a infiltração, além de bacias de captação (barraginhas) nas estradas rurais; (2) Em encostas íngremes (>15% de declividade) ou solos com gradiente textural (Argissolos/Cambissolos), onde a infiltração excessiva pode deflagrar escorregamentos translacionais, utilizam-se terraços em gradiente (com canais escoadouros vegetados), cordões de contorno vegetados com Capim-vetiver (Chrysopogon zizanioides), faixas de retenção e Sistemas Agroflorestais (SAFs) estratificados.',
                'keywords' => [
                    'erosão laminar', 'erosão', 'microbacia', 'bacia', 'terraceamento', 'curvas de nível',
                    'declividade', 'encosta', 'meia encosta', 'barraginhas', 'escorregamento', 'enxurrada',
                    'vetiver', 'infiltração',
                ],
            ],
            [
                'source' => 'Embrapa',
                'title' => 'Embrapa Cerrados — Edafoclimatologia do Bioma Cerrado: Latossolos, Construção de Perfil e Sistemas ILPF',
                'topic' => 'Biomas',
                'content' => 'O Bioma Cerrado caracteriza-se por clima tropical estacional (Aw de Köppen), com precipitação média de 1.200 a 1.800 mm concentrada entre outubro e março, presença frequente de veranicos (15 a 25 dias sem chuva no verão) e estação seca pronunciada. Predominam Latossolos Vermelhos e Vermelho-Amarelos (~46% da área), profundos, bem drenados, com estrutura granular muito pequena (aspecto de pó de café), porém quimicamente distróficos ou álicos (pH em CaCl2 entre 4,2 e 4,9; alta saturação por alumínio; baixa CTC e forte adsorção específica de fósforo nos sesquióxidos de Fe e Al). A monocultura sucessiva de soja com preparo convencional ou plantio direto mal conduzido (sem rotação) degrada a estrutura física, forma camada compactada subsuperficial (10–25 cm), reduz a MOS abaixo de 1,8% e acelera a erosão laminar em chapadas e meias-encostas. A recuperação preconizada pela Embrapa baseia-se na construção química e biológica do perfil (calagem + gessagem + fosfatagem corretiva), Sistema Santa Fé (consórcio Milho ou Sorgo + Urochloa ruziziensis na segunda safra) e Integração Lavoura-Pecuária-Floresta (ILPF).',
                'keywords' => [
                    'cerrado', 'latossolo', 'soja', 'monocultura', 'veranico', 'ilpf', 'sistema santa fé',
                    'chapada', 'meia encosta', 'argilosa', 'acidez', 'fósforo', 'urochloa ruziziensis',
                ],
            ],
            [
                'source' => 'Wikipedia',
                'title' => 'Wikipedia (Ciências Agrárias) — Bioma Mata Atlântica: Argissolos em Relevo Declivoso e Agroecologia de Encostas',
                'topic' => 'Biomas',
                'content' => 'No domínio morfoclimático dos "Mares de Morros" da Mata Atlântica, predominam relevos forte-ondulados a montanhosos (declividades frequentemente superiores a 15–25%) associados a Argissolos Vermelho-Amarelos e Cambissolos Háplicos sob regime pluviométrico intenso. Os Argissolos apresentam horizonte diagnóstico B textural (Bt), caracterizado pelo acúmulo subsuperficial de argila translocada (eluviação/iluviação). Essa descontinuidade hidráulica entre o horizonte A (mais arenoso e permeável) e o horizonte Bt (mais argiloso, adensado e de baixa condutividade hidráulica saturada) gera fluxo lateral subsuperficial e saturação rápida da camada superficial durante chuvas torrenciais, tornando o solo altamente vulnerável à erosão hídrica severa, sulcos, ravinas e movimentos de massa (escorregamentos). Pastagens degradadas de Brachiaria decumbens com superpastejo e trilhos de gado agravam a compactação superficial e o escoamento superficial direto para os fundos de vale. A reconversão sustentável exige Sistemas Silvipastoris com piqueteamento rotacionado em nível, Sistemas Agroflorestais (SAFs) biodiversos, adubação verde perene (Arachis pintoi / Amendoim-forrageiro, Flemingia macrophylla, Tithonia diversifolia) e vedação rigorosa dos terços superiores de morro.',
                'keywords' => [
                    'mata atlântica', 'argissolo', 'cambissolo', 'encosta forte', 'declividade',
                    'pastagem degradada', 'escorregamento', 'erosão laminar', 'compactação',
                    'saf', 'silvipastoril', 'arachis pintoi', 'amendoim-forrageiro',
                ],
            ],
            [
                'source' => 'Embrapa',
                'title' => 'Embrapa — Manejo Conservacionista de Solos Arenosos e Frágeis (Neossolos Quartzarênicos)',
                'topic' => 'Físico-Química do Solo',
                'content' => 'Os Neossolos Quartzarênicos (solos de textura arenosa, com menos de 15% de argila e mais de 80% de areia quartzosa até 150 cm de profundidade) apresentam fragilidade pedológica extrema: estrutura em grãos simples sem agregação natural, baixíssima Capacidade de Água Disponível (CAD < 0,6 mm/cm), CTC frequentemente inferior a 3,0 cmolc/dm3 (totalmente dependente do carbono orgânico) e elevada taxa de mineralização/oxidação da matéria orgânica (MOS frequentemente < 1,0%). Nesses solos, a calagem deve ser parcelada e moderada (visando V% em torno de 50%) para evitar supercalagem e deficiência induzida de micronutrientes catiônicos (Zn, Mn, Cu, Fe, B), e a adubação nitrogenada e potássica sofre intensa perda por lixiviação no perfil. A transição agroecológica e produtiva em solos arenosos depende vitalmente do aporte contínuo de raízes finas e biomassa recalcitrante via consórcio de gramíneas de sistema radicular fasciculado profundo (Milheto Pennisetum glaucum, Urochloa brizantha cv. Marandu/Piatã, Panicum maximum) com leguminosas rústicas fixadoras de N (Crotalaria ochroleuca, Feijão-caupi Vigna unguiculata, Guandu-anão), criando uma "esponja biológica" que eleva a retenção hídrica e estabiliza a FBN.',
                'keywords' => [
                    'arenosa', 'arenoso', 'neossolo quartzarênico', 'neossolo', 'baixa retenção hídrica',
                    'lixiviação', 'transição agroecológica', 'milheto', 'crotalaria ochroleuca',
                    'feijão-caupi', 'mos', 'baixa palhada', 'baixa fixação de n',
                ],
            ],
            [
                'source' => 'Wikipedia',
                'title' => 'Wikipedia (Física do Solo) — Compactação do Solo, Resistência à Penetração e Descompactação Biológica ("Biodrilling")',
                'topic' => 'Rotação de Culturas',
                'content' => 'A compactação antrópica do solo — causada pelo tráfego de máquinas agrícolas em condições de umidade inadequada (acima do limite de plasticidade) ou pelo pisoteio animal intensivo — reduz a macroporosidade de aeração abaixo do limite crítico de 10% (0,10 m3/m3) e eleva a Resistência Mecânica do Solo à Penetração (RP) acima de 2,0 a 2,5 MPa. Isso restringe severamente a difusão de oxigênio (O2) no espaço poroso, inibe a respiração radicular e dos bacterioides nos nódulos de FBN, favorece a desnitrificação anaeróbia (perda de N na forma de N2O) e reduz drasticamente a taxa de infiltração básica de água, convertendo chuva em enxurrada erosiva. Em vez da subsolagem mecânica isolada (cujo efeito é efêmero se não houver estabilização biológica dos poros), preconiza-se a descompactação biológica ("biodrilling"): cultivo de espécies com raízes pivotantes vigorosas como Nabo-forrageiro (Raphanus sativus var. oleiferus,no outono/inverno) e Feijão-guandu (Cajanus cajan, na primavera/verão) consorciadas com gramíneas formadoras de bioporos contínuos e exsudatos radiculares agregantes (glomalina produzida por fungos micorrízicos arbusculares).',
                'keywords' => [
                    'compactação', 'descompactação', 'física do solo', 'macroporosidade', 'infiltração',
                    'nabo-forrageiro', 'raphanus sativus', 'feijão-guandu', 'cajanus cajan', 'bioporos',
                    'micorrizas', 'glomalina', 'raízes',
                ],
            ],
            [
                'source' => 'FAO',
                'title' => 'FAO Watershed Management — Proteção Hidroambiental de Nascentes, Zonas Ripárias e Controle de Assoreamento',
                'topic' => 'Manejo de Bacias',
                'content' => 'O assoreamento de nascentes, olhos d’água e cursos hídricos de primeira ordem em bacias agrícolas decorre do transporte de sedimentos erodidos das vertentes desprotegidas e da degradação das Áreas de Preservação Permanente (APPs) e matas ciliares. Hidrológica e biogeoquimicamente, a zona ripária atua como filtro tampão ("riparian buffer strip"): sua serrapilheira e malha radicular densa reduzem a velocidade do escoamento superficial, promovem a deposição mecânica de partículas de areia e silte, retêm até 80% do fósforo particulado adsorvido aos coloides e estimulam a remoção biológica de nitrato (NO3-) das águas subsuperficiais. A recuperação hidroambiental exige o isolamento imediato da nascente contra o acesso de animais (cercamento de no mínimo 50 metros de raio), reconformação de taludes com técnicas de bioengenharia de solos (retentores de sedimento em fibra vegetal, paliçadas de bambu e plantio de gramíneas em faixas), enriquecimento florestal com espécies nativas pioneiras e secundárias adaptadas à saturação hídrica (Fundo de Vale / Várzea) e recarga de lençol freático no topo de chapada e meia-encosta.',
                'keywords' => [
                    'assoreamento de nascente', 'nascente', 'assoreamento', 'mata ciliar', 'zona ripária',
                    'fundo de vale', 'várzea', 'água', 'sedimentos', 'bioengenharia', 'bacia', 'app',
                ],
            ],
            [
                'source' => 'Embrapa',
                'title' => 'Embrapa Semiárido & Pecuária Sul — Manejo Edafoclimático na Caatinga, Pampa, Amazônia e Pantanal',
                'topic' => 'Biomas',
                'content' => 'A diversidade edafoclimática dos biomas brasileiros exige adaptações específicas de manejo do solo e da água: (1) Caatinga (Semiárido, clima BSh): predominam solos rasos (Neossolos Litólicos, Luvissolos Crômicos e Planossolos Háplicos) com déficit hídrico severo e risco de salinização/sodificação; o manejo prioriza tecnologias sociais e agroecológicas de convivência com o semiárido, como barragens subterrâneas, camalhões em nível, cobertura morta (mulching), sistemas agroflorestais xerófilos (Caatinga produtiva com Palma-forrageira Opuntia/Nopalea, Gliricídia Gliricidia sepium, Leucena e Umbuzeiro); (2) Pampa: campos sulinos subtropicais sujeitos à arenização em solos derivados de arenitos frágeis no sudoeste gaúcho e ao superpastejo; recomenda-se ajuste de carga animal, diferimento de pastagens nativas e sobressemeadura de forrageiras hibernais (Azevém Lolium multiflorum, Trevo-branco Trifolium repens, Cornichão Lotus corniculatus); (3) Amazônia: Latossolos e Argissolos sob alta pluviosidade equatorial dependentes da ciclagem rápida de nutrientes da biomassa florestal, onde o uso de SAFs multiestratos (Cacau, Cupuaçu, Açaí, Castanheira, Inga edulis) e Biochar ("Terra Preta de Índio") previne a lixiviação; (4) Pantanal: planície aluvial sujeita a pulsos sazonais de inundação (Fundo de Vale/Várzea), demandando manejo conservacionista de pastagens nativas sem supressão de cordilheiras e capões.',
                'keywords' => [
                    'caatinga', 'pampa', 'amazônia', 'pantanal', 'semiárido', 'gliricídia',
                    'palma-forrageira', 'azevém', 'trevo', 'inga edulis', 'biochar', 'várzea',
                    'arenização', 'salinização',
                ],
            ],
        ];
    }
}
