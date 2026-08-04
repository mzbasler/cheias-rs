import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import qrcode from 'qrcode-generator';

/**
 * Uma peça só para os quatro estados: um ponto do mesmo diâmetro, que muda de
 * cor. A cor não decide sozinha nos estados de risco — eles pulsam, e movimento
 * sobrevive ao daltonismo e ao sol forte, onde a diferença de matiz some.
 */
const DOT_SIZE = 14;

/**
 * zIndexOffset separa a pilha por gravidade, não por posição na tela — sem
 * isso o Leaflet empilha por latitude, e uma estação normal mais ao sul podia
 * cobrir uma em alerta mais ao norte. O intervalo de 1000 é bem maior que
 * qualquer disputa de posição consegue vencer.
 */
const STATUS = {
    critical: { label: 'Inundação', color: '#d03b3b', zIndexOffset: 3000 },
    alert: { label: 'Alerta', color: '#fab219', zIndexOffset: 2000 },
    normal: { label: 'Normal', color: '#0ca30c', zIndexOffset: 1000 },
    // Mede o rio corretamente, só não tem cota de referência publicada pra
    // classificar severidade — não é a mesma coisa que sensor mudo, e não pode
    // parecer um problema no ponto: azul, a cor de "dado real" no resto do
    // app, não mais uma variação de cinza.
    unclassified: { label: 'Sem cota de referência', color: '#2a78d6', zIndexOffset: 500 },
    unknown: { label: 'Sem leitura', color: '#8c8a85', zIndexOffset: 0 },
};

const dateFormat = new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'America/Sao_Paulo',
});

const timeFormat = new Intl.DateTimeFormat('pt-BR', {
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'America/Sao_Paulo',
});

const SOURCE_LABEL = {
    sigdc: 'Defesa Civil de Eldorado do Sul (SIGDC)',
    sace: 'SGB/CPRM — SACE',
};

const number = (value, digits = 2) =>
    value.toLocaleString('pt-BR', { minimumFractionDigits: digits, maximumFractionDigits: digits });

/** O mesmo ponto serve o mapa e a lista de filtros — só o tamanho muda. */
function dot(status, size, label = '') {
    const { color } = STATUS[status] ?? STATUS.unknown;
    const meaning = label ? `role="img" aria-label="${label}"` : 'aria-hidden="true"';

    return `<span class="dot" style="--dot:${color};--dot-size:${size}px" ${meaning}></span>`;
}

/**
 * Alvo de toque, não tamanho do desenho: o ponto encolheu para não sujar o mapa,
 * mas o dedo continua acertando a mesma área de sempre.
 */
const HIT_AREA = 30;

function icon(station) {
    const { status } = station;
    const { label } = STATUS[status] ?? STATUS.unknown;

    return L.divIcon({
        className: `station-pin station-pin--${status}`,
        html: dot(status, DOT_SIZE, label),
        iconSize: [HIT_AREA, HIT_AREA],
        iconAnchor: [HIT_AREA / 2, HIT_AREA / 2],
        popupAnchor: [0, -DOT_SIZE / 2 - 2],
    });
}

function escape(value) {
    const element = document.createElement('span');
    element.textContent = value;

    return element.innerHTML;
}

/**
 * Topo da escala do medidor. A fonte informa as cotas, mas não o leito nem a
 * margem do rio: usa-se 20% acima do maior valor conhecido, arredondado para
 * meio metro, para a água nunca encostar no teto.
 */
function gaugeTop(station) {
    const ceiling = Math.max(station.criticalLevel ?? 0, station.reading.value, station.peak ?? 0);

    return Math.max(0.5, Math.ceil(ceiling * 1.2 * 2) / 2);
}

const percent = (value, top) => Math.min(100, Math.max(0, (value / top) * 100));

/**
 * Corte vertical do rio: faixas de risco ao fundo, água subindo do leito, cotas
 * atravessando na altura de cada limiar. Largo em vez de estreito — com 45 px o
 * tanque não simulava nada; ocupando a largura do card, a água encostando na
 * linha de inundação se lê de relance.
 */
function gauge(station) {
    const top = gaugeTop(station);
    const alert = station.alertLevel;
    const critical = station.criticalLevel;

    const alertAt = alert === null ? 100 : percent(alert, top);
    const criticalAt = critical === null ? 100 : percent(critical, top);

    const bands = `
        <div class="band" data-zone="normal" style="bottom:0;height:${alertAt}%"></div>
        <div class="band" data-zone="alert" style="bottom:${alertAt}%;height:${Math.max(0, criticalAt - alertAt)}%"></div>
        <div class="band" data-zone="critical" style="bottom:${criticalAt}%;height:${Math.max(0, 100 - criticalAt)}%"></div>
    `;

    // Halo na linha de cota: sobre a água azul, vermelho mede 1,09:1 de contraste
    // e desapareceria. A etiqueta cabe porque o tanque agora é largo.
    const marks = [
        ['alert', 'Alerta', alert],
        ['critical', 'Inundação', critical],
    ]
        .filter(([, , value]) => value !== null)
        .map(
            ([kind, label, value]) => `
                <div class="limit" data-kind="${kind}" style="bottom:${percent(value, top)}%">
                    <b>${label} ${number(value)} m</b>
                </div>
            `,
        )
        .join('');

    const water = station.reading.stale
        ? '<div class="tank-empty">Sem leitura</div>'
        : `<div class="water" style="height:${percent(station.reading.value, top)}%"></div>`;

    // Régua com marca a cada quarto da escala: só os extremos não dão noção de
    // proporção a quem olha o medidor.
    const ruler = [0, 0.25, 0.5, 0.75, 1]
        .map((fraction) => `<i style="bottom:${fraction * 100}%">${number(top * fraction, 1)}</i>`)
        .join('');

    return `
        <div class="gauge">
            <div class="gauge-ruler" aria-hidden="true">${ruler}</div>
            <div class="tank" role="img" aria-label="${escape(station.name)}: ${number(station.reading.value)} m numa escala até ${number(top, 1)} m">
                ${bands}
                ${water}
                ${marks}
            </div>
        </div>
    `;
}

