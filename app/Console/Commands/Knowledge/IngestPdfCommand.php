<?php

namespace App\Console\Commands\Knowledge;

use App\Actions\Knowledge\ChunkAndIndexDocumentAction;
use App\Models\KnowledgeChunk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;

class IngestPdfCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'knowledge:ingest-pdf 
                            {path=storage/app/knowledge/pdfs : Caminho do arquivo PDF ou pasta de PDFs}
                            {--source=PDF : Rótulo da fonte gravado no banco (ex: Embrapa, FAO, Receita Federal)}
                            {--category=Conhecimento Geral : Categoria/Tópico associado aos fragmentos}
                            {--words-per-chunk=350 : Quantidade aproximada de palavras por fragmento}
                            {--overlap=50 : Palavras sobrepostas entre fragmentos para não quebrar contexto}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extrai, fatia (chunking) e indexa automaticamente manuais e livros em PDF na base RAG local';

    /**
     * Execute the console command.
     */
    public function handle(ChunkAndIndexDocumentAction $chunkAndIndex): int
    {
        $rawPath = (string) $this->argument('path');
        $fullPath = Str::startsWith($rawPath, [DIRECTORY_SEPARATOR, ':', '/']) || preg_match('/^[A-Za-z]:/', $rawPath)
            ? $rawPath
            : base_path($rawPath);

        if (! File::exists($fullPath)) {
            $this->error("Arquivo ou diretório não encontrado: {$fullPath}");

            return self::FAILURE;
        }

        $pdfFiles = [];
        if (File::isDirectory($fullPath)) {
            $all = File::files($fullPath);
            foreach ($all as $f) {
                if (Str::lower($f->getExtension()) === 'pdf') {
                    $pdfFiles[] = $f->getRealPath();
                }
            }
        } elseif (Str::lower(pathinfo($fullPath, PATHINFO_EXTENSION)) === 'pdf') {
            $pdfFiles[] = $fullPath;
        }

        if (empty($pdfFiles)) {
            $this->warn("Nenhum arquivo PDF encontrado em: {$fullPath}");
            $this->line('👉 Coloque seus arquivos .pdf na pasta <comment>storage/app/knowledge/pdfs/</comment> e execute o comando novamente.');

            return self::SUCCESS;
        }

        $source = (string) $this->option('source');
        $defaultTopic = (string) $this->option('category');
        $wordsPerChunk = max(100, (int) $this->option('words-per-chunk'));
        $overlap = max(10, min(100, (int) $this->option('overlap')));

        $parser = new Parser;
        $this->info('Iniciando processamento de '.count($pdfFiles).' arquivo(s) PDF...');

        $totalChunksCreated = 0;
        $tableRows = [];

        foreach ($pdfFiles as $pdfPath) {
            $filename = pathinfo($pdfPath, PATHINFO_FILENAME);
            $cleanTitle = ucwords(str_replace(['_', '-'], ' ', $filename));

            $this->line("📄 Lendo PDF: <comment>{$filename}.pdf</comment>...");

            try {
                $pdf = $parser->parseFile($pdfPath);
                $rawText = $pdf->getText();
            } catch (\Throwable $e) {
                $this->error("Erro ao ler o PDF {$filename}: ".$e->getMessage());

                continue;
            }

            $indexedChunks = $chunkAndIndex->execute(
                rawText: $rawText,
                baseTitle: $cleanTitle,
                source: $source,
                topic: $defaultTopic,
                wordsPerChunk: $wordsPerChunk,
                overlap: $overlap
            );

            if (empty($indexedChunks)) {
                $this->warn("O PDF {$filename} não contém texto selecionável legível (pode ser imagem escaneada).");

                continue;
            }

            $this->line('   ➔ Extraídos '.count($indexedChunks).' fragmentos.');

            foreach ($indexedChunks as $item) {
                $totalChunksCreated++;
                $tableRows[] = [
                    $item['id'],
                    $item['source'],
                    Str::limit($item['title'], 42),
                    $item['keywords_count'].' tags',
                    $item['words_count'].' palavras',
                ];
            }
        }

        $this->newLine();
        $this->table(
            ['ID', 'Fonte', 'Título do Fragmento', 'Palavras-chave', 'Extensão'],
            $tableRows
        );

        $totalInDb = KnowledgeChunk::count();
        $this->info("✅ Ingestão de PDFs finalizada! {$totalChunksCreated} fragmentos criados/atualizados.");
        $this->line("📚 Base total de conhecimento RAG agora: <comment>{$totalInDb}</comment> fragmentos.");

        return self::SUCCESS;
    }
}
