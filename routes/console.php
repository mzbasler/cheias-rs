<?php

use Illuminate\Support\Facades\Schedule;

// As leituras mudam a cada ~30 min na origem; 15 min mantém o mapa próximo do
// rio sem martelar a Defesa Civil.
Schedule::command('import:sigdc')->everyFifteenMinutes()->withoutOverlapping();

// A ANA publica a cota a cada 15 min — mesma cadência do SIGDC, API oficial
// autenticada em vez da raspagem que o SACE exigia.
Schedule::command('import:ana')->everyFifteenMinutes()->withoutOverlapping();

// import:sace agora só cobre as ~31 estações sem correspondência no catálogo
// da ANA — bem menos que as ~90 de antes, mas ainda é raspagem: meia hora
// mantém o mapa atual sem martelar o servidor do SGB.
Schedule::command('import:sace')->everyThirtyMinutes()->withoutOverlapping();

// O inventário de estações é catálogo, não medição: muda em escala de meses.
Schedule::command('import:snirh')->weeklyOn(1, '04:00')->withoutOverlapping();

// Cota de referência também é catálogo, não medição — mesma cadência do
// inventário da ANA, com uma hora de intervalo pra não disputar o scheduler.
Schedule::command('import:cemaden')->weeklyOn(1, '05:00')->withoutOverlapping();
