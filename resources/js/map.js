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
        ? '<div class="tank-empty">Sem leitura recente</div>'
        : `<div class="water" style="height:${percent(station.reading.value, top)}%"></div>`;

    return `
        <div class="gauge">
            <div class="gauge-scale" aria-hidden="true">
                <i>${number(top, 1)}</i>
                <i>0,0</i>
            </div>
            <div class="tank" role="img" aria-label="${escape(station.name)}: ${number(station.reading.value)} m numa escala até ${number(top, 1)} m">
                ${bands}
                ${water}
                ${marks}
            </div>
        </div>
    `;
}

/**
 * A única informação de cota fora do detalhe: quanto falta para o próximo limiar,
 * ou quanto já passou. É o número que um leigo precisa — o resto é técnico e vai
 * para dentro do recolhível.
 */
function headline(station) {
    const { value } = station.reading;
    const alert = station.alertLevel;
    const critical = station.criticalLevel;

    if (critical !== null && value >= critical) {
        return `<p class="popup-headline is-critical">Passou <strong>${number(value - critical)} m</strong> da cota de inundação</p>`;
    }

    if (critical !== null) {
        const reached = alert !== null && value >= alert;

        return `<p class="popup-headline${reached ? ' is-alert' : ''}">
            Faltam <strong>${number(critical - value)} m</strong> para a cota de inundação
        </p>`;
    }

    if (alert !== null) {
        return value >= alert
            ? `<p class="popup-headline is-alert">Passou <strong>${number(value - alert)} m</strong> da cota de alerta</p>`
            : `<p class="popup-headline">Faltam <strong>${number(alert - value)} m</strong> para a cota de alerta</p>`;
    }

    return '<p class="popup-headline">Sem cota de referência publicada</p>';
}

function trendLabel(change) {
    if (change === null) {
        return '';
    }

    const { value, hours } = change;
    const arrow = value > 0.005 ? '▲' : value < -0.005 ? '▼' : '—';
    const sign = value > 0 ? '+' : '';

    return `<span class="popup-trend" data-rising="${value > 0.005}">${arrow} ${sign}${number(value)} m em ${number(hours, 1)} h</span>`;
}

/** Medidor na largura toda, leitura embaixo dele. */
function measuredBlock(station) {
    const { reading, unit } = station;

    // Leitura velha aparece — mas nunca sem o carimbo que a denuncia como velha.
    const staleWarning = reading.stale
        ? '<p class="popup-stale">Transmissão interrompida · leitura desatualizada</p>'
        : '';

    return `
        ${gauge(station)}
        <div class="popup-reading">
            <p class="popup-value">${number(reading.value)}<span>${escape(unit ?? 'm')}</span></p>
            ${trendLabel(station.change)}
            <span class="popup-when">${dateFormat.format(new Date(reading.measuredAt))}</span>
        </div>
        ${staleWarning}
        ${headline(station)}
    `;
}

/** Quanto falta — ou quanto já passou — de cada cota. */
function levelTable(station) {
    const marks = [
        ['Alerta', station.alertLevel, STATUS.alert.color],
        ['Inundação', station.criticalLevel, STATUS.critical.color],
    ].filter(([, value]) => value !== null);

    if (marks.length === 0) {
        return '<p class="popup-note">Sem cota de referência publicada.</p>';
    }

    const rows = marks
        .map(([label, value, color]) => {
            const gap = value - station.reading.value;
            const reached = gap <= 0;

            return `
                <li class="${reached ? 'is-reached' : ''}" style="--mark:${color}">
                    <span>${label}</span>
                    <strong>${number(value)} m</strong>
                    <em>${reached ? `passou ${number(-gap)} m` : `faltam ${number(gap)} m`}</em>
                </li>
            `;
        })
        .join('');

    return `<ul class="popup-scale">${rows}</ul>`;
}

/**
 * Gráfico das últimas 24 h. Linha, não barra: a variação é de centímetros e
 * barra com base truncada mentiria sobre a proporção. A faixa vertical usada vai
 * declarada em texto embaixo, porque o eixo não começa no zero.
 */
function historyBlock(station) {
    const series = station.history ?? [];

    if (series.length < 2) {
        return '<p class="popup-note">Sem leituras suficientes nas últimas 24 horas.</p>';
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
        <svg class="popup-chart" viewBox="0 0 100 40" preserveAspectRatio="none" role="img"
             aria-label="Nível variou de ${number(low)} a ${number(high)} metros nas últimas 24 horas">
            <polyline points="${points}" vector-effect="non-scaling-stroke"/>
        </svg>
        <p class="popup-chart-axis"><span>${first}</span><span>${series.length} leituras</span><span>${last}</span></p>
        <p class="popup-note">Escala do gráfico: ${number(floor)} a ${number(high + pad)} m —
        não começa no zero. Mín ${number(low)} · máx ${number(high)} m.</p>
    `;
}

function popup(station) {
    const status = STATUS[station.status] ?? STATUS.unknown;
    const place = [station.river, station.municipality].filter(Boolean).join(' · ');
    const source = station.reading.source;

    return `
        <header class="popup-head" style="--status:${status.color}">
            <h2 class="popup-title">${escape(station.name)}</h2>
            ${place ? `<p class="popup-place">${escape(place)}</p>` : ''}
            <p class="popup-status">${status.label}</p>
        </header>

        ${measuredBlock(station)}

        <h3>O que fazer</h3>
        <p class="popup-action">${ACTION[station.status] ?? ACTION.unknown}</p>

        <h3>Cotas deste ponto</h3>
        ${levelTable(station)}

        <h3>Últimas 24 horas</h3>
        ${historyBlock(station)}

        <p class="popup-source">${escape(SOURCE_LABEL[source] ?? source)}</p>
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
 * O Leaflet dimensiona o popup pelo conteúdo: sem uma largura mínima alta, no
 * desktop ele ficava estreito e o card voltava a ser uma coluna. No celular a
 * largura fica presa à viewport, com folga para não colar nas bordas.
 */
const POPUP_WIDTH = (() => {
    const available = window.innerWidth - 40;

    return {
        minWidth: Math.max(236, Math.min(340, available)),
        maxWidth: Math.max(236, Math.min(420, available)),
    };
})();

stations.forEach((station) => {
    const status = STATUS[station.status] ?? STATUS.unknown;

    const marker = L.marker([station.latitude, station.longitude], {
        icon: icon(station.status),
        title: `${station.name} — ${status.label}`,
        alt: `${station.name} — ${status.label}`,
        riseOnHover: true,
    });

    marker.bindPopup(popup(station), POPUP_WIDTH);

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

// O aviso reaparece a cada nova sessão: quem chega numa emergência precisa saber
// que a página não é oficial, mesmo tendo dispensado o aviso semanas atrás.
const disclaimer = document.getElementById('disclaimer');

if (sessionStorage.getItem('disclaimer-seen') === null) {
    disclaimer.showModal();
    disclaimer.addEventListener('close', () => sessionStorage.setItem('disclaimer-seen', '1'), { once: true });
}
