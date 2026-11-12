<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Provedor de IA Padrão
    |--------------------------------------------------------------------------
    |
    | Define o driver padrão responsável pelas inferências do sistema.
    | No futuro, novos drivers (ex: groq, openai, vllm) podem ser adicionados aqui.
    |
    */

    'default' => env('AI_DRIVER', 'ollama'),

    'drivers' => [
        'ollama' => [
            'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
            'model' => env('OLLAMA_MODEL', 'agro-slm'),
            'chat_model' => env('OLLAMA_CHAT_MODEL', 'qwen2.5:3b'),
            'fallback_model' => env('OLLAMA_FALLBACK_MODEL', 'qwen2.5:3b'),
            'keep_alive' => env('OLLAMA_KEEP_ALIVE', '30m'),
            'timeout' => (int) env('OLLAMA_TIMEOUT', 240),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pipeline de Conhecimento (RAG & Ingestão)
    |--------------------------------------------------------------------------
    |
    | Caminhos padrão de entrada de documentos e parâmetros de fatiamento
    | (chunking) compartilhados pelos comandos ETL de ingestão.
    |
    */

    'knowledge' => [
        'markdown_path' => env('AI_KNOWLEDGE_MARKDOWN_PATH', 'storage/app/knowledge/markdown'),
        'pdfs_path' => env('AI_KNOWLEDGE_PDFS_PATH', 'storage/app/knowledge/pdfs'),
        'words_per_chunk' => (int) env('AI_CHUNK_WORDS', 350),
        'overlap_words' => (int) env('AI_CHUNK_OVERLAP', 50),
    ],
];