/**
 * A resposta que o card existe para dar: quanto falta para o rio transbordar, ou
 * quanto já passou disso. O valor de cada cota já está no medidor — repetir tudo
 * numa tabela era a informação duplicada.
 */
function gapLine(station) {
    const { value } = station.reading;
    const alert = station.alertLevel;
    const critical = station.criticalLevel;

    if (critical !== null) {
        return value >= critical
            ? `<p class="card-gap" data-level="critical">Passou <strong>${number(value - critical)} m</strong> da cota de inundação</p>`
            : `<p class="card-gap"${alert !== null && value >= alert ? ' data-level="alert"' : ''}>Faltam <strong>${number(critical - value)} m</strong> para a cota de inundação</p>`;
    }

    if (alert !== null) {
        return value >= alert
            ? `<p class="card-gap" data-level="alert">Passou <strong>${number(value - alert)} m</strong> da cota de alerta</p>`
            : `<p class="card-gap">Faltam <strong>${number(alert - value)} m</strong> para a cota de alerta</p>`;
    }

    return '<p class="card-gap">Sem cota de referência publicada</p>';
}

function trendLabel(change) {
    if (change === null) {
        return '';
    }

    const { value, hours } = change;
    const arrow = value > 0.005 ? '▲' : value < -0.005 ? '▼' : '—';
    const sign = value > 0 ? '+' : '';

    return `<em class="card-trend" data-rising="${value > 0.005}">${arrow} ${sign}${number(value)} m / ${number(hours, 1)} h</em>`;
}

/**
 * Gráfico das últimas 24 h. Linha, não barra: a variação é de centímetros e
 * barra com base truncada mentiria sobre a proporção. A faixa vertical usada vai
 * declarada em texto embaixo, porque o eixo não começa no zero.
 */
function historyBlock(station) {
    const series = station.history ?? [];

    if (series.length < 2) {
        return '<p class="card-note">Sem leituras suficientes nas últimas 24 horas.</p>';
    }

    const values = series.map(([, value]) => value);
    const low = Math.min(...values);
    const high = Math.max(...values);
    const pad = Math.max((high - low) * 0.12, 0.01);
    const floor = low - pad;
    const span = high + pad - floor || 1;

    const points = series
        .map(([, value], index) => {
            const x = ((index / (series.length - 1)) * 100).toFixed(2);
            const y = (40 - ((value - floor) / span) * 40).toFixed(2);

            return `${x},${y}`;
        })
        .join(' ');

    const first = timeFormat.format(new Date(series[0][0] * 1000));
    const last = timeFormat.format(new Date(series.at(-1)[0] * 1000));

    return `
        <svg class="card-chart" viewBox="0 0 100 40" preserveAspectRatio="none" role="img"
             aria-label="Nível variou de ${number(low)} a ${number(high)} metros nas últimas 24 horas">
            <polyline points="${points}" vector-effect="non-scaling-stroke"/>
        </svg>
        <p class="card-axis"><span>${first}</span><span>${series.length} leituras</span><span>${last}</span></p>
        <p class="card-note">Escala ${number(floor)}–${number(high + pad)} m, não começa no zero.
        Mín ${number(low)} · máx ${number(high)} m.</p>
    `;
}

function popup(station) {
    const status = STATUS[station.status] ?? STATUS.unknown;
    const place = [station.river, station.municipality].filter(Boolean).join(' · ');
    const { reading, unit } = station;

    // Leitura velha aparece — mas nunca sem o carimbo que a denuncia como velha.
    const staleWarning = reading.stale
        ? '<p class="card-stale">Transmissão interrompida · leitura desatualizada</p>'
        : '';

    return `
        <div style="--status:${status.color}">
            <header class="card-head">
                <h2 class="card-name">${escape(station.name)}</h2>
                <span class="card-badge" data-status="${station.status}">${status.label}</span>
            </header>
            ${place ? `<p class="card-place">${escape(place)}</p>` : ''}

            ${gauge(station)}

            <p class="card-reading">
                <strong class="card-value">${number(reading.value)}<span>${escape(unit ?? 'm')}</span></strong>
                ${trendLabel(station.change)}
                <span class="card-time">${dateFormat.format(new Date(reading.measuredAt))}</span>
            </p>
            ${staleWarning}

            ${gapLine(station)}

            <p class="card-eyebrow">Últimas 24 horas</p>
            ${historyBlock(station)}

            <p class="card-source">${escape(SOURCE_LABEL[reading.source] ?? reading.source)}</p>
        </div>
    `;
}

const stations = JSON.parse(document.getElementById('stations-data').textContent);
const reports = JSON.parse(document.getElementById('reports-data').textContent);

const map = L.map('map', {
    center: [-29.8, -53.2], // Rio Grande do Sul
    zoom: 6,
    zoomControl: false,
    // A atribuição sai da caixa padrão e entra num controle próprio — exigida pela
    // licença dos tiles, então muda de lugar, não desaparece.
    attributionControl: false,
});

/**
 * Sete fontes abertas e gratuitas, sem chave de API — trocar de estilo não
 * depende de nenhuma infraestrutura nova. Voyager é o padrão: terreno neutro,
 * mas rios e lâminas de água em azul definido. O OSM satura verde e laranja,
 * competindo com as cores de alerta; o Positron apaga a água, que aqui é o
 * assunto — por isso nenhum dos dois é o padrão, mas seguem como opção.
 */
