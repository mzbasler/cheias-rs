<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light">
    <title>Cheias RS — nível dos rios</title>
    @vite(['resources/css/app.css', 'resources/js/map.js'])
</head>
<body class="h-dvh bg-stone-100 text-stone-900">

    <dialog id="disclaimer" class="disclaimer" aria-labelledby="disclaimer-title">
        <h1 id="disclaimer-title" class="disclaimer-title">Página não oficial</h1>

        <p class="disclaimer-text">
            Este site não é operado pela Defesa Civil nem por nenhum órgão público.
            Ele reapresenta dados públicos e pode ficar desatualizado ou fora do ar
            sem aviso.
        </p>

        <p class="disclaimer-text">
            <strong>Em emergência, siga a orientação oficial da Defesa Civil.</strong>
        </p>

        <ul class="disclaimer-phones">
            <li><a href="tel:199"><span>Defesa Civil</span><strong>199</strong></a></li>
            <li><a href="tel:193"><span>Bombeiros</span><strong>193</strong></a></li>
        </ul>

        <form method="dialog">
            <button class="disclaimer-button" autofocus>Entendi, ver o mapa</button>
        </form>
    </dialog>

    <main id="map" class="h-dvh" role="application" aria-label="Mapa das estações de medição"></main>

    {{-- Dados fora do JavaScript: o navegador trata como texto, nunca como código. --}}
    <script type="application/json" id="stations-data">@json($stations)</script>

</body>
</html>
