import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/**
 * Cada estado tem cor, forma e texto próprios. A cor nunca decide sozinha:
 * em daltonismo, sob sol forte ou em impressão P&B, a forma ainda distingue.
 */
const STATUS = {
    normal: { label: 'Normal', color: '#0ca30c', shape: 'check', size: 26 },
    alert: { label: 'Alerta', color: '#fab219', shape: 'triangle', size: 28 },
    critical: { label: 'Crítico', color: '#d03b3b', shape: 'octagon', size: 30 },
    unrated: { label: 'Medindo, sem cota de referência', color: '#2a78d6', shape: 'square', size: 24 },
    stale: { label: 'Sensor sem reportar', color: '#8a5a00', shape: 'dashed', size: 26 },
    // Sem nível medido, mas com vazão estimada por modelo — dado real, natureza
    // diferente: por isso losango, e nunca as cores de alerta.
    modeled: { label: 'Vazão estimada', color: '#5b7fa6', shape: 'diamond', size: 16 },
    // Estação catalogada da qual não recebemos nada: presença, não alarme.
    unmonitored: { label: 'Estação mapeada, sem leitura', color: '#8c8a85', shape: 'dot', size: 11 },
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
    sigdc: 'Defesa Civil (SIGDC)',
    snirh: 'Inventário ANA/SNIRH',
};

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

function readingBlock(station) {
    const { reading, unit } = station;

    if (reading === null) {
        return `
            <p class="popup-empty">Sem nível medido</p>
            <p class="popup-note">Estação catalogada pela ANA — ainda não recebemos
            a medição de nível dela.</p>
        `;
    }

    const measured = dateFormat.format(new Date(reading.measuredAt));

    // Leitura velha aparece — mas nunca sem o carimbo que a denuncia como velha.
    const staleWarning = reading.stale
        ? `<p class="popup-stale">O sensor parou de reportar. Esta leitura é antiga e
           pode não representar o rio agora.</p>`
        : '';

    return `
        <p class="popup-value">${reading.value.toFixed(2).replace('.', ',')}<span>${escape(unit ?? 'm')}</span></p>
        <p class="popup-note">Medido em ${measured}</p>
        ${staleWarning}
    `;
}

function levelsBlock(station) {
    const levels = [];

    if (station.alertLevel !== null) {
        levels.push(`alerta ${station.alertLevel.toFixed(2).replace('.', ',')}`);
    }

    if (station.criticalLevel !== null) {
        levels.push(`crítico ${station.criticalLevel.toFixed(2).replace('.', ',')}`);
    }

    return levels.length > 0
        ? `<p class="popup-note">Cotas: ${levels.join(' · ')} ${escape(station.unit ?? 'm')}</p>`
        : `<p class="popup-note">Sem cota de referência publicada.</p>`;
}

const TREND_LABEL = {
    rising: 'subindo nos próximos dias',
    falling: 'baixando nos próximos dias',
    steady: 'estável nos próximos dias',
};

/**
 * Bloco separado e rotulado: vazão de modelo não é medição da estação, e num app
 * de cheia confundir as duas é o erro mais caro possível.
 */
function dischargeBlock(station) {
    if (station.discharge === null) {
        return '';
    }

    const { value, trend } = station.discharge;
    const trendText = trend === null ? '' : ` · ${TREND_LABEL[trend]}`;

    return `
        <p class="popup-modeled">
            <strong>${value.toLocaleString('pt-BR', { maximumFractionDigits: 1 })} m³/s</strong>
            de vazão estimada${trendText}
        </p>
        <p class="popup-note">Estimativa do modelo GloFAS para este ponto do rio —
        não é medição da estação.</p>
    `;
}

function popup(station) {
    const status = STATUS[station.status] ?? STATUS.unmonitored;
    const place = [station.river, station.municipality].filter(Boolean).join(' · ');

    return `
        <h2 class="popup-title">${escape(station.name)}</h2>
        ${place ? `<p class="popup-place">${escape(place)}</p>` : ''}
        <p class="popup-status" style="--status:${status.color}">${status.label}</p>
        ${readingBlock(station)}
        ${levelsBlock(station)}
        ${dischargeBlock(station)}
        <p class="popup-source">Fonte: ${escape(SOURCE_LABEL[station.source] ?? station.source)}</p>
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

L.control.zoom({ position: 'bottomright' }).addTo(map);

const legend = L.control({ position: 'bottomleft' });

legend.onAdd = () => {
    const live = stations.filter((station) => station.reading !== null).length;
    const modeled = stations.filter((station) => station.status === 'modeled').length;

    const swatch = (key, size = 16) => {
        const { color, shape } = STATUS[key];

        return `<svg viewBox="0 0 24 24" width="${size}" height="${size}" aria-hidden="true">${SHAPES[shape].replaceAll('COLOR', color)}</svg>`;
    };

    const entries = ['normal', 'alert', 'critical', 'stale']
        .map((key) => `<li>${swatch(key)}${STATUS[key].label}</li>`)
        .join('');

    const container = L.DomUtil.create('div', 'legend');
    container.innerHTML = `
        <p class="legend-heading">Nível medido <span>${live}</span></p>
        <ul class="legend-list">${entries}</ul>
        <p class="legend-heading legend-heading--spaced">Só vazão estimada <span>${modeled}</span></p>
        <p class="legend-note">
            ${swatch('modeled', 13)} Modelo GloFAS, não é medição da estação
        </p>
        <p class="legend-credit">© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a></p>
    `;

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

stations.forEach((station) => {
    const status = STATUS[station.status] ?? STATUS.unknown;

    L.marker([station.latitude, station.longitude], {
        icon: icon(station.status),
        title: `${station.name} — ${status.label}`,
        alt: `${station.name} — ${status.label}`,
        riseOnHover: true,
    })
        .addTo(map)
        .bindPopup(popup(station), { maxWidth: 260 });
});
