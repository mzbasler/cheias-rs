import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/**
 * Cada estado tem cor, forma e texto próprios. A cor nunca decide sozinha:
 * em daltonismo, sob sol forte ou em impressão P&B, a forma ainda distingue.
 */
const STATUS = {
    critical: { label: 'Inundação', color: '#d03b3b', shape: 'octagon', size: 30 },
    alert: { label: 'Alerta', color: '#fab219', shape: 'triangle', size: 28 },
    normal: { label: 'Normal', color: '#0ca30c', shape: 'check', size: 26 },
    unknown: { label: 'Sem leitura', color: '#8c8a85', shape: 'dashed', size: 24 },
};

const SHAPES = {
    check: '<circle cx="12" cy="12" r="9" fill="COLOR" stroke="#fff" stroke-width="2.5"/><path d="M8 12.3l2.6 2.6L16 9.5" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>',
    triangle: '<path d="M12 2.5L22.5 20.5H1.5z" fill="COLOR" stroke="#fff" stroke-width="2.2" stroke-linejoin="round"/><path d="M12 9v4.4" stroke="#3d2c00" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="16.8" r="1.3" fill="#3d2c00"/>',
    octagon: '<path d="M8.2 2.5h7.6l5.7 5.7v7.6l-5.7 5.7H8.2l-5.7-5.7V8.2z" fill="COLOR" stroke="#fff" stroke-width="2.2" stroke-linejoin="round"/><path d="M12 7.5v5.2" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/><circle cx="12" cy="16.4" r="1.4" fill="#fff"/>',
    dashed: '<circle cx="12" cy="12" r="9" fill="#fff" stroke="COLOR" stroke-width="2.6" stroke-dasharray="3.6 3.2"/><path d="M12 7.6v5" stroke="COLOR" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="16.2" r="1.3" fill="COLOR"/>',
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

/**
 * O que o morador deve fazer em cada estado. Sai do estado, não do número: quem
 * abre isto durante cheia precisa de instrução, não de interpretação.
 */
const ACTION = {
    critical:
        'Risco imediato. A cota de inundação foi atingida — siga as instruções da Defesa Civil e ligue 199.',
    alert: 'Risco elevado. A cota de alerta foi atingida — prepare-se e acompanhe as orientações da Defesa Civil.',
    normal: 'Situação normal. O nível está abaixo da cota de alerta deste ponto.',
    unknown:
        'Este ponto está sem leitura recente ou sem cota publicada. Consulte a Defesa Civil pelo 199 antes de tirar conclusões.',
};

const number = (value, digits = 2) =>
    value.toLocaleString('pt-BR', { minimumFractionDigits: digits, maximumFractionDigits: digits });

function icon(status) {
    const { color, shape, label, size } = STATUS[status] ?? STATUS.unknown;
    const half = size / 2;

    return L.divIcon({
        className: `station-pin station-pin--${status}`,
        html: `<svg viewBox="0 0 24 24" width="${size}" height="${size}" role="img" aria-label="${label}">${SHAPES[shape].replaceAll('COLOR', color)}</svg>`,
        iconSize: [size, size],
        iconAnchor: [half, half],
        popupAnchor: [0, -half + 2],
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

            <p class="card-eyebrow">O que fazer</p>
            <p class="card-action">${ACTION[station.status] ?? ACTION.unknown}</p>

            <p class="card-eyebrow">Últimas 24 horas</p>
            ${historyBlock(station)}

            <p class="card-source">${escape(SOURCE_LABEL[reading.source] ?? reading.source)}</p>
        </div>
    `;
}

const stations = JSON.parse(document.getElementById('stations-data').textContent);

const map = L.map('map', {
    center: [-29.8, -53.2], // Rio Grande do Sul
    zoom: 6,
    zoomControl: false,
    // A atribuição sai da caixa padrão e entra na legenda — exigida pela
    // licença dos tiles, então muda de lugar, não desaparece.
    attributionControl: false,
});

// Voyager do CARTO: terreno neutro, mas rios e lâminas de água em azul
// definido. O mapa padrão do OSM satura verde e laranja, competindo com as
// cores de alerta; o Positron apaga a água, que aqui é o assunto.
L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
    subdomains: 'abcd',
    maxZoom: 19,
}).addTo(map);

/**
 * Uma camada por estado, para a legenda poder ligar e desligar cada um. Numa
 * cheia, o que importa é isolar as estações em risco entre centenas de pins.
 */
const layers = {};

/**
 * Largura e altura do card ficam no CSS, não aqui. Medida em pixels no
 * carregamento não acompanha rotação nem troca de tela; `maxWidth: 0` desliga o
 * cálculo do Leaflet e deixa o layout fluido responder pela viewport.
 */
const POPUP_OPTIONS = {
    maxWidth: 0,
    minWidth: 0,
    // Folga para o card não abrir por baixo do botão de filtros nem colar no topo.
    autoPanPadding: [16, 16],
};

stations.forEach((station) => {
    const status = STATUS[station.status] ?? STATUS.unknown;

    const marker = L.marker([station.latitude, station.longitude], {
        icon: icon(station.status),
        title: `${station.name} — ${status.label}`,
        alt: `${station.name} — ${status.label}`,
        riseOnHover: true,
    });

    marker.bindPopup(popup(station), POPUP_OPTIONS);

    (layers[station.status] ??= L.layerGroup().addTo(map)).addLayer(marker);
});

/** A ordem de STATUS já vai do mais grave ao menos — a legenda a reaproveita. */
const legend = L.control({ position: 'bottomleft' });

legend.onAdd = () => {
    const container = L.DomUtil.create('div', 'legend');

    const rows = Object.keys(STATUS)
        .filter((key) => layers[key] !== undefined)
        .map((key) => {
            const { label, color, shape } = STATUS[key];

            return `
                <li>
                    <button type="button" data-status="${key}" aria-pressed="true">
                        <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true">${SHAPES[shape].replaceAll('COLOR', color)}</svg>
                        <span>${label}</span>
                        <em>${layers[key].getLayers().length}</em>
                    </button>
                </li>
            `;
        })
        .join('');

    container.innerHTML = `
        <button type="button" class="legend-toggle" aria-expanded="false" aria-controls="legend-panel">
            <svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true">
                <path d="M4 6h16M7 12h10M10 18h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            Filtros
        </button>
        <div class="legend-panel" id="legend-panel" hidden>
            <ul class="legend-list">${rows}</ul>
        </div>
        <p class="legend-credit">
            © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>
            · <a href="https://carto.com/attributions" target="_blank" rel="noopener">CARTO</a>
        </p>
    `;

    const toggle = container.querySelector('.legend-toggle');
    const panel = container.querySelector('.legend-panel');

    toggle.addEventListener('click', () => {
        panel.hidden = !panel.hidden;
        toggle.setAttribute('aria-expanded', String(!panel.hidden));
    });

    container.querySelectorAll('button[data-status]').forEach((button) => {
        button.addEventListener('click', () => {
            const layer = layers[button.dataset.status];
            const visible = map.hasLayer(layer);

            if (visible) {
                map.removeLayer(layer);
            } else {
                map.addLayer(layer);
            }

            button.setAttribute('aria-pressed', String(!visible));
        });
    });

    // Sem isso, arrastar ou rolar sobre a legenda move o mapa por baixo dela.
    L.DomEvent.disableClickPropagation(container);
    L.DomEvent.disableScrollPropagation(container);

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

/** Botão flutuante ancorado no canto, no padrão de controle do Leaflet. */
function floatingButton({ label, icon, onClick }) {
    const control = L.control({ position: 'bottomright' });

    control.onAdd = () => {
        const container = L.DomUtil.create('div', 'fab');

        container.innerHTML = `
            <button type="button" aria-label="${label}" title="${label}">
                <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">${icon}</svg>
            </button>
        `;

        container.querySelector('button').addEventListener('click', onClick);

        L.DomEvent.disableClickPropagation(container);

        return container;
    };

    control.addTo(map);

    return control;
}

const ICON_LOCATE =
    '<circle cx="12" cy="12" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/>' +
    '<circle cx="12" cy="12" r="2" fill="currentColor"/>' +
    '<path d="M12 1.5v3M12 19.5v3M1.5 12h3M19.5 12h3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>';

const ICON_CAMERA =
    '<path d="M3 8.5A2.5 2.5 0 015.5 6h1.2l1-1.6A1 1 0 018.5 4h7a1 1 0 01.85.4L17.3 6h1.2A2.5 2.5 0 0121 8.5v8A2.5 2.5 0 0118.5 19h-13A2.5 2.5 0 013 16.5z" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/>' +
    '<circle cx="12" cy="12.5" r="3.4" fill="none" stroke="currentColor" stroke-width="1.9"/>';

// Localização vale em qualquer aparelho. Restringir ao celular obrigava a testar
// dentro da emulação de dispositivo do DevTools, que substitui a posição real por
// uma fixa — e era isso que fazia a marca cair sempre no mesmo lugar errado.
{
    const here = L.layerGroup().addTo(map);

    /** Abaixo disto o fixo é de GPS e não vale seguir gastando bateria. */
    const GOOD_ENOUGH_METRES = 15;

    /**
     * GPS frio leva de 30 a 60 s para travar, e o primeiro fixo é sempre de rede.
     */
    const REFINE_TIMEOUT_MS = 60000;

    let centred = false;
    let bestAccuracy = Infinity;
    let giveUp = null;

    function stopLocating() {
        map.stopLocate();
        clearTimeout(giveUp);
        giveUp = null;
    }

    floatingButton({
        label: 'Ir para minha localização',
        icon: ICON_LOCATE,
        onClick: () => {
            centred = false;
            bestAccuracy = Infinity;
            clearTimeout(giveUp);
            giveUp = setTimeout(stopLocating, REFINE_TIMEOUT_MS);

            // No celular vale acompanhar o refinamento: o primeiro fixo vem da rede
            // e o GPS entra depois. No desktop não há GPS para esperar.
            map.locate(
                onPhone()
                    ? {
                        watch: true,
                        setView: false,
                        enableHighAccuracy: true,
                        maximumAge: 0,
                        timeout: REFINE_TIMEOUT_MS,
                    }
                    : { setView: false, timeout: REFINE_TIMEOUT_MS },
            );
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

// A foto depende de câmera na mão, então esse segue só no celular.
if (onPhone()) {
    const photoNotice = document.getElementById('photo-notice');

    floatingButton({
        label: 'Enviar foto do rio',
        icon: ICON_CAMERA,
        onClick: () => photoNotice.showModal(),
    });
}

// O aviso reaparece a cada nova sessão: quem chega numa emergência precisa saber
// que a página não é oficial, mesmo tendo dispensado o aviso semanas atrás.
const disclaimer = document.getElementById('disclaimer');

if (sessionStorage.getItem('disclaimer-seen') === null) {
    disclaimer.showModal();
    disclaimer.addEventListener('close', () => sessionStorage.setItem('disclaimer-seen', '1'), { once: true });
}