const BASEMAPS = [
    {
        key: 'voyager',
        label: 'Voyager',
        url: 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
        subdomains: 'abcd',
        maxZoom: 19,
    },
    {
        key: 'positron',
        label: 'Claro',
        url: 'https://{s}.basemaps.cartocdn.com/rastertiles/light_all/{z}/{x}/{y}{r}.png',
        subdomains: 'abcd',
        maxZoom: 19,
    },
    {
        key: 'dark',
        label: 'Escuro',
        url: 'https://{s}.basemaps.cartocdn.com/rastertiles/dark_all/{z}/{x}/{y}{r}.png',
        subdomains: 'abcd',
        maxZoom: 19,
    },
    {
        key: 'osm',
        label: 'OpenStreetMap',
        url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        subdomains: 'abc',
        maxZoom: 19,
    },
    {
        key: 'topo',
        label: 'Relevo',
        url: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
        subdomains: 'abc',
        maxZoom: 17,
    },
    {
        key: 'satellite',
        label: 'Satélite',
        url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        maxZoom: 19,
    },
    {
        key: 'humanitarian',
        label: 'Humanitário',
        url: 'https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png',
        subdomains: 'abc',
        maxZoom: 19,
    },
];

const BASEMAP_STORAGE_KEY = 'cheias-rs:basemap';

let activeBasemap = null;
let basemapLayer = null;

function setBasemap(key) {
    const basemap = BASEMAPS.find((item) => item.key === key) ?? BASEMAPS[0];

    if (basemapLayer) {
        map.removeLayer(basemapLayer);
    }

    basemapLayer = L.tileLayer(basemap.url, {
        subdomains: basemap.subdomains ?? 'abc',
        maxZoom: basemap.maxZoom,
    }).addTo(map);

    activeBasemap = basemap;

    // Lembrado entre sessões: quem prefere o mapa escuro à noite não deveria
    // escolher de novo toda vez que abre a página.
    localStorage.setItem(BASEMAP_STORAGE_KEY, basemap.key);
}

setBasemap(localStorage.getItem(BASEMAP_STORAGE_KEY));

/**
 * Uma camada só, com todos os marcadores dentro — o filtro liga e desliga cada
 * um individualmente por id, via addLayer/removeLayer/hasLayer do próprio
 * grupo. Numa cheia, o que importa é isolar as estações em risco entre
 * centenas de pins.
 */
const stationsLayer = L.layerGroup().addTo(map);
const markersById = {};

/** station.id -> station, para achar o dado a partir de um data-id no DOM. */
const stationsById = Object.fromEntries(stations.map((station) => [station.id, station]));

const sidebar = document.getElementById('station-sidebar');
const sidebarBody = document.getElementById('station-sidebar-body');

/** No desktop, width:0→24rem anima; no celular, o slide é por transform. Os
 *  dois casos duram 250 ms — invalidateSize cedo demais pega o mapa no meio
 *  do caminho, e só o desktop de fato muda o tamanho do container do mapa. */
function resizeMapAfterSidebar() {
    setTimeout(() => map.invalidateSize({ animate: true }), 260);
}

function closeStationSidebar() {
    sidebar.dataset.open = 'false';
    resizeMapAfterSidebar();
}

document.querySelector('.station-sidebar-close').addEventListener('click', closeStationSidebar);

// Clicar no mapa vazio fecha o card — clique em marcador não chega aqui,
// o Leaflet já para a propagação antes.
map.on('click', () => {
    if (sidebar.dataset.open === 'true') {
        closeStationSidebar();
    }
});

/** No celular cobre a tela inteira; no desktop empurra o mapa — o mesmo
 *  elemento, o CSS decide a apresentação. */
function showStationDetail(station) {
    sidebarBody.innerHTML = popup(station);
    sidebar.dataset.open = 'true';
    resizeMapAfterSidebar();
}

stations.forEach((station) => {
    const status = STATUS[station.status] ?? STATUS.unknown;

    const marker = L.marker([station.latitude, station.longitude], {
        icon: icon(station),
        title: `${station.name} — ${status.label}`,
        alt: `${station.name} — ${status.label}`,
        riseOnHover: true,
        zIndexOffset: status.zIndexOffset,
    });

    marker.on('click', () => showStationDetail(station));

    markersById[station.id] = marker;

    // Todo status começa visível: 'unknown' cobre tanto "nunca teve leitura"
    // quanto "leitura ficou velha" (Station::status()), e o segundo caso é
    // exatamente o que não pode desaparecer numa cheia — fonte indisponível é
    // estado visível, nunca lacuna silenciosa.
    stationsLayer.addLayer(marker);
});

/**
 * Relato de morador é camada própria, nunca misturada com o pin de estação —
 * nem no ícone (quadrado, não círculo), nem no popup (aviso "não é medição
 * oficial" sempre visível), nem no grupo do Leaflet.
 */
const reportsLayer = L.layerGroup().addTo(map);

function reportIcon() {
    return L.divIcon({
        className: 'report-pin',
        // Mesma câmera do botão "Enviar foto do rio" (ICON_CAMERA, definida
        // mais abaixo) — duplicada aqui em vez de referenciada porque este
        // código roda antes daquele const existir.
        html: `
            <span class="report-marker" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="#fff" stroke-width="2.3" stroke-linejoin="round">
                    <path d="M3 8.5A2.5 2.5 0 015.5 6h1.2l1-1.6A1 1 0 018.5 4h7a1 1 0 01.85.4L17.3 6h1.2A2.5 2.5 0 0121 8.5v8A2.5 2.5 0 0118.5 19h-13A2.5 2.5 0 013 16.5z"/>
                    <circle cx="12" cy="12.5" r="3.4"/>
                </svg>
            </span>
        `,
        iconSize: [20, 20],
        iconAnchor: [10, 10],
        popupAnchor: [0, -10],
    });
}

reports.forEach((report) => {
    const marker = L.marker([report.latitude, report.longitude], {
        icon: reportIcon(),
        title: 'Relato de morador',
        alt: 'Relato de morador',
    });

    marker.bindPopup(`
        <div class="report-popup">
            <p class="report-popup-kind">Relato de morador · não é medição oficial</p>
            <img class="report-popup-photo" src="${report.photoUrl}" alt="Foto enviada por morador">
            <p class="report-popup-date">${dateFormat.format(new Date(report.createdAt))}</p>
        </div>
    `);

    reportsLayer.addLayer(marker);
});

/**
 * Dock: todos os controles do mapa numa barra só, no alto à esquerda. Espalhados
 * por dois cantos, o polegar precisava atravessar a tela para ir do filtro à
 * câmera.
 *
 * O botão de estações nasce aqui; os demais entram por dockButton(), na ordem
 * em que o arquivo os define.
 */
