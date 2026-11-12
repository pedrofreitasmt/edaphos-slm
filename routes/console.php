<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Agendamento automático do Pipeline ETL de Conhecimento RAG (SaaS Ready)
Schedule::command('knowledge:fetch --limit=5')
    ->weekly()
    ->sundays()
    ->at('03:00')
    ->withoutOverlapping()
    ->description('Colheita semanal automática de artigos da Wikipedia e Embrapa');

Schedule::command('knowledge:ingest-pdf')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->description('Indexação diária automática de novos PDFs adicionados em storage/app/knowledge/pdfs');
