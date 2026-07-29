import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/**
 * Cada estado tem cor, forma e texto próprios. A cor nunca decide sozinha:
 * em daltonismo, sob sol forte ou em impressão P&B, a forma ainda distingue.
 */
const STATUS = {
    critical: { label: 'Inundação', color: '#d03b3b', shape: 'octagon', size: 30 },
    alert: { label: 'Alerta', color: '#e2711d', shape: 'diamond', size: 28 },
    attention: { label: 'Atenção', color: '#fab219', shape: 'triangle', size: 27 },
    normal: { label: 'Normal', color: '#0ca30c', shape: 'check', size: 26 },
    stale: { label: 'Sem transmissão', color: '#8a5a00', shape: 'dashed', size: 26 },
    unrated: { label: 'Sem cota', color: '#2a78d6', shape: 'square', size: 24 },
    unmonitored: { label: 'Sem leitura', color: '#8c8a85', shape: 'dot', size: 12 },
};

const SHAPES = {
    check: '<circle cx="12" cy="12" r="9" fill="COLOR" stroke="#fff" stroke-width="2.5"/><path d="M8 12.3l2.6 2.6L16 9.5" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>',
    triangle: '<path d="M12 2.5L22.5 20.5H1.5z" fill="COLOR" stroke="#fff" stroke-width="2.2" stroke-linejoin="round"/><path d="M12 9v4.4" stroke="#3d2c00" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="16.8" r="1.3" fill="#3d2c00"/>',
    octagon: '<path d="M8.2 2.5h7.6l5.7 5.7v7.6l-5.7 5.7H8.2l-5.7-5.7V8.2z" fill="COLOR" stroke="#fff" stroke-width="2.2" stroke-linejoin="round"/><path d="M12 7.5v5.2" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/><circle cx="12" cy="16.4" r="1.4" fill="#fff"/>',
    square: '<rect x="3.5" y="3.5" width="17" height="17" rx="2.5" fill="COLOR" stroke="#fff" stroke-width="2.4"/>',
    dashed: '<circle cx="12" cy="12" r="9" fill="#fff" stroke="COLOR" stroke-width="2.6" stroke-dasharray="3.6 3.2"/><path d="M12 7.6v5" stroke="COLOR" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="16.2" r="1.3" fill="COLOR"/>',
    diamond: '<path d="M12 2.8L21.2 12 12 21.2 2.8 12z" fill="COLOR" stroke="#fff" stroke-width="2.6" stroke-linejoin="round"/>',
    // Sem ícone de aviso: é um ponto no catálogo, não uma ocorrência.
    dot: '<circle cx="12" cy="12" r="6.5" fill="COLOR" stroke="#fff" stroke-width="3"/>',
};

const dateFormat = new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
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

function icon(status) {
    const { color, shape, label, size } = STATUS[status] ?? STATUS.unmonitored;
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

function measuredBlock(station) {
    const { reading, unit } = station;

    // Leitura velha aparece — mas nunca sem o carimbo que a denuncia como velha.
    const staleWarning = reading.stale
        ? '<p class="popup-stale">Transmissão interrompida · leitura desatualizada</p>'
        : '';

    return `
        <p class="popup-kind">Nível</p>
        <p class="popup-value">${number(reading.value)}<span>${escape(unit ?? 'm')}</span></p>
        <p class="popup-note">${dateFormat.format(new Date(reading.measuredAt))}</p>
        ${staleWarning}
        ${levelScale(station)}
    `;
}

/**
 * Régua das cotas oficiais com a leitura marcada. O número sozinho não diz nada a
 * quem não conhece o rio; a distância até a inundação diz.
 */
function levelScale(station) {
    const marks = [
        ['Atenção', station.attentionLevel, STATUS.attention.color],
        ['Alerta', station.alertLevel, STATUS.alert.color],
        ['Inundação', station.criticalLevel, STATUS.critical.color],
    ].filter(([, value]) => value !== null);

    if (marks.length === 0) {
        return '<p class="popup-note">Sem cota de referência publicada.</p>';
    }

    // Só chamada de dentro de measuredBlock, que já garantiu haver leitura.
    const rows = marks
        .map(([label, value, color]) => {
            const reached = station.reading.value >= value;

            return `
                <li class="${reached ? 'is-reached' : ''}" style="--mark:${color}">
                    <span>${label}</span>
                    <strong>${number(value)} m</strong>
                    <em>${reached ? 'atingida' : `faltam ${number(value - station.reading.value)} m`}</em>
                </li>
            `;
        })
        .join('');

    return `<ul class="popup-scale">${rows}</ul>`;
}

function popup(station) {
    const status = STATUS[station.status] ?? STATUS.unmonitored;
    const place = [station.river, station.municipality].filter(Boolean).join(' · ');
    const source = station.reading.source;

    return `
        <h2 class="popup-title">${escape(station.name)}</h2>
        ${place ? `<p class="popup-place">${escape(place)}</p>` : ''}
        <p class="popup-status" style="--status:${status.color}">${status.label}</p>
        ${measuredBlock(station)}
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

L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18 }).addTo(map);

/**
 * Uma camada por estado, para a legenda poder ligar e desligar cada um. Numa
 * cheia, o que importa é isolar as estações em risco entre centenas de pins.
 */
const layers = {};

stations.forEach((station) => {
    const status = STATUS[station.status] ?? STATUS.unmonitored;

    const marker = L.marker([station.latitude, station.longitude], {
        icon: icon(station.status),
        title: `${station.name} — ${status.label}`,
        alt: `${station.name} — ${status.label}`,
        riseOnHover: true,
    });

    // Largura mínima: sem ela o Leaflet aperta o popup até a régua de cotas
    // quebrar linha. Máxima para não virar uma faixa larga no celular.
    marker.bindPopup(popup(station), { minWidth: 244, maxWidth: 288 });

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
        <p class="legend-credit">© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a></p>
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