let dockBar = null;

/** Clicar de novo no botão que abriu fecha — abre e fecha alternam no mesmo
 *  botão, sem depender só do X. */
function toggleDialog(dialog) {
    if (dialog.open) {
        dialog.close();
    } else {
        dialog.showModal();
    }
}

/** Clicar fora do cartão (no fundo escurecido) fecha — só existe fundo
 *  clicável no desktop; em tela cheia no celular o dialog não sobra área para
 *  clicar fora. O clique no backdrop chega aqui com o próprio dialog como
 *  alvo, nunca um filho dele. */
function closeOnBackdropClick(dialog) {
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });
}

closeOnBackdropClick(document.getElementById('stations'));
closeOnBackdropClick(document.getElementById('about'));
closeOnBackdropClick(document.getElementById('donate'));

/** O botão de estações abre o modal — só o dock nasce como controle do Leaflet. */
const tools = L.control({ position: 'topleft' });

tools.onAdd = () => {
    const container = L.DomUtil.create('div', 'tools');

    // Ícone "waves" do Lucide (lucide.dev), sem alteração de path — só o
    // stroke-width ajustado para bater com o peso dos outros ícones do dock.
    container.innerHTML = `
        <div class="dock">
            <button type="button" class="filters-toggle"
                    aria-label="Estações fluviométricas" title="Estações fluviométricas">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
                     stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M2 12q2.5 2 5 0t5 0 5 0 5 0" />
                    <path d="M2 19q2.5 2 5 0t5 0 5 0 5 0" />
                    <path d="M2 5q2.5 2 5 0t5 0 5 0 5 0" />
                </svg>
                <em id="filters-count">${stations.length}</em>
            </button>
        </div>
        <div class="basemap-panel" id="basemap-panel" hidden></div>
    `;

    dockBar = container.querySelector('.dock');

    container.querySelector('.filters-toggle').addEventListener('click', () => {
        toggleDialog(document.getElementById('stations'));
    });

    L.DomEvent.disableClickPropagation(container);
    L.DomEvent.disableScrollPropagation(container);

    return container;
};

tools.addTo(map);

/**
 * Modal de estações: painel de dados, não só um filtro. Busca e chips por
 * estado restringem o que aparece NESTA lista; o olho em cada linha controla
 * o mapa, e são coisas diferentes de propósito — filtrar a lista não esconde
 * nada do mapa, só ajuda a achar. Clicar no corpo da linha fecha o modal e
 * abre aquela estação no mapa, como clicar o pin direto.
 */
{
    const body = document.getElementById('stations-body');
    const search = document.getElementById('stations-search');
    const chipsBar = document.getElementById('stations-chips');
    const count = document.getElementById('filters-count');

    const normalize = (value) => value.toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '');

    const groups = Object.keys(STATUS)
        .map((key) => ({ key, ...STATUS[key], stations: stations.filter((station) => station.status === key) }))
        .filter((group) => group.stations.length > 0);

    // Fora do DOM: usado na busca, nunca precisa ser HTML-seguro.
    const searchIndex = {};

    const EYE_ICON =
        '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>' +
        '<circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/>';

    function stationRow(group, station) {
        const place = [station.river, station.municipality].filter(Boolean).join(' · ');
        const { reading, unit } = station;
        const visible = true;

        searchIndex[station.id] = normalize(`${station.name} ${place}`);

        return `
            <li class="station-row" data-id="${station.id}" data-status="${group.key}"
                data-visible="${visible}" style="--status:${group.color}">
                <button type="button" class="station-open" data-id="${station.id}">
                    ${dot(group.key, 10)}
                    <span class="station-text">
                        <span class="station-name">${escape(station.name)}</span>
                        ${place ? `<span class="station-place">${escape(place)}</span>` : ''}
                    </span>
                    <span class="station-reading">
                        <strong>${number(reading.value)} ${escape(unit ?? 'm')}</strong>
                        <span class="card-time">${dateFormat.format(new Date(reading.measuredAt))}</span>
                    </span>
                </button>
                <button type="button" class="station-eye" data-id="${station.id}" aria-pressed="${visible}"
                        aria-label="Mostrar ou ocultar ${escape(station.name)} no mapa" title="Mostrar/ocultar no mapa">
                    <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">${EYE_ICON}</svg>
                </button>
            </li>
        `;
    }

    body.innerHTML = `
        <ul class="station-list">
            ${groups
                .map(
                    (group) => `
                        <li class="station-group" data-status="${group.key}">
                            <div class="station-group-head">
                                <p class="station-group-title">${group.label} <em>${group.stations.length}</em></p>
                                <button type="button" class="station-group-toggle" data-status="${group.key}"
                                        aria-pressed="true"
                                        aria-label="Mostrar ou ocultar ${group.label} no mapa"
                                        title="Mostrar/ocultar categoria no mapa">
                                    <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">${EYE_ICON}</svg>
                                </button>
                            </div>
                            <ul class="station-group-rows">
                                ${group.stations.map((station) => stationRow(group, station)).join('')}
                            </ul>
                        </li>
                    `,
                )
                .join('')}
        </ul>
    `;

    chipsBar.innerHTML = [
        `<button type="button" class="chip" aria-pressed="true">Todas <em>${stations.length}</em></button>`,
        ...groups.map(
            (group) => `
                <button type="button" class="chip" data-status="${group.key}" aria-pressed="false">
                    ${dot(group.key, 8)} ${group.label} <em>${group.stations.length}</em>
                </button>
            `,
        ),
    ].join('');

    let activeStatus = null;

    // Busca e chip precisam concordar para uma linha aparecer; o cabeçalho do
    // grupo some junto se nenhuma estação dele sobrar.
    function applyFilter() {
        const query = normalize(search.value.trim());

        body.querySelectorAll('.station-group').forEach((group) => {
            const groupMatches = activeStatus === null || group.dataset.status === activeStatus;
            let visible = 0;

            group.querySelectorAll('.station-row').forEach((row) => {
                const show = groupMatches && (query === '' || searchIndex[row.dataset.id].includes(query));

                row.hidden = !show;
                visible += show ? 1 : 0;
            });

            group.hidden = visible === 0;
        });
    }

    search.addEventListener('input', applyFilter);

    chipsBar.querySelectorAll('.chip').forEach((chip) => {
        chip.addEventListener('click', () => {
            activeStatus = chip.dataset.status ?? null;

            chipsBar.querySelectorAll('.chip').forEach((other) => other.setAttribute('aria-pressed', String(other === chip)));
            applyFilter();
        });
    });

    // Quantas estações estão à vista no mapa: sem isso não se sabe o que ficou
    // escondido depois de fechar o modal.
    function refreshCount() {
        count.textContent = stations.filter((station) => stationsLayer.hasLayer(markersById[station.id])).length;
    }

    function setStationVisible(id, show) {
        const marker = markersById[id];
        const row = body.querySelector(`.station-row[data-id="${id}"]`);

        row.dataset.visible = String(show);
        row.querySelector('.station-eye').setAttribute('aria-pressed', String(show));

        if (show) {
            stationsLayer.addLayer(marker);
        } else {
            stationsLayer.removeLayer(marker);
        }
    }

    // O botão do grupo reflete se todas as estações dele estão à vista: clicar
    // sempre inverte esse total, mesmo depois de toques individuais terem
    // mudado algumas por baixo dele.
    function refreshGroupToggle(groupLi) {
        const rows = [...groupLi.querySelectorAll('.station-row')];
        const allVisible = rows.every((row) => row.dataset.visible === 'true');

        groupLi.querySelector('.station-group-toggle').setAttribute('aria-pressed', String(allVisible));
    }

    body.querySelectorAll('.station-eye').forEach((eye) => {
        eye.addEventListener('click', () => {
            setStationVisible(eye.dataset.id, eye.getAttribute('aria-pressed') === 'false');
            refreshGroupToggle(eye.closest('.station-group'));
            refreshCount();
        });
    });

    // Oculta ou mostra a categoria inteira de uma vez — útil numa cheia, para
    // sumir com o que não informa nada e sobrar só o que importa.
    body.querySelectorAll('.station-group-toggle').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const show = toggle.getAttribute('aria-pressed') === 'false';
            const groupLi = toggle.closest('.station-group');

            groupLi.querySelectorAll('.station-row').forEach((row) => setStationVisible(row.dataset.id, show));

            toggle.setAttribute('aria-pressed', String(show));
            refreshCount();
        });
    });

    body.querySelectorAll('.station-open').forEach((open) => {
        open.addEventListener('click', () => {
            const { id } = open.dataset;
            const marker = markersById[id];

            if (!stationsLayer.hasLayer(marker)) {
                setStationVisible(id, true);
                refreshGroupToggle(open.closest('.station-group'));
                refreshCount();
            }

            document.getElementById('stations').close();
            map.setView(marker.getLatLng(), Math.max(map.getZoom(), 13));
            showStationDetail(stationsById[id]);
        });
    });

    refreshCount();
}

