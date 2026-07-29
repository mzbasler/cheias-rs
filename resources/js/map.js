import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/**
 * Cada estado tem cor, forma e texto próprios. A cor nunca decide sozinha:
 * em daltonismo, sob sol forte ou em impressão P&B, a forma ainda distingue.
 */
const STATUS = {
    normal: { label: 'Normal', color: '#0ca30c', shape: 'check' },
    alert: { label: 'Alerta', color: '#fab219', shape: 'triangle' },
    critical: { label: 'Crítico', color: '#d03b3b', shape: 'octagon' },
    unknown: { label: 'Sem leitura', color: '#898781', shape: 'dashed' },
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

const SOURCE_LABEL = {
    sigdc: 'Defesa Civil (SIGDC)',
    snirh: 'Inventário ANA/SNIRH',
};

function icon(status) {
    const { color, shape, label } = STATUS[status] ?? STATUS.unknown;

    return L.divIcon({
        className: 'station-pin',
        html: `<svg viewBox="0 0 24 24" width="26" height="26" role="img" aria-label="${label}">${SHAPES[shape].replaceAll('COLOR', color)}</svg>`,
        iconSize: [26, 26],
        iconAnchor: [13, 13],
        popupAnchor: [0, -12],
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
            <p class="popup-empty">Sem leitura</p>
            <p class="popup-note">Esta estação está mapeada, mas ainda não temos
            leitura ao vivo dela.</p>
        `;
    }

    const measured = dateFormat.format(new Date(reading.measuredAt));

    // Leitura velha aparece — mas nunca sem o carimbo que a denuncia como velha.
    const staleWarning = reading.stale
        ? `<p class="popup-stale">Leitura antiga — pode não representar o rio agora.</p>`
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

function popup(station) {
    const status = STATUS[station.status] ?? STATUS.unknown;
    const place = [station.river, station.municipality].filter(Boolean).join(' · ');

    return `
        <h2 class="popup-title">${escape(station.name)}</h2>
        ${place ? `<p class="popup-place">${escape(place)}</p>` : ''}
        <p class="popup-status" style="--status:${status.color}">${status.label}</p>
        ${readingBlock(station)}
        ${levelsBlock(station)}
        <p class="popup-source">Fonte: ${escape(SOURCE_LABEL[station.source] ?? station.source)}</p>
    `;
}

const stations = JSON.parse(document.getElementById('stations-data').textContent);

const map = L.map('map', {
    center: [-29.8, -53.2], // Rio Grande do Sul
    zoom: 6,
    zoomControl: false,
});

L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
}).addTo(map);

L.control.zoom({ position: 'bottomright' }).addTo(map);

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
