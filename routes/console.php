<?php

use Illuminate\Support\Facades\Schedule;

// As leituras mudam a cada ~30 min na origem; 15 min mantém o mapa próximo do
// rio sem martelar a Defesa Civil.
Schedule::command('import:sigdc')->everyFifteenMinutes()->withoutOverlapping();

// O SACE publica a cada 15 min, mas são ~90 requisições por ciclo: meia hora
// mantém o mapa atual sem martelar o servidor do SGB.
Schedule::command('import:sace')->everyThirtyMinutes()->withoutOverlapping();

// O inventário de estações é catálogo, não medição: muda em escala de meses.
Schedule::command('import:snirh')->weeklyOn(1, '04:00')->withoutOverlapping();