/** Acrescenta um botão ao dock, à direita dos que já estão lá. */
function dockButton({ label, icon, onClick }) {
    dockBar.insertAdjacentHTML(
        'beforeend',
        `<button type="button" aria-label="${label}" title="${label}">
            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">${icon}</svg>
        </button>`,
    );

    dockBar.lastElementChild.addEventListener('click', onClick);
}

const ICON_LAYERS =
    '<path d="M12 3L21 8.5L12 14L3 8.5Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>' +
    '<path d="M3 13.5L12 19L21 13.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>';

/**
 * Estilo do mapa: painel pequeno pendurado no dock, não modal — é uma escolha
 * única entre poucas opções, e ver o mapa por trás ajuda a decidir. Cada opção
 * traz a ladrilha real da própria fonte sobre o RS, não um nome só: a paleta
 * de cada estilo se vê antes de escolher.
 */
{
    const panel = document.getElementById('basemap-panel');

    // Um ladrilho fixo sobre o RS (zoom 6), o mesmo em todo estilo — a
    // miniatura mostra a paleta, não uma região qualquer do mundo.
    const PREVIEW_TILE = { z: 6, x: 22, y: 37 };

    function previewUrl(basemap) {
        const subdomain = (basemap.subdomains ?? 'a')[0];

        return basemap.url
            .replace('{s}', subdomain)
            .replace('{z}', PREVIEW_TILE.z)
            .replace('{x}', PREVIEW_TILE.x)
            .replace('{y}', PREVIEW_TILE.y)
            .replace('{r}', '');
    }

    panel.innerHTML = `
        <p class="basemap-panel-title">Estilo do mapa</p>
        <ul class="basemap-list">
            ${BASEMAPS.map(
                (basemap) => `
                    <li>
                        <button type="button" class="basemap-item" data-key="${basemap.key}"
                                aria-pressed="${basemap.key === activeBasemap.key}">
                            <img class="basemap-preview" src="${previewUrl(basemap)}" alt="" width="40" height="40" loading="lazy">
                            <span class="basemap-label">${basemap.label}</span>
                            <svg class="basemap-check" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                                <path d="M5 12.5l4.5 4.5L19 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </li>
                `,
            ).join('')}
        </ul>
    `;

    panel.querySelectorAll('.basemap-item').forEach((item) => {
        item.addEventListener('click', () => {
            setBasemap(item.dataset.key);
            panel.querySelectorAll('.basemap-item').forEach((other) => other.setAttribute('aria-pressed', String(other === item)));
            panel.hidden = true;
        });
    });

    dockButton({
        label: 'Estilo do mapa',
        icon: ICON_LAYERS,
        onClick: () => {
            panel.hidden = !panel.hidden;
        },
    });
}

/** O que cada cor quer dizer. Sempre à vista: ler o mapa não pode depender de
 *  abrir o filtro nem de lembrar da convenção. */
const legend = L.control({ position: 'bottomleft' });

