# Documentação dos Comandos de Conhecimento (RAG / ETL)

Este documento descreve os comandos Artisan responsáveis por coletar, processar, fatiar (*chunking*) e indexar documentos técnicos na tabela `knowledge_chunks` da base RAG local.

Todos os comandos ficam em [`app/Console/Commands/Knowledge`](../app/Console/Commands/Knowledge) e utilizam a infraestrutura agnóstica de domínio em [`ExternalKnowledgeHarvester.php`](../app/Services/Knowledge/ExternalKnowledgeHarvester.php) e [`ChunkAndIndexDocumentAction.php`](../app/Actions/Knowledge/ChunkAndIndexDocumentAction.php).

---

## Visão Geral Rápida

| Comando | Finalidade | Origem dos dados |
| :--- | :--- | :--- |
| `knowledge:fetch` | Coleta automática na web | Wikipedia (`--provider=wikipedia`), qualquer URL pública (`--url`) ou feeds RSS da Embrapa (`--provider=embrapa`) |
| `knowledge:ingest-pdf` | Extração e indexação de PDFs | Arquivos `.pdf` locais (padrão: `storage/app/knowledge/pdfs`) |
| `knowledge:ingest` | Indexação de manuais Markdown | Arquivos `.md`, `.markdown`, `.txt` com YAML frontmatter (padrão: `storage/app/knowledge/markdown`) |

Para listar todos os comandos e consultar a ajuda direto no terminal:

```powershell
php artisan list knowledge
php artisan help knowledge:fetch
```

---

## Regra Clara das Flags (1 flag = 1 função)

Para evitar ambiguidades, os comandos seguem exatamente a mesma convenção:

- **`--provider=`** define **onde buscar** na web (`all`, `wikipedia`, `embrapa`).
- **`--source=`** define **sempre** o rótulo da fonte salvo no banco e exibido na resposta da IA (ex.: `Wikipedia`, `Embrapa`, `Planalto`, `Receita Federal`, `IEEE`).
- **`--category=`** define **sempre** a categoria/tópico salvo no banco (ex.: `Tributação`, `Medicina Veterinária`, `Ciência do Solo`).
- **`--search=`** busca uma **frase curta** na Wikipedia.
- **`--articles=`** busca uma **lista de artigos** separados por vírgula na Wikipedia (com fallback automático caso o título exato não exista).
- **`--url=`** baixa e extrai texto limpo de **qualquer página web pública**.

---

## 1. `knowledge:fetch`

Arquivo: [`FetchKnowledgeCommand.php`](../app/Console/Commands/Knowledge/FetchKnowledgeCommand.php)

Busca conteúdos externos na internet, remove ruído HTML/rodapés da Wikipedia, fatia textos longos em blocos com sobreposição (*overlap*), extrai palavras-chave automaticamente e salva na base `knowledge_chunks`.

### Opções

| Opção | Padrão | Descrição |
| :--- | :--- | :--- |
| `--provider=` | `all` | Provedor externo consultado: `all`, `wikipedia` ou `embrapa`. |
| `--url=` | *(vazio)* | URL pública direta (`http`/`https`) de página HTML, documentação, artigo ou lei para baixar e indexar. |
| `--articles=` | *(vazio)* | Títulos específicos da Wikipedia separados por vírgula (ex.: `"Direito tributário,Código Tributário Nacional"`). |
| `--search=` | *(vazio)* | Frase livre de pesquisa na API da Wikipedia. |
| `--source=` | *(automático)* | Sobrescreve o rótulo de fonte salvo no banco (ex.: `Planalto`, `IEEE`, `CFMV`). |
| `--category=` | *(automático)* | Categoria gravada no campo `topic` (padrão: `"Conhecimento Web"` para `--url` e `"Conhecimento Geral"` para `--search`/`--articles`). |
| `--title=` | *(automático)* | Título customizado (opcional; usado principalmente com `--url`). |
| `--lang=` | `pt` | Idioma da Wikipedia (`pt`, `en`, `es`, etc.). |
| `--limit=` | `5` | Quantidade máxima de artigos por provedor (`1` a `20`). |
| `--words-per-chunk=` | `350` | Palavras aproximadas por fragmento (mínimo `100`). |
| `--overlap=` | `50` | Palavras repetidas entre fragmentos consecutivos (`10` a `100`). |
| `--dry-run` | `false` | Pré-visualiza títulos, extensão e quantidade de chunks **sem gravar** nada no banco. |

### Regras de execução
- **Modo `--url`:** baixa exclusivamente a URL informada via `ExternalKnowledgeHarvester::fetchWebPage()`.
- **Modo `--search` ou `--articles`:** consulta a Wikipedia no idioma `--lang` (os feeds RSS da Embrapa não suportam filtro por termo/título e exibem um aviso caso `--provider=embrapa` seja usado com essas flags).
- **Sem filtros (`php artisan knowledge:fetch`):** busca a lista padrão `DEFAULT_WIKIPEDIA_TOPICS` (até `--limit`) e os feeds RSS 2.0 públicos da Embrapa Infoteca-e e ALICE.

### Exemplos práticos

