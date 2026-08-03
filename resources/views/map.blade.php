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

    {{-- O que o mapa mostra e quem mediu. As contagens vêm do banco: número
         escrito à mão no texto vira mentira na primeira importação. --}}
    <dialog id="about" class="about" aria-labelledby="about-title">
        <header class="about-head">
            <h1 id="about-title" class="about-title">Sobre os dados</h1>
            <form method="dialog">
                <button class="report-close" aria-label="Fechar">&times;</button>
            </form>
        </header>

        <p class="about-eyebrow">Cada ponto</p>

        <p class="about-text">
            Uma estação que mede o rio. O valor é a <strong>cota</strong>: altura da água em
            metros a partir do zero da régua daquele ponto — não se compara com a de outra
            estação.
        </p>

        <p class="about-text">
            A cor sai da comparação com as cotas que o órgão publica para aquele ponto.
            Cinza é falta de informação — sem leitura há mais de 3 h ou sem cota —, não rio
            calmo.
        </p>

        <p class="about-eyebrow">As fontes</p>

        <ul class="about-sources">
            <li>
                <strong>{{ $sources['snirh'] ?? 0 }}</strong>
                <span><b>SNIRH/Hidroweb (ANA)</b> — catálogo das estações do RS, sem leitura.</span>
            </li>
            <li>
                <strong>{{ $sources['sace'] ?? 0 }}</strong>
                <span><b>SACE (SGB)</b> — a maior parte das leituras e das cotas.</span>
            </li>
            <li>
                <strong>{{ $sources['sigdc'] ?? 0 }}</strong>
                <span><b>SIGDC (Defesa Civil RS)</b> — pontos com leitura.</span>
            </li>
        </ul>

        <p class="about-text">
            No mapa, as <strong>{{ $stations->count() }}</strong> estações que têm leitura.
            As outras <strong>{{ $catalogTotal - $stations->count() }}</strong> do catálogo
            nunca reportaram e ficam de fora.
        </p>

        <form method="dialog">
            <button class="disclaimer-button">Voltar ao mapa</button>
        </form>
    </dialog>

    {{-- Doação por Pix. Sem chave configurada, o corpo vira aviso — nunca um QR
         Code apontando para lugar nenhum. --}}
    <dialog id="donate" class="donate" aria-labelledby="donate-title">
        <header class="report-head">
            <h1 id="donate-title" class="report-title">Apoiar o projeto</h1>
            <form method="dialog">
                <button class="report-close" aria-label="Fechar">&times;</button>
            </form>
        </header>

        <div class="donate-body">
            @if ($pix['key'])
                <p class="about-text">
                    Este site é mantido de forma independente, sem custo para quem usa. Se ele
                    te ajudou, um Pix de qualquer valor ajuda a manter no ar.
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

                <button type="button" class="report-action" id="donate-copy" hidden>
                    <svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true">
                        <rect x="8" y="8" width="12" height="12" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.8"/>
                        <path d="M16 8V5.5A1.5 1.5 0 0014.5 4h-9A1.5 1.5 0 004 5.5v9A1.5 1.5 0 005.5 16H8"
                              fill="none" stroke="currentColor" stroke-width="1.8"/>
                    </svg>
                    Copiar código Pix
                </button>

                <p id="donate-status" class="report-status" role="status" hidden></p>
            @else
                <p class="card-gap">Doações via Pix ainda não configuradas nesta instância.</p>
            @endif
        </div>
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

    {{-- Dados fora do JavaScript: o navegador trata como texto, nunca como código. --}}
    <script type="application/json" id="stations-data">@json($stations)</script>
    <script type="application/json" id="reports-data">@json($reports)</script>

</body>
</html>