legend.onAdd = () => {
    const container = L.DomUtil.create('ul', 'legend');

    container.innerHTML = Object.entries(STATUS)
        .map(([key, { label }]) => `<li>${dot(key, 14)}${label}</li>`)
        .join('');

    L.DomEvent.disableClickPropagation(container);

    return container;
};

legend.addTo(map);

/**
 * Telefone, não tablet. Ponteiro grosso já separa toque de mouse, mas tablet
 * também é toque: o lado curto da viewport é o que distingue — celular fica em
 * torno de 430 px mesmo deitado, tablet passa de 700 px.
 */
function onPhone() {
    return (
        window.matchMedia('(pointer: coarse)').matches &&
        Math.min(window.innerWidth, window.innerHeight) <= 480
    );
}

/** Abaixo disto o fixo é de GPS e não vale seguir gastando bateria. */
const GOOD_ENOUGH_METRES = 15;

/** GPS frio leva de 30 a 60 s para travar, e o primeiro fixo é sempre de rede. */
const REFINE_TIMEOUT_MS = 60000;

/**
 * No celular vale acompanhar o refinamento: o primeiro fixo vem da rede e o
 * GPS entra depois. No desktop não há GPS para esperar — um único fixo de
 * rede é tudo que existe, watch só repetiria o mesmo valor impreciso.
 */
function locateOptions() {
    return onPhone()
        ? { watch: true, setView: false, enableHighAccuracy: true, maximumAge: 0, timeout: REFINE_TIMEOUT_MS }
        : { setView: false, timeout: REFINE_TIMEOUT_MS };
}

const ICON_LOCATE =
    '<circle cx="12" cy="12" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/>' +
    '<circle cx="12" cy="12" r="2" fill="currentColor"/>' +
    '<path d="M12 1.5v3M12 19.5v3M1.5 12h3M19.5 12h3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>';

const ICON_INFO =
    '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.9"/>' +
    '<path d="M12 11v5.2" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>' +
    '<circle cx="12" cy="7.9" r="1.25" fill="currentColor"/>';

const ICON_CAMERA =
    '<path d="M3 8.5A2.5 2.5 0 015.5 6h1.2l1-1.6A1 1 0 018.5 4h7a1 1 0 01.85.4L17.3 6h1.2A2.5 2.5 0 0121 8.5v8A2.5 2.5 0 0118.5 19h-13A2.5 2.5 0 013 16.5z" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/>' +
    '<circle cx="12" cy="12.5" r="3.4" fill="none" stroke="currentColor" stroke-width="1.9"/>';

// Localização vale em qualquer aparelho. Restringir ao celular obrigava a testar
// dentro da emulação de dispositivo do DevTools, que substitui a posição real por
// uma fixa — e era isso que fazia a marca cair sempre no mesmo lugar errado.
{
    const here = L.layerGroup().addTo(map);

    let centred = false;
    let bestAccuracy = Infinity;
    let giveUp = null;

    function stopLocating() {
        map.stopLocate();
        clearTimeout(giveUp);
        giveUp = null;
    }

    dockButton({
        label: 'Ir para minha localização',
        icon: ICON_LOCATE,
        onClick: () => {
            centred = false;
            bestAccuracy = Infinity;
            clearTimeout(giveUp);
            giveUp = setTimeout(stopLocating, REFINE_TIMEOUT_MS);

            map.locate(locateOptions());
        },
    });

    map.on('locationfound', (event) => {
        // O navegador emite fixos cada vez melhores; um fixo pior que o já obtido
        // é ruído do rádio e não deve mover a marca de volta.
        if (event.accuracy > bestAccuracy) {
            return;
        }

        bestAccuracy = event.accuracy;

        here.clearLayers();

        // Marcador distinto dos pins de estação: é a pessoa, não uma medição.
        L.circleMarker(event.latlng, {
            radius: 7,
            color: '#fff',
            weight: 3,
            fillColor: '#1f6feb',
            fillOpacity: 1,
        })
            .addTo(here)
            .bindTooltip('Você está aqui');

        if (!centred) {
            map.setView(event.latlng, 16);
            centred = true;
        }

        if (event.accuracy <= GOOD_ENOUGH_METRES) {
            stopLocating();
        }
    });

    map.on('locationerror', stopLocating);

}

/* ---- Relato com foto ---------------------------------------------------- */

