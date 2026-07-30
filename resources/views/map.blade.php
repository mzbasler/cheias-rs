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

    {{-- Relato do cidadão. O aviso de que não é medição oficial vem no topo, não
         no fim: quem envia precisa saber disso antes, não depois. --}}
    <dialog id="photo-form" class="report" aria-labelledby="report-title">
        <header class="report-head">
            <h1 id="report-title" class="report-title">Enviar foto do rio</h1>
            <p class="report-kind">Relato de morador · não é medição oficial</p>
            <form method="dialog">
                <button class="report-close" aria-label="Fechar">&times;</button>
            </form>
        </header>

        <div class="report-body">
            <p class="card-eyebrow">Foto</p>

            <label class="report-file">
                <input type="file" id="photo-file" accept="image/*" capture="environment">
                <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">
                    <path d="M3 8.5A2.5 2.5 0 015.5 6h1.2l1-1.6A1 1 0 018.5 4h7a1 1 0 01.85.4L17.3 6h1.2A2.5 2.5 0 0121 8.5v8A2.5 2.5 0 0118.5 19h-13A2.5 2.5 0 013 16.5z"
                          fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    <circle cx="12" cy="12.5" r="3.4" fill="none" stroke="currentColor" stroke-width="1.8"/>
                </svg>
                <span id="photo-label">Tirar foto ou escolher do aparelho</span>
            </label>

            <figure id="photo-preview" class="report-preview" hidden>
                <img id="photo-thumb" alt="Foto escolhida">
                <button type="button" id="photo-clear" class="report-link">Trocar foto</button>
            </figure>

            <p class="card-eyebrow">Onde a foto foi tirada</p>

            <button type="button" id="report-locate" class="report-action">
                <svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true">
                    <circle cx="12" cy="12" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/>
                    <circle cx="12" cy="12" r="2" fill="currentColor"/>
                    <path d="M12 1.5v3M12 19.5v3M1.5 12h3M19.5 12h3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                Usar minha localização
            </button>

            {{-- Busca por submissão explícita: a política do Nominatim proíbe
                 autocomplete no cliente e limita a 1 requisição por segundo. --}}
            <div class="report-search">
                <input type="text" id="report-address" placeholder="Endereço, bairro ou CEP"
                       autocomplete="street-address" enterkeyhint="search">
                <button type="button" id="report-find">Buscar</button>
            </div>

            <div id="report-map" class="report-map" role="application"
                 aria-label="Mapa para marcar onde a foto foi tirada"></div>

            <p id="report-position" class="report-position">
                Arraste o ponto no mapa para marcar o local exato.
            </p>

            <label class="report-consent">
                <input type="checkbox" id="report-consent">
                <span>
                    Autorizo o uso desta foto e da localização nesta página. Sei que a
                    imagem pode conter pessoas, veículos ou fachadas.
                </span>
            </label>

            <p id="report-status" class="report-status" role="status" hidden></p>

            <button type="button" id="report-submit" class="report-submit" disabled>
                Enviar relato
            </button>
        </div>
    </dialog>

    {{-- Dados fora do JavaScript: o navegador trata como texto, nunca como código. --}}
    <script type="application/json" id="stations-data">@json($stations)</script>

</body>
</html>
