<?php

namespace App\Console\Commands\Knowledge;

use App\Models\KnowledgeChunk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class IngestKnowledgeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'knowledge:ingest 
                            {--path=storage/app/knowledge/markdown : Caminho relativo do diretório com manuais Markdown}
                            {--fresh : Limpa os chunks existentes antes de ingerir novos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ingere e indexa manuais técnicos em Markdown (com YAML frontmatter) na base RAG local';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $relPath = (string) $this->option('path');
        $fullPath = base_path($relPath);

        if (! File::isDirectory($fullPath)) {
            $this->error("Diretório não encontrado: {$fullPath}");

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            if ($this->confirm('Deseja realmente limpar toda a base de conhecimento existente antes de ingerir?', true)) {
                KnowledgeChunk::truncate();
                $this->warn('Base de conhecimento truncada.');
            }
        }

        $files = File::files($fullPath);
        $markdownFiles = array_filter(
            $files,
            fn (\SplFileInfo $f) => in_array(Str::lower($f->getExtension()), ['md', 'markdown', 'txt'], true)
        );

        if (empty($markdownFiles)) {
            $this->warn("Nenhum arquivo Markdown (.md) encontrado em {$fullPath}.");

            return self::SUCCESS;
        }

        $this->info("Iniciando ingestão de conhecimento a partir de: {$relPath}");

        $rows = [];
        $ingestedCount = 0;

        foreach ($markdownFiles as $file) {
            $rawContent = File::get($file->getRealPath());
            $parsed = $this->parseMarkdownFrontmatter($rawContent);

            if (! $parsed) {
                $this->warn("Ignorando {$file->getFilename()}: cabeçalho YAML/Frontmatter inválido ou ausente.");

                continue;
            }

            $source = trim((string) ($parsed['source'] ?? ''));
            if ($source === '') {
                $source = 'Markdown';
            }

            $chunk = KnowledgeChunk::updateOrCreate(
                ['title' => $parsed['title']],
                [
                    'source' => $source,
                    'topic' => $parsed['topic'] ?? 'Geral',
                    'content' => $parsed['content'],
                    'keywords' => $parsed['keywords'] ?? [],
                ]
            );

            $ingestedCount++;
            $wordCount = str_word_count($parsed['content']);

            $rows[] = [
                $chunk->id,
                $chunk->source,
                Str::limit($chunk->title, 40),
                $chunk->topic,
                count($chunk->keywords),
                "{$wordCount} palavras",
            ];
        }

        $this->newLine();
        $this->table(
            ['ID', 'Fonte', 'Título', 'Tópico', 'Keywords', 'Extensão'],
            $rows
        );

        $totalInDatabase = KnowledgeChunk::count();
        $this->info("✅ Ingestão concluída com sucesso! {$ingestedCount} manuais processados.");
        $this->line("📚 Total de fragmentos técnicos indexados no banco RAG: <comment>{$totalInDatabase}</comment>");

        return self::SUCCESS;
    }

    /**
     * Parse simple YAML frontmatter and markdown body.
     *
     * @return array{
     *   source: string,
     *   title: string,
     *   topic: string,
     *   keywords: list<string>,
     *   content: string
     * }|null
     */
    protected function parseMarkdownFrontmatter(string $text): ?array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);

        if (! preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $normalized, $matches)) {
            return null;
        }

        $yamlBlock = trim($matches[1]);
        $body = trim($matches[2]);

        $meta = [
            'source' => 'Embrapa',
            'title' => '',
            'topic' => 'Agronomia',
            'keywords' => [],
        ];

        $lines = explode("\n", $yamlBlock);
        $currentListKey = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || Str::startsWith($trimmed, '#')) {
                continue;
            }

            // Check if line is a list item: "- keyword"
            if (preg_match('/^-\s+(.*)$/', $trimmed, $mList) && $currentListKey) {
                $item = trim($mList[1], " \t\n\r\0\x0B\"'");
                if ($item !== '') {
                    $meta[$currentListKey][] = Str::lower($item);
                }

                continue;
            }

            // Check if key: value
            if (preg_match('/^([a-zA-Z0-9_-]+)\s*:\s*(.*)$/', $trimmed, $mKey)) {
                $key = Str::lower(trim($mKey[1]));
                $val = trim($mKey[2], " \t\n\r\0\x0B\"'");

                if ($val === '') {
                    // Start of a YAML array list
                    $currentListKey = $key;
                    if (! isset($meta[$currentListKey])) {
                        $meta[$currentListKey] = [];
                    }
                } else {
                    $currentListKey = null;
                    if ($key === 'keywords') {
                        $meta['keywords'] = array_map(
                            fn (string $k) => Str::lower(trim($k)),
                            explode(',', $val)
                        );
                    } else {
                        $meta[$key] = $val;
                    }
                }
            }
        }

        if (empty($meta['title']) || empty($body)) {
            return null;
        }

        return [
            'source' => (string) ($meta['source'] ?? 'Embrapa'),
            'title' => (string) $meta['title'],
            'topic' => (string) ($meta['topic'] ?? 'Agronomia'),
            'keywords' => array_values(array_unique($meta['keywords'] ?? [])),
            'content' => $body,
        ];
    }
}