{
    const form = document.getElementById('photo-form');
    const fileInput = document.getElementById('photo-file');
    const fileLabel = document.getElementById('photo-label');
    const preview = document.getElementById('photo-preview');
    const thumb = document.getElementById('photo-thumb');
    const address = document.getElementById('report-address');
    const positionText = document.getElementById('report-position');
    const consent = document.getElementById('report-consent');
    const status = document.getElementById('report-status');
    const submit = document.getElementById('report-submit');

    /** Coordenada do relato e de onde ela veio — medida ou apontada à mão. */
    let picked = null;
    let photo = null;
    let pickerMap = null;
    let pickerMarker = null;

    /** Mesmo refinamento do botão "Ir para minha localização": o primeiro fixo de
     *  GPS costuma vir da rede, e um único fixo sem refinar é o que fazia o ponto
     *  cair longe do lugar certo. */
    let bestAccuracy = Infinity;
    let giveUp = null;

    function stopReportLocating() {
        pickerMap.stopLocate();
        clearTimeout(giveUp);
        giveUp = null;
    }

    function say(message) {
        status.textContent = message;
        status.hidden = message === '';
    }

    function describePosition() {
        if (picked === null) {
            positionText.textContent = 'Arraste o ponto no mapa para marcar o local exato.';

            return;
        }

        const { lat, lng, source } = picked;
        const origin = { gps: 'pelo GPS', address: 'pelo endereço', manual: 'marcado no mapa' }[source];

        positionText.textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)} · ${origin}`;
    }

    function setPosition(latlng, source) {
        picked = { lat: latlng.lat, lng: latlng.lng, source };

        pickerMarker.setLatLng(latlng);
        pickerMap.setView(latlng, Math.max(pickerMap.getZoom(), 16));

        describePosition();
        refresh();
    }

    /** O botão só libera com foto, local e consentimento — e diz o que falta. */
    function refresh() {
        const missing = [];

        if (photo === null) {
            missing.push('a foto');
        }

        if (picked === null) {
            missing.push('o local');
        }

        if (!consent.checked) {
            missing.push('a autorização');
        }

        submit.disabled = missing.length > 0;

        if (missing.length > 0) {
            say(`Falta ${missing.join(', ').replace(/, ([^,]*)$/, ' e $1')}.`);
        } else {
            say('');
        }
    }

    function buildPickerMap() {
        pickerMap = L.map('report-map', { attributionControl: false }).setView(map.getCenter(), 12);

        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            subdomains: 'abcd',
            maxZoom: 19,
        }).addTo(pickerMap);

        pickerMarker = L.marker(map.getCenter(), { draggable: true, autoPan: true })
            .addTo(pickerMap)
            .on('dragend', () => setPosition(pickerMarker.getLatLng(), 'manual'));

        // Marcar tocando é mais direto que arrastar num mapa de 11 rem.
        pickerMap.on('click', (event) => setPosition(event.latlng, 'manual'));

        pickerMap.on('locationfound', (event) => {
            // Mesma regra do botão principal: fixo pior que o já obtido é ruído
            // do rádio, não deve puxar o ponto de volta para um lugar pior.
            if (event.accuracy > bestAccuracy) {
                return;
            }

            bestAccuracy = event.accuracy;
            setPosition(event.latlng, 'gps');

            if (event.accuracy <= GOOD_ENOUGH_METRES) {
                stopReportLocating();
            } else {
                say(`Refinando o GPS — ainda impreciso por até ${Math.round(event.accuracy)} m…`);
            }
        });

        pickerMap.on('locationerror', (event) => {
            stopReportLocating();
            say(`Não foi possível localizar: ${event.message}`);
        });
    }

    dockButton({
        label: 'Enviar foto do rio',
        icon: ICON_CAMERA,
        onClick: () => {
            // Sem clique fora para fechar aqui: é formulário com dado (foto,
            // local) — um toque perdido no mapa não pode descartar o que a
            // pessoa já preencheu.
            if (form.open) {
                form.close();

                return;
            }

            form.showModal();

            if (pickerMap === null) {
                buildPickerMap();
            }

            // O Leaflet mede o container ao criar; dentro de um dialog fechado ele
            // tem tamanho zero, e sem isto o mapa abre em branco.
            pickerMap.invalidateSize();
            refresh();
        },
    });

    fileInput.addEventListener('change', () => {
        const [file] = fileInput.files;

        if (file === undefined) {
            return;
        }

        photo = file;
        thumb.src = URL.createObjectURL(file);
        preview.hidden = false;
        fileLabel.textContent = file.name;
        refresh();
    });

    document.getElementById('photo-clear').addEventListener('click', () => {
        URL.revokeObjectURL(thumb.src);
        photo = null;
        fileInput.value = '';
        preview.hidden = true;
        fileLabel.textContent = 'Tirar foto ou escolher do aparelho';
        refresh();
    });

    document.getElementById('report-locate').addEventListener('click', () => {
        say('Localizando…');

        bestAccuracy = Infinity;
        clearTimeout(giveUp);
        giveUp = setTimeout(stopReportLocating, REFINE_TIMEOUT_MS);

        pickerMap.locate(locateOptions());
    });

    const CEP_PATTERN = /^\d{5}-?\d{3}$/;

    /**
     * Resolve CEP em logradouro via ViaCEP — gratuito, sem chave, mesma linha do
     * resto do projeto. Devolve null para CEP inexistente; string vazia nunca:
     * localidade e UF sempre vêm preenchidos quando o CEP é válido.
     */
    async function resolveCep(cep) {
        const digits = cep.replace(/\D/g, '');
        const data = await fetch(`https://viacep.com.br/ws/${digits}/json/`).then((response) => response.json());

        if (data.erro) {
            return null;
        }

        return [data.logradouro, data.bairro, data.localidade, data.uf].filter(Boolean).join(', ');
    }

    async function findAddress() {
        const query = address.value.trim();

        if (query === '') {
            return;
        }

        say('Buscando endereço…');

        try {
            let geocodeQuery = `${query}, Rio Grande do Sul, Brasil`;

            // CEP não é endereço: o Nominatim não tem cobertura de CEP no Brasil,
            // então o número sozinho nunca batia com nada. O ViaCEP (gratuito, sem
            // chave) resolve o CEP em logradouro antes de geocodificar.
            if (CEP_PATTERN.test(query)) {
                const resolved = await resolveCep(query);

                if (resolved === null) {
                    say('CEP não encontrado. Confira o número, ou marque no mapa.');

                    return;
                }

                geocodeQuery = `${resolved}, Brasil`;
            }

            const url = new URL('https://nominatim.openstreetmap.org/search');

            url.search = new URLSearchParams({
                q: geocodeQuery,
                format: 'json',
                limit: '1',
            });

            const [found] = await fetch(url).then((response) => response.json());

            if (found === undefined) {
                say('Endereço não encontrado. Tente incluir a cidade, ou marque no mapa.');

                return;
            }

            setPosition(L.latLng(Number(found.lat), Number(found.lon)), 'address');
            say('Endereço encontrado. Confira o ponto no mapa antes de enviar.');
        } catch {
            say('A busca de endereço falhou. Marque o ponto no mapa.');
        }
    }

    document.getElementById('report-find').addEventListener('click', findAddress);

    // Enter no campo busca; sem isto o dialog fecha, que é o padrão do formulário.
    address.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            findAddress();
        }
    });

    consent.addEventListener('change', refresh);

    function resetForm() {
        photo = null;
        picked = null;
        fileInput.value = '';
        preview.hidden = true;
        fileLabel.textContent = 'Tirar foto ou escolher do aparelho';
        consent.checked = false;
        address.value = '';
        describePosition();
        refresh();
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    submit.addEventListener('click', async () => {
        submit.disabled = true;
        say('Enviando…');

        const body = new FormData();
        body.append('photo', photo);
        body.append('latitude', picked.lat);
        body.append('longitude', picked.lng);
        body.append('position_source', picked.source);
        body.append('consent', consent.checked ? '1' : '0');

        try {
            const response = await fetch('/api/reports', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                body,
            });

            if (response.status === 429) {
                say('Muitos relatos enviados em pouco tempo. Tente de novo mais tarde.');
                submit.disabled = false;

                return;
            }

            if (response.status === 422) {
                const { message } = await response.json();
                say(message ?? 'Não foi possível enviar. Confira os dados e tente de novo.');
                submit.disabled = false;

                return;
            }

            if (!response.ok) {
                throw new Error('request failed');
            }

            // O relato só entra no mapa depois de aprovado — reseta o formulário
            // em vez de já mostrar o pino, para não sugerir uma publicação que
            // ainda não aconteceu.
            resetForm();
            say('Relato enviado — obrigado. Ele passa por uma checagem antes de aparecer no mapa.');
        } catch {
            say('Falha ao enviar. Confira sua conexão e tente de novo.');
            submit.disabled = false;
        }
    });
}