```powershell
# Coleta padrão (Wikipedia + Embrapa RSS)
php artisan knowledge:fetch --limit=5

# Pré-visualizar antes de gravar (recomendado antes de indexar buscas novas)
php artisan knowledge:fetch --provider=wikipedia --search="direito tributário" --category="Tributação" --dry-run

# Buscar artigos específicos por lista de títulos
php artisan knowledge:fetch --provider=wikipedia --articles="Direito tributário,Código Tributário Nacional" --category="Tributação"

# Busca em outras áreas (ex.: Medicina Veterinária e Engenharia Mecatrônica)
php artisan knowledge:fetch --provider=wikipedia --search="cinomose canina" --category="Medicina Veterinária" --limit=5
php artisan knowledge:fetch --provider=wikipedia --lang=en --articles="PID controller" --category="Mechatronics"

# Indexar diretamente uma página web pública (lei, documentação, artigo técnico)
php artisan knowledge:fetch --url="https://exemplo.gov.br/lei-tributaria" --source="Planalto" --category="Tributação"
```

---

## 2. `knowledge:ingest-pdf`

Arquivo: [`IngestPdfCommand.php`](../app/Console/Commands/Knowledge/IngestPdfCommand.php)

Extrai texto selecionável de arquivos `.pdf` usando `Smalot\PdfParser\Parser`, transforma o nome do arquivo no título base (trocando `_` e `-` por espaços), fatia em chunks sobrepostos e indexa na tabela `knowledge_chunks`.

> [!NOTE]
> PDFs compostos apenas por imagens escaneadas (sem camada de texto selecionável) não geram fragmentos e exibem um aviso no terminal.

### Argumento e Opções

| Parâmetro | Padrão | Descrição |
| :--- | :--- | :--- |
| `path` *(argumento)* | `storage/app/knowledge/pdfs` | Caminho de uma pasta com PDFs ou de um arquivo `.pdf` específico (relativo à raiz ou absoluto). |
| `--source=` | `PDF` | Rótulo da fonte gravado no banco (ex.: `Embrapa`, `FAO`, `Receita Federal`, `Senar`). |
| `--category=` | `Conhecimento Geral` | Categoria/tópico associado aos fragmentos gerados. |
| `--words-per-chunk=` | `350` | Palavras aproximadas por fragmento (mínimo `100`). |
| `--overlap=` | `50` | Palavras sobrepostas entre fragmentos (`10` a `100`). |

### Exemplos práticos

```powershell
# Processar todos os PDFs da pasta padrão (storage/app/knowledge/pdfs)
php artisan knowledge:ingest-pdf

# Processar um PDF específico definindo fonte e categoria
php artisan knowledge:ingest-pdf storage/app/knowledge/pdfs/embrapa_biomaphos_solubilizacao_fosforo.pdf --source="Embrapa" --category="Bioinsumos"

# Processar uma cartilha tributária da Receita Federal / CNA
php artisan knowledge:ingest-pdf storage/app/knowledge/pdfs/cartilha_ibs_cbs.pdf --source="Receita Federal" --category="Tributação Rural"
```

---

## 3. `knowledge:ingest`

Arquivo: [`IngestKnowledgeCommand.php`](../app/Console/Commands/Knowledge/IngestKnowledgeCommand.php)

Lê arquivos `.md`, `.markdown` ou `.txt` que possuam cabeçalho **YAML Frontmatter** no topo e salva cada documento **inteiro** (sem fatiar em várias partes) na tabela `knowledge_chunks`.

### Opções

| Opção | Padrão | Descrição |
| :--- | :--- | :--- |
| `--path=` | `storage/app/knowledge/markdown` | Diretório relativo contendo os manuais Markdown. |
| `--fresh` | `false` | Solicita confirmação interativa e limpa toda a tabela `knowledge_chunks` (`truncate`) antes de reimportar. |

### Formato esperado do arquivo Markdown

```markdown
---
source: Embrapa
title: Título Único do Manual Técnico
topic: Fertilidade do Solo e Nutrição de Plantas
keywords:
  - calagem
  - calcário dolomítico
  - saturação por bases
---
Conteúdo técnico completo do manual aqui...
```

> [!IMPORTANT]
> Arquivos sem `title` no frontmatter ou sem corpo de texto são ignorados com um aviso no terminal. Caso `source` não seja informado, assume `Markdown` por padrão.

### Exemplo prático

```powershell
# Indexar/atualizar manuais da pasta padrão
php artisan knowledge:ingest

# Apagar a base existente (com confirmação) e reimportar do zero
php artisan knowledge:ingest --fresh
```

---

## Automação e Agendamento (`Schedule`)

Em [`routes/console.php`](../routes/console.php), existem dois agendamentos automáticos:

1. **Semanal (domingos às `03:00`):** `knowledge:fetch --limit=5`
2. **Diário (`03:30`):** `knowledge:ingest-pdf` (indexa automaticamente novos PDFs colocados em `storage/app/knowledge/pdfs`)

Para listar ou executar o agendador localmente em desenvolvimento:

```powershell
php artisan schedule:list
php artisan schedule:work
```

---

## Idempotência e Verificação no Banco

- Todos os comandos gravam via `KnowledgeChunk::updateOrCreate(['title' => ...])`. Reexecutar um comando sobre o mesmo documento **atualiza** o conteúdo sem duplicar linhas na tabela.
- Para consultar o total de fragmentos indexados, fontes e últimos registros via Tinker:

```powershell
php artisan tinker --execute="echo 'Total: ' . App\Models\KnowledgeChunk::count() . PHP_EOL; print_r(App\Models\KnowledgeChunk::selectRaw('source, count(*) as total')->groupBy('source')->pluck('total', 'source')->toArray()); print_r(App\Models\KnowledgeChunk::latest('id')->take(5)->get(['id', 'source', 'title', 'updated_at'])->toArray());"
```
