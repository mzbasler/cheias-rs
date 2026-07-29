<?php

use Illuminate\Support\Facades\Schedule;

// As leituras mudam a cada ~30 min na origem; 15 min mantém o mapa próximo do
// rio sem martelar a Defesa Civil.
Schedule::command('import:sigdc')->everyFifteenMinutes()->withoutOverlapping();

// O GloFAS roda uma vez por dia; buscar mais que isso só gasta requisição.
Schedule::command('import:discharge')->dailyAt('05:00')->withoutOverlapping();

// O inventário de estações é catálogo, não medição: muda em escala de meses.
Schedule::command('import:snirh')->weeklyOn(1, '04:00')->withoutOverlapping();
