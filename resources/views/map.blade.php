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

    {{-- Coordenada e precisão à vista: é o que permite comparar com outro app e
         localizar de onde vem um eventual desvio. --}}
    <p id="locate-readout" class="locate-readout" aria-live="polite" hidden></p>

    {{-- A precisão é decidida pelo sistema operacional antes de chegar ao
         navegador. Quando vem grosseira, o que resta é dizer por quê. --}}
    <div id="locate-coarse" class="locate-coarse" role="status" hidden>
        <p><strong>Posição imprecisa.</strong> O navegador usou a rede, não o GPS.</p>
        <ul>
            <li>Conceda permissão <strong>precisa</strong>, não aproximada</li>
            <li>Ative o GPS do aparelho</li>
            <li>Abra a página em <strong>HTTPS</strong> — sem isso o GPS fica bloqueado</li>
            <li>No computador não há GPS: a posição sempre vem da rede</li>
        </ul>
    </div>

    {{-- O envio de foto ainda não existe. Um botão que aceitasse a imagem e a
         descartasse faria alguém acreditar que pediu ajuda durante uma cheia. --}}
    <dialog id="photo-notice" class="disclaimer" aria-labelledby="photo-notice-title">
        <h1 id="photo-notice-title" class="disclaimer-title">Enviar foto do rio</h1>

        <p class="disclaimer-text">
            Esta função ainda não está ativa. Sua foto não seria recebida por
            ninguém, então preferimos avisar em vez de aceitar o envio.
        </p>

        <p class="disclaimer-text">
            <strong>Precisa de ajuda agora?</strong> Ligue para a Defesa Civil.
        </p>

        <ul class="disclaimer-phones">
            <li><a href="tel:199"><span>Defesa Civil</span><strong>199</strong></a></li>
            <li><a href="tel:193"><span>Bombeiros</span><strong>193</strong></a></li>
        </ul>

        <form method="dialog">
            <button class="disclaimer-button" autofocus>Entendi</button>
        </form>
    </dialog>

    {{-- Dados fora do JavaScript: o navegador trata como texto, nunca como código. --}}
    <script type="application/json" id="stations-data">@json($stations)</script>

</body>
</html>
