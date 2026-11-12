<?php

namespace App\Console\Commands\Knowledge;

use App\Actions\Knowledge\ChunkAndIndexDocumentAction;
use App\Models\KnowledgeChunk;
use App\Services\Knowledge\ExternalKnowledgeHarvester;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class FetchKnowledgeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'knowledge:fetch
                            {--provider=all : Onde buscar na web (all, wikipedia, embrapa)}
                            {--url= : URL pública direta (artigo, documentação, lei) para baixar e indexar}
                            {--articles= : Títulos específicos da Wikipedia separados por vírgula (ex: "Direito tributário,CTN" ou "Latossolo,Calagem")}
                            {--search= : Termo livre para busca automática de artigos na Wikipedia}
                            {--source= : Rótulo da fonte gravado no banco (ex: "Planalto", "IEEE"; padrão automático pelo provedor/domínio)}
                            {--category= : Categoria/Tópico gravado nos fragmentos (ex: "Tributação", "Medicina Veterinária")}
                            {--title= : Título customizado (opcional, usado com --url)}
                            {--lang=pt : Idioma da Wikipedia (pt, en, es...)}
                            {--limit=5 : Quantidade máxima de artigos buscados por provedor}
                            {--words-per-chunk=350 : Palavras por fragmento fatiado}
                            {--overlap=50 : Palavras sobrepostas entre fragmentos}
                            {--dry-run : Apenas pré-visualiza os documentos encontrados sem gravar no banco}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Busca, higieniza, fatia e indexa automaticamente conteúdos da web (Wikipedia, URLs públicas ou Embrapa) na base RAG';

    /**
     * Execute the console command.
     */
    public function handle(
        ExternalKnowledgeHarvester $harvester,
        ChunkAndIndexDocumentAction $chunkAndIndex
    ): int {
        $provider = Str::lower(trim((string) $this->option('provider')));
        $directUrl = trim((string) $this->option('url'));
        $articlesOption = trim((string) $this->option('articles'));
        $searchQuery = trim((string) $this->option('search'));
        $customSource = trim((string) $this->option('source'));
        $customCategory = trim((string) $this->option('category'));
        $customTitle = trim((string) $this->option('title'));
        $lang = trim((string) $this->option('lang')) ?: 'pt';
        $limit = max(1, min(20, (int) $this->option('limit')));
        $wordsPerChunk = max(100, (int) $this->option('words-per-chunk'));
        $overlap = max(10, min(100, (int) $this->option('overlap')));

        if ($directUrl === '' && ! in_array($provider, ['all', 'wikipedia', 'embrapa'], true)) {
            $this->error("Provedor inválido: '{$provider}'. Use: all, wikipedia ou embrapa (ou passe --url= para uma página web direta).");

            return self::FAILURE;
        }

        if (Str::lower($customSource) === 'all') {
            $this->error('Valor inválido para --source="all". Use --provider=all para escolher o provedor; --source define apenas o rótulo da fonte salvo no banco (ex: "Planalto", "IEEE").');

            return self::FAILURE;
        }

        $this->info('🌐 Iniciando colheita automatizada de conhecimento (ETL RAG)...');

        $documentsToProcess = [];

        // 1. Direct URL Harvesting (any public page: docs, legislation, articles)
        if ($directUrl !== '') {
            $urlSource = $customSource !== '' ? $customSource : null;
            $urlCategory = $customCategory !== '' ? $customCategory : 'Conhecimento Web';
            $this->line("🔗 Baixando conteúdo web de: <comment>{$directUrl}</comment>...");

            $webDoc = $harvester->fetchWebPage(
                url: $directUrl,
                source: $urlSource,
                topic: $urlCategory,
                customTitle: $customTitle !== '' ? $customTitle : null
            );

            if ($webDoc !== null) {
                $documentsToProcess[] = $webDoc;
            }
        } else {
            // 2. Wikipedia Harvesting (domain-agnostic)
            if (in_array($provider, ['all', 'wikipedia'], true)) {
                $resolvedCategory = $customCategory !== '' ? $customCategory : 'Conhecimento Geral';

                if ($searchQuery !== '') {
                    $this->line("🔍 Pesquisando na Wikipedia ({$lang}) por: <comment>\"{$searchQuery}\"</comment> (categoria: {$resolvedCategory}, limite: {$limit})...");
                    $wikiDocs = $harvester->searchAndFetchWikipedia($searchQuery, $limit, $resolvedCategory, $lang);
                    $documentsToProcess = array_merge($documentsToProcess, $wikiDocs);
                } elseif ($articlesOption !== '') {
                    $articleList = array_filter(array_map('trim', explode(',', $articlesOption)));
                    $articlesMap = [];
                    foreach ($articleList as $title) {
                        $articlesMap[$title] = $resolvedCategory;
                    }
                    $this->line('📘 Buscando '.count($articlesMap)." artigo(s) na Wikipedia ({$lang}) em \"{$resolvedCategory}\"...");
                    $wikiDocs = $harvester->fetchWikipediaArticles($articlesMap, $lang);
                    $documentsToProcess = array_merge($documentsToProcess, $wikiDocs);
                } else {
                    $defaultSlice = array_slice(
                        ExternalKnowledgeHarvester::DEFAULT_WIKIPEDIA_TOPICS,
                        0,
                        $limit,
                        true
                    );
                    $this->line('📘 Buscando '.count($defaultSlice).' artigos padrão na API da Wikipedia...');
                    $wikiDocs = $harvester->fetchWikipediaArticles($defaultSlice, $lang);
                    $documentsToProcess = array_merge($documentsToProcess, $wikiDocs);
                }
            }

            // 3. Embrapa ALICE / Infoteca-e Open Repository Harvesting (latest RSS items; does not support query/articles filter)
            if (in_array($provider, ['all', 'embrapa'], true)) {
                if ($articlesOption !== '' || $searchQuery !== '') {
                    if ($provider === 'embrapa') {
                        $this->warn('ℹ️ Os feeds RSS da Embrapa não suportam filtro por --search ou --articles (listam apenas as publicações recentes). Use --provider=wikipedia para pesquisar por temas ou --url= para indexar uma página específica.');
                    }
                } else {
                    $this->line("🌾 Consultando repositório científico aberto Embrapa (RSS, limite: {$limit})...");
                    $embrapaDocs = $harvester->fetchEmbrapaOpenRepository($limit);
                    $documentsToProcess = array_merge($documentsToProcess, $embrapaDocs);
                }
            }
        }

        if ($customSource !== '' || $customCategory !== '') {
            $documentsToProcess = array_map(static function (array $doc) use ($customSource, $customCategory): array {
                if ($customSource !== '') {
                    $doc['source'] = $customSource;
                }
                if ($customCategory !== '') {
                    $doc['topic'] = $customCategory;
                }

                return $doc;
            }, $documentsToProcess);
        }

        if (empty($documentsToProcess)) {
            $this->warn('Nenhum documento externo pôde ser coletado no momento (verifique a conexão ou os termos de busca).');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $previewRows = [];
            foreach ($documentsToProcess as $doc) {
                $cleaned = $chunkAndIndex->sanitizeText($doc['content']);
                $wordCount = str_word_count($cleaned);
                $estimatedChunks = count($chunkAndIndex->splitIntoOverlappingChunks($cleaned, $wordsPerChunk, $overlap));

                $previewRows[] = [
                    $doc['source'],
                    Str::limit($doc['title'], 50),
                    Str::limit($doc['topic'], 28),
                    "{$wordCount} palavras",
                    "{$estimatedChunks} chunks",
                ];
            }

            $this->newLine();
            $this->table(
                ['Fonte', 'Título Encontrado', 'Tópico', 'Extensão', 'Chunks Previstos'],
                $previewRows
            );
            $this->info('👀 Modo --dry-run ativo: nenhum dado foi gravado no banco.');

            return self::SUCCESS;
        }

        $this->info('✂️ Processando, higienizando e fatiando '.count($documentsToProcess).' documento(s) coletado(s)...');

        $tableRows = [];
        $totalChunksSaved = 0;

        foreach ($documentsToProcess as $doc) {
            $indexedChunks = $chunkAndIndex->execute(
                rawText: $doc['content'],
                baseTitle: $doc['title'],
                source: $doc['source'],
                topic: $doc['topic'],
                wordsPerChunk: $wordsPerChunk,
                overlap: $overlap,
                extraKeywords: $doc['keywords']
            );

            foreach ($indexedChunks as $chunk) {
                $totalChunksSaved++;
                $tableRows[] = [
                    $chunk['id'],
                    $chunk['source'],
                    Str::limit($chunk['title'], 46),
                    Str::limit($chunk['topic'], 26),
                    $chunk['keywords_count'].' tags',
                    $chunk['words_count'].' palavras',
                ];
            }
        }

        $this->newLine();
        $this->table(
            ['ID', 'Fonte', 'Título do Fragmento', 'Tópico', 'Keywords', 'Extensão'],
            $tableRows
        );

        $totalInDb = KnowledgeChunk::count();
        $this->info("✅ Colheita concluída! {$totalChunksSaved} fragmentos indexados a partir de ".count($documentsToProcess).' documento(s).');
        $this->line("📚 Total de fragmentos técnicos na Base RAG agora: <comment>{$totalInDb}</comment>");

        return self::SUCCESS;
    }
}
