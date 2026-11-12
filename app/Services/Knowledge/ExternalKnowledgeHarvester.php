<?php

namespace App\Services\Knowledge;

use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ExternalKnowledgeHarvester
{
    protected const WIKIPEDIA_API_TEMPLATE = 'https://%s.wikipedia.org/w/api.php';

    /**
     * Feeds RSS 2.0 oficiais dos repositórios científicos da Embrapa (Infoteca-e e ALICE).
     *
     * @var list<string>
     */
    protected const EMBRAPA_RSS_FEEDS = [
        'https://www.infoteca.cnptia.embrapa.br/infoteca/feed/rss_2.0/site',
        'https://www.alice.cnptia.embrapa.br/alice/feed/rss_2.0/site',
    ];

    protected const USER_AGENT = 'AgroSoloAI-KnowledgeHarvester/1.0 (https://localhost; dev@agrosolo.local)';

    /**
     * Tópicos técnicos padrão de Ciência do Solo e Agronomia para colheita automática na Wikipedia.
     *
     * @var array<string, string>
     */
    public const DEFAULT_WIKIPEDIA_TOPICS = [
        'Pedologia' => 'Físico-Química do Solo',
        'Latossolo' => 'Classificação de Solos',
        'Argissolo' => 'Classificação de Solos',
        'Neossolo' => 'Classificação de Solos',
        'Calagem' => 'Fertilidade do Solo e Nutrição de Plantas',
        'Capacidade de troca catiônica' => 'Físico-Química do Solo',
        'Fixação biológica de nitrogênio' => 'Rotação de Culturas',
        'Plantio direto' => 'Manejo Sustentável',
        'Adubação verde' => 'Rotação de Culturas',
        'Erosão' => 'Manejo de Bacias',
    ];

    /**
     * Fetch full plain-text articles from Wikipedia API by exact titles in any language.
     *
     * @param  array<string, string>  $topicsMap  Map of [Article Title => Topic Category]
     * @return list<array{
     *   source: 'Wikipedia',
     *   title: string,
     *   topic: string,
     *   content: string,
     *   keywords: list<string>
     * }>
     */
    public function fetchWikipediaArticles(
        array $topicsMap = self::DEFAULT_WIKIPEDIA_TOPICS,
        string $lang = 'pt',
        bool $fallbackToSearch = true
    ): array {
        $harvested = [];
        $seenTitles = [];
        $safeLang = preg_replace('/[^a-z\-]/i', '', Str::lower($lang)) ?: 'pt';
        $apiUrl = sprintf(self::WIKIPEDIA_API_TEMPLATE, $safeLang);

        foreach ($topicsMap as $articleTitle => $topicCategory) {
            $matchedForThisTopic = false;

            try {
                $response = $this->httpClient()
                    ->get($apiUrl, [
                        'action' => 'query',
                        'format' => 'json',
                        'prop' => 'extracts',
                        'explaintext' => 1,
                        'redirects' => 1,
                        'titles' => $articleTitle,
                    ]);

                if ($response->successful()) {
                    /** @var array<string, mixed> $pages */
                    $pages = $response->json('query.pages', []);

                    foreach ($pages as $pageId => $pageData) {
                        if ((int) $pageId < 0 || empty($pageData['extract'])) {
                            continue;
                        }

                        $resolvedTitle = (string) ($pageData['title'] ?? $articleTitle);
                        $extract = trim((string) $pageData['extract']);

                        if (mb_strlen($extract) < 120) {
                            continue;
                        }

                        $matchedForThisTopic = true;
                        $docTitle = "Wikipedia — {$resolvedTitle}";
                        if (isset($seenTitles[$docTitle])) {
                            continue;
                        }
                        $seenTitles[$docTitle] = true;

                        $harvested[] = [
                            'source' => 'Wikipedia',
                            'title' => $docTitle,
                            'topic' => $topicCategory,
                            'content' => $extract,
                            'keywords' => [Str::lower($resolvedTitle), Str::lower($topicCategory)],
                        ];
                    }
                }
            } catch (Throwable) {
                // Handled below by search fallback if enabled
            }

            if (! $matchedForThisTopic && $fallbackToSearch) {
                $fallbackDocs = $this->searchAndFetchWikipedia($articleTitle, 1, $topicCategory, $safeLang);
                foreach ($fallbackDocs as $doc) {
                    if (isset($seenTitles[$doc['title']])) {
                        continue;
                    }
                    $seenTitles[$doc['title']] = true;
                    $harvested[] = $doc;
                }
            }
        }

        return $harvested;
    }

    /**
     * Search Wikipedia for any free-form query in any domain and fetch matching articles.
     *
     * @return list<array{
     *   source: 'Wikipedia',
     *   title: string,
     *   topic: string,
     *   content: string,
     *   keywords: list<string>
     * }>
     */
    public function searchAndFetchWikipedia(
        string $query,
        int $limit = 5,
        string $topic = 'Conhecimento Geral',
        string $lang = 'pt'
    ): array {
        $safeLang = preg_replace('/[^a-z\-]/i', '', Str::lower($lang)) ?: 'pt';
        $apiUrl = sprintf(self::WIKIPEDIA_API_TEMPLATE, $safeLang);

        try {
            $searchResponse = $this->httpClient()
                ->get($apiUrl, [
                    'action' => 'query',
                    'format' => 'json',
                    'list' => 'search',
                    'srsearch' => $query,
                    'srlimit' => max(1, min(15, $limit)),
                ]);

            if (! $searchResponse->successful()) {
                return [];
            }

            /** @var list<array{title?: string}> $searchResults */
            $searchResults = $searchResponse->json('query.search', []);
            $topicsMap = [];

            foreach ($searchResults as $item) {
                if (! empty($item['title'])) {
                    $topicsMap[(string) $item['title']] = $topic;
                }
            }

            if (empty($topicsMap)) {
                return [];
            }

            return $this->fetchWikipediaArticles($topicsMap, $safeLang, false);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Fetch and extract clean readable text from any public web page (HTML, Markdown, or plain text).
     *
     * @return array{
     *   source: string,
     *   title: string,
     *   topic: string,
     *   content: string,
     *   keywords: list<string>
     * }|null
     */
    public function fetchWebPage(
        string $url,
        ?string $source = null,
        string $topic = 'Conhecimento Web',
        ?string $customTitle = null
    ): ?array {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            $response = $this->httpClient('text/html, text/plain, application/xhtml+xml')
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $body = trim($response->body());
            if ($body === '') {
                return null;
            }

            $contentType = Str::lower((string) $response->header('Content-Type'));
            $host = (string) (parse_url($url, PHP_URL_HOST) ?? 'Web');
            $derivedSource = $source ?: Str::ucfirst(preg_replace('/^www\./i', '', $host) ?? 'Web');

            $extractedTitle = $customTitle ?: $host;
            $cleanContent = $body;

            if ($contentType === '' || Str::contains($contentType, ['html', 'xhtml'])) {
                [$parsedTitle, $parsedText] = $this->extractTextFromHtml($body);
                if (! $customTitle && $parsedTitle !== '') {
                    $extractedTitle = $parsedTitle;
                }
                $cleanContent = $parsedText;
            }

            if (mb_strlen($cleanContent) < 100) {
                return null;
            }

            return [
                'source' => Str::limit($derivedSource, 60, ''),
                'title' => Str::limit($extractedTitle, 180, ''),
                'topic' => $topic,
                'content' => "{$cleanContent}\n\nFonte URL: {$url}",
                'keywords' => [Str::lower($topic), Str::lower($derivedSource)],
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Parse main readable text and page title from an HTML document, removing scripts, navbars and footers.
     *
     * @return array{0: string, 1: string}
     */
    protected function extractTextFromHtml(string $html): array
    {
        $prevUseErrors = libxml_use_internal_errors(true);

        $dom = new DOMDocument;
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);

        $xpath = new DOMXPath($dom);

        // Extract <title>
        $titleNodes = $xpath->query('//title');
        $title = $titleNodes && $titleNodes->length > 0
            ? trim(html_entity_decode((string) $titleNodes->item(0)?->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            : '';

        // Strip non-content tags
        $noiseNodes = $xpath->query('//script|//style|//noscript|//svg|//nav|//header|//footer|//aside|//form');
        if ($noiseNodes) {
            foreach ($noiseNodes as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        // Prefer <main> or <article>, fall back to <body>
        $contentNode = $xpath->query('//main|//article')->item(0)
            ?? $xpath->query('//body')->item(0)
            ?? $dom->documentElement;

        $rawHtmlSnippet = $contentNode ? $dom->saveHTML($contentNode) : '';
        libxml_clear_errors();
        libxml_use_internal_errors($prevUseErrors);

        if (! is_string($rawHtmlSnippet) || $rawHtmlSnippet === '') {
            return [$title, ''];
        }

        // Insert newlines before block tags so words do not concatenate across elements
        $withBreaks = preg_replace('/<\/?(?:p|div|br|li|tr|h[1-6]|section|article|blockquote)[^>]*>/i', "\n", $rawHtmlSnippet) ?? $rawHtmlSnippet;
        $plain = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = preg_replace('/[ \t]+/u', ' ', $plain) ?? $plain;
        $plain = preg_replace('/(\r\n|\r|\n){3,}/u', "\n\n", $plain) ?? $plain;

        return [$title, trim($plain)];
    }

    /**
     * Fetch public scientific publications from Embrapa's official Infoteca-e and ALICE RSS 2.0 feeds.
     *
     * @return list<array{
     *   source: 'Embrapa',
     *   title: string,
     *   topic: string,
     *   content: string,
     *   keywords: list<string>
     * }>
     */
    public function fetchEmbrapaOpenRepository(int $limit = 5): array
    {
        $harvested = [];

        foreach (self::EMBRAPA_RSS_FEEDS as $feedUrl) {
            if (count($harvested) >= $limit) {
                break;
            }

            try {
                $response = $this->httpClient('application/rss+xml, application/xml, text/xml')
                    ->get($feedUrl);

                if (! $response->successful()) {
                    continue;
                }

                $xmlContent = $response->body();
                if ($xmlContent === '') {
                    continue;
                }

                $xml = @simplexml_load_string($xmlContent);
                if ($xml === false || ! isset($xml->channel->item)) {
                    continue;
                }

                $repoLabel = Str::contains($feedUrl, 'infoteca') ? 'Embrapa Infoteca-e' : 'Embrapa ALICE';

                foreach ($xml->channel->item as $item) {
                    if (count($harvested) >= $limit) {
                        break;
                    }

                    $title = trim(strip_tags((string) ($item->title ?? '')));
                    $description = trim(strip_tags((string) ($item->description ?? '')));
                    $link = trim((string) ($item->link ?? ''));

                    if ($title === '' || mb_strlen($description) < 100) {
                        continue;
                    }

                    $content = $link !== ''
                        ? "{$description}\n\nReferência Oficial: {$link}"
                        : $description;

                    $harvested[] = [
                        'source' => 'Embrapa',
                        'title' => "{$repoLabel} — ".Str::limit($title, 110),
                        'topic' => 'Pesquisa Científica Embrapa',
                        'content' => $content,
                        'keywords' => ['embrapa', Str::lower($repoLabel)],
                    ];
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $harvested;
    }

    /**
     * Build a resilient HTTP client that gracefully handles missing local CA bundles on Windows dev environments.
     */
    protected function httpClient(string $accept = 'application/json'): PendingRequest
    {
        $client = Http::withHeaders([
            'User-Agent' => self::USER_AGENT,
            'Accept' => $accept,
        ])->timeout(15);

        $caInfo = (string) ini_get('curl.cainfo');
        if ($caInfo === '' || ! file_exists($caInfo)) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }
}