// Por último no dock, à direita dos demais: o que é um ponto e quem mediu é a
// pergunta que segue a primeira olhada no mapa, não a primeira ação nele.
dockButton({
    label: 'Sobre os dados',
    icon: ICON_INFO,
    onClick: () => toggleDialog(document.getElementById('about')),
});

const ICON_HEART =
    '<path d="M12 20.2C7.8 17.4 3 13.4 3 8.9 3 6.2 5.1 4 7.7 4c1.7 0 3.2.9 4.3 2.3C13.1 4.9 14.6 4 16.3 4 18.9 4 21 6.2 21 8.9c0 4.5-4.8 8.5-9 11.3z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>';

dockButton({
    label: 'Apoiar o projeto',
    icon: ICON_HEART,
    onClick: () => toggleDialog(document.getElementById('donate')),
});

/* ---- Doação por Pix -------------------------------------------------------

   QR Code Pix estático com valor livre: a pessoa escolhe o quanto, o valor
   entra no próprio payload (campo 54 do BR Code) — sem servidor, sem conta a
   mais, só a chave que já existe. */
{
    const pix = JSON.parse(document.getElementById('pix-data').textContent);

    // Sem chave, o Blade já renderizou só o aviso — nenhum destes elementos existe.
    if (pix.key) {
        const amountsBar = document.getElementById('donate-amounts');
        const valueInput = document.getElementById('donate-value');
        const qrBox = document.getElementById('donate-qr');
        const copyButton = document.getElementById('donate-copy');
        const status = document.getElementById('donate-status');

        let payload = '';

        /**
         * CRC16/CCITT-FALSE (poli 0x1021, início 0xFFFF) — o checksum exigido
         * pelo padrão BR Code do Banco Central, calculado sobre o payload
         * inteiro com o campo 63 ainda vazio.
         */
        function crc16(text) {
            let crc = 0xffff;

            for (let i = 0; i < text.length; i++) {
                crc ^= text.charCodeAt(i) << 8;

                for (let bit = 0; bit < 8; bit++) {
                    crc = (crc & 0x8000) !== 0 ? ((crc << 1) ^ 0x1021) & 0xffff : (crc << 1) & 0xffff;
                }
            }

            return crc.toString(16).toUpperCase().padStart(4, '0');
        }

        const tlv = (id, value) => `${id}${String(value.length).padStart(2, '0')}${value}`;

        /** Monta o BR Code (EMV QRCPS-MPM) Pix estático, com valor opcional. */
        function pixPayload(amount) {
            const merchantAccount = tlv('00', 'br.gov.bcb.pix') + tlv('01', pix.key);
            const additionalData = tlv('05', '***');

            const fields =
                tlv('00', '01') +
                tlv('26', merchantAccount) +
                tlv('52', '0000') +
                tlv('53', '986') +
                (amount > 0 ? tlv('54', amount.toFixed(2)) : '') +
                tlv('58', 'BR') +
                tlv('59', pix.name.slice(0, 25)) +
                tlv('60', pix.city.slice(0, 15)) +
                tlv('62', additionalData);

            const withCrcId = `${fields}6304`;

            return withCrcId + crc16(withCrcId);
        }

        function say(message) {
            status.hidden = message === '';
            status.textContent = message;
        }

        function render() {
            const amount = parseFloat(valueInput.value.replace(',', '.'));

            if (!(amount > 0)) {
                qrBox.hidden = true;
                copyButton.hidden = true;
                say('');

                return;
            }

            payload = pixPayload(amount);

            const qr = qrcode(0, 'M');
            qr.addData(payload);
            qr.make();

            qrBox.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2 });
            qrBox.hidden = false;
            copyButton.hidden = false;
            say('');
        }

        function syncChips() {
            amountsBar.querySelectorAll('.chip').forEach((chip) => {
                chip.setAttribute('aria-pressed', String(chip.dataset.amount === valueInput.value));
            });
        }

        amountsBar.querySelectorAll('.chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                valueInput.value = chip.dataset.amount;
                syncChips();
                render();
            });
        });

        valueInput.addEventListener('input', () => {
            syncChips();
            render();
        });

        // A imagem é o caminho principal, mas o código copiado é o mesmo texto
        // que o app do banco lê — funciona mesmo se o QR não escanear bem.
        copyButton.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(payload);
                say('Código copiado — cole no seu app do banco.');
            } catch {
                say('Não foi possível copiar automaticamente. Selecione o valor e copie pelo QR Code.');
            }
        });
    }
}

// O aviso reaparece a cada nova sessão: quem chega numa emergência precisa saber
// que a página não é oficial, mesmo tendo dispensado o aviso semanas atrás.
const disclaimer = document.getElementById('disclaimer');

if (sessionStorage.getItem('disclaimer-seen') === null) {
    disclaimer.showModal();
    disclaimer.addEventListener('close', () => sessionStorage.setItem('disclaimer-seen', '1'), { once: true });
}
