<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light">
    <title>Cheias RS — nível dos rios</title>
    @vite(['resources/css/app.css', 'resources/js/map.js'])
</head>
<body class="flex h-dvh flex-col bg-stone-100 text-stone-900">

    <header class="z-20 shrink-0 bg-white px-4 py-3 shadow-sm">
        <div class="mx-auto flex max-w-5xl flex-wrap items-baseline gap-x-3 gap-y-1">
            <h1 class="text-lg font-semibold tracking-tight">Cheias RS</h1>
            <p class="text-sm text-stone-600">Nível dos rios no Rio Grande do Sul</p>
        </div>

        <ul class="mx-auto mt-2 flex max-w-5xl flex-wrap gap-x-4 gap-y-1 text-xs text-stone-700">
            @foreach ([
                ['Normal', '#0ca30c', 'círculo'],
                ['Alerta', '#fab219', 'triângulo'],
                ['Crítico', '#d03b3b', 'octógono'],
                ['Sem leitura', '#898781', 'círculo tracejado'],
            ] as [$label, $color, $shape])
                <li class="flex items-center gap-1.5">
                    <span class="size-2.5 shrink-0 rounded-full" style="background: {{ $color }}"></span>
                    <span>{{ $label }}</span>
                    <span class="text-stone-500">({{ $shape }})</span>
                </li>
            @endforeach
        </ul>
    </header>

    <main id="map" class="z-0 grow" role="application" aria-label="Mapa das estações de medição"></main>

    <footer class="z-20 shrink-0 bg-white px-4 py-2 text-xs leading-snug text-stone-600">
        <p class="mx-auto max-w-5xl">
            <strong class="font-semibold text-stone-900">Página não oficial.</strong>
            Em emergência, siga a Defesa Civil — telefone 199, Bombeiros 193.
        </p>
    </footer>

    {{-- Dados fora do JavaScript: o navegador trata como texto, nunca como código. --}}
    <script type="application/json" id="stations-data">@json($stations)</script>

</body>
</html>
