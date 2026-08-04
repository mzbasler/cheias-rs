<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Cheias RS — nível dos rios</title>
    @vite(['resources/css/app.css', 'resources/js/map.js'])
</head>
<body class="h-dvh bg-stone-100 text-stone-900">

    <dialog id="disclaimer" class="disclaimer" aria-labelledby="disclaimer-title">
        {{-- Mesmo triângulo âmbar dos pins de alerta: o aviso e o mapa falam a
             mesma língua visual. --}}
        <header class="disclaimer-head">
            <svg class="disclaimer-icon" viewBox="0 0 24 24" width="56" height="56" aria-hidden="true">
                <path d="M12 3L22.2 20.6H1.8z" fill="#fab219" stroke="#3d2c00" stroke-width="1.5" stroke-linejoin="round"/>
                <path d="M12 9.6v4.5" stroke="#3d2c00" stroke-width="2.2" stroke-linecap="round"/>
                <circle cx="12" cy="17.1" r="1.35" fill="#3d2c00"/>
            </svg>
            <h1 id="disclaimer-title" class="disclaimer-title">Página não oficial</h1>
        </header>

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

    {{-- No desktop, o card da estação empurra o mapa em vez de flutuar por
         cima — sobra tela para os dois. No celular, o card segue como bolha,
         que é o que o polegar alcança sem esconder o pin que acabou de tocar. --}}
    <div class="map-layout">
        <main id="map" class="h-dvh" role="application" aria-label="Mapa das estações de medição"></main>

        <aside id="station-sidebar" class="station-sidebar" aria-label="Detalhes da estação">
            <div class="station-sidebar-inner">
                <button type="button" class="station-sidebar-close report-close" aria-label="Fechar">&times;</button>
                <div id="station-sidebar-body"></div>
            </div>
        </aside>
    </div>

    {{-- Painel central das 55 estações: busca, filtro por estado, nível e
         horário de cada uma, com atalho para abrir no mapa ou tirá-la de vista.
         O conteúdo é montado pelo JS a partir dos mesmos dados do mapa. --}}
    <dialog id="stations" class="stations" aria-labelledby="stations-title">
        <header class="report-head">
            <h1 id="stations-title" class="report-title stations-title">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                     stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M2 12q2.5 2 5 0t5 0 5 0 5 0" />
                    <path d="M2 19q2.5 2 5 0t5 0 5 0 5 0" />
                    <path d="M2 5q2.5 2 5 0t5 0 5 0 5 0" />
                </svg>
                Estações fluviométricas
            </h1>
            <form method="dialog">
                <button class="report-close" aria-label="Fechar">&times;</button>
            </form>
        </header>

        <div class="stations-toolbar">
            <input type="search" id="stations-search" class="stations-search"
                   placeholder="Buscar por nome, rio ou município" autocomplete="off">

            <div class="stations-chips" id="stations-chips" role="group" aria-label="Filtrar por estado"></div>
        </div>

        <div class="stations-body" id="stations-body"></div>
    </dialog>

    {{-- Sobre + apoiar, uma modal só. Uma frase pra quem nunca ouviu falar do
         projeto explicar a ideia — e o pedido de apoio é o conteúdo principal
         da modal, não um rodapé discreto: é ele que paga o servidor. --}}
    <dialog id="about" class="about" aria-labelledby="about-title">
        <header class="about-head">
            <h1 id="about-title" class="about-title">Sobre o projeto</h1>
            <form method="dialog">
                <button class="report-close" aria-label="Fechar">&times;</button>
            </form>
        </header>

        <p class="about-lead">
            Monitoramento colaborativo e independente do nível dos rios do Rio Grande do
            Sul, em tempo real e de graça.
        </p>

        @if ($pix['key'])
            <div class="donate-panel">
                <p class="donate-heading">
                    <svg class="donate-heading-icon" viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"
                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 14h2a2 2 0 0 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 16"/>
                        <path d="m14.45 13.39 5.05-4.694C20.196 8 21 6.85 21 5.75a2.75 2.75 0 0 0-4.797-1.837.276.276 0 0 1-.406 0A2.75 2.75 0 0 0 11 5.75c0 1.2.802 2.248 1.5 2.946L16 11.95"/>
                        <path d="m2 15 6 6"/>
                        <path d="m7 20 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a1 1 0 0 0-2.75-2.91"/>
                    </svg>
                    Apoie o projeto
                </p>
                <p class="donate-text">
                    Sem patrocínio, sem anúncio — o servidor sai do bolso de quem fez. Um Pix
                    de qualquer valor mantém o mapa no ar.
                </p>

                <div class="donate-amounts" id="donate-amounts" role="group" aria-label="Valor da doação">
                    <button type="button" class="chip" data-amount="10">R$ 10</button>
                    <button type="button" class="chip" data-amount="20">R$ 20</button>
                    <button type="button" class="chip" data-amount="50">R$ 50</button>
                    <button type="button" class="chip" data-amount="100">R$ 100</button>
                </div>

                <label class="donate-custom">
                    <span>Outro valor (R$)</span>
                    <input type="number" id="donate-value" inputmode="decimal" min="1" step="0.01"
                           placeholder="0,00">
                </label>

                <div class="donate-qr" id="donate-qr" hidden></div>

                <button type="button" class="report-action donate-copy-action" id="donate-copy" hidden>
                    <svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true">
                        <rect x="8" y="8" width="12" height="12" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.8"/>
                        <path d="M16 8V5.5A1.5 1.5 0 0014.5 4h-9A1.5 1.5 0 004 5.5v9A1.5 1.5 0 005.5 16H8"
                              fill="none" stroke="currentColor" stroke-width="1.8"/>
                    </svg>
                    Copiar código Pix
                </button>

                <p id="donate-status" class="report-status" role="status" hidden></p>
            </div>
        @endif

        <form method="dialog">
            <button class="disclaimer-button">Voltar ao mapa</button>
        </form>
    </dialog>

    {{-- Dados fora do JavaScript: nome e cidade compõem o payload do QR Code,
         mas nunca a chave é usada se estiver vazia. --}}
    <script type="application/json" id="pix-data">@json($pix)</script>

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

    {{-- Foto de relato ou vídeo de câmera: uma modal só para os dois, o JS decide
         o miolo (img ou iframe) conforme o pin clicado. --}}
    <dialog id="media" class="media" aria-labelledby="media-title">
        <header class="media-head">
            <h1 id="media-title" class="media-title"></h1>
            <form method="dialog">
                <button class="report-close" aria-label="Fechar">&times;</button>
            </form>
        </header>
        <div class="media-body" id="media-body"></div>
    </dialog>

    {{-- Dados fora do JavaScript: o navegador trata como texto, nunca como código. --}}
    <script type="application/json" id="stations-data">@json($stations)</script>
    <script type="application/json" id="reports-data">@json($reports)</script>
    <script type="application/json" id="cameras-data">@json($cameras)</script>

</body>
</html>
