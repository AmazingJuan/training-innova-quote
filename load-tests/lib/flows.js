// Lo que hace una persona en el sitio público.
import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Counter } from 'k6/metrics';
import { assetUrls, pageOf, xsrfToken } from './inertia.js';

export const BASE_URL = (__ENV.BASE_URL || 'https://cotizaciones.trainingcorporation.com.co').replace(/\/+$/, '');
const UNITS = ['auditoria', 'consultoria', 'formacion'];
// ASSETS=0 no descarga JS/CSS (si la conexión de quien ejecuta k6 es el límite).
const WITH_ASSETS = __ENV.ASSETS !== '0';
// PENSAR=0.2 reduce las pausas al 20 % (usuarios más "rápidos" que una persona real).
const THINK = Number(__ENV.PENSAR || 1);
// ADJUNTO_KB=500 adjunta un PDF de 500 KB a cada solicitud (prueba la subida de documentos).
const ATTACHMENT_KB = Number(__ENV.ADJUNTO_KB || 0);

export const solicitudesEnviadas = new Counter('solicitudes_enviadas');

const pick = (list) => list[Math.floor(Math.random() * list.length)];
const think = (min, max) => sleep((min + Math.random() * (max - min)) * THINK);

function get(path, name) {
    const res = http.get(`${BASE_URL}${path}`, { tags: { name } });
    check(res, { [`${name} → 200`]: (r) => r.status === 200 });
    return res;
}

function loadAssets(res) {
    if (!WITH_ASSETS || res.status !== 200) return;
    const urls = assetUrls(res).map((url) => (url.startsWith('http') ? url : `${BASE_URL}${url}`));
    if (urls.length > 0) http.batch(urls.map((url) => ['GET', url, null, { tags: { name: 'assets /build' } }]));
}

/** Visita: inicio → elegir unidad → leer el formulario. No envía nada. */
export function visitante() {
    group('Inicio', () => loadAssets(get('/', 'GET /')));
    think(3, 8);
    group('Elegir unidad', () => get('/cotizar', 'GET /cotizar'));
    think(2, 5);
    group('Formulario', () => {
        const res = get(`/cotizar/${pick(UNITS)}`, 'GET /cotizar/{unidad}');
        loadAssets(res);
    });
    think(10, 30);
}

/** Solicitud completa: igual que el visitante, pero llena el formulario y lo envía. */
export function solicitante() {
    group('Inicio', () => loadAssets(get('/', 'GET /')));
    think(2, 5);
    const unit = pick(UNITS);
    let page;
    group('Formulario', () => {
        const res = get(`/cotizar/${unit}`, 'GET /cotizar/{unidad}');
        loadAssets(res);
        page = pageOf(res);
    });
    check(page, { 'formulario trae las preguntas': (p) => !!(p && p.props && p.props.viewData) });
    if (!page || !page.props || !page.props.viewData) return;

    // Llenar el formulario lleva minutos; aquí se resume en 20-60 s.
    think(20, 60);

    group('Enviar solicitud', () => enviar(unit, page));
    think(5, 10);
}

/** Una sola petición a una página pública al azar (para medir peticiones por segundo). */
export function paginaSuelta() {
    const r = Math.random();
    if (r < 0.4) get('/', 'GET /');
    else if (r < 0.6) get('/cotizar', 'GET /cotizar');
    else get(`/cotizar/${pick(UNITS)}`, 'GET /cotizar/{unidad}');
}

function enviar(unit, page) {
    const { viewData } = page.props;
    const answers = answersFor(viewData.conditions, viewData.initial_condition_id);
    const id = `${__VU}-${__ITER}-${Date.now()}`;
    const contact = {
        name: `Prueba de carga ${id}`,
        company: 'Empresa de prueba k6 S.A.S.',
        nit: '900123456-7',
        economic_activity: 'Prueba de carga',
        address: 'Calle 1 # 2-3, Medellín',
        email: `k6-${id}@example.com`,
        phone: '3001234567',
        role: 'Gerente',
    };

    const token = xsrfToken(BASE_URL);
    const headers = {
        Accept: 'text/html, application/xhtml+xml',
        'X-Requested-With': 'XMLHttpRequest',
        'X-Inertia': 'true',
        'X-Inertia-Version': page.version || '',
        'X-XSRF-TOKEN': token || '',
        Referer: `${BASE_URL}/cotizar/${unit}`,
    };

    let body;
    const firstKey = Object.keys(answers)[0];
    if (ATTACHMENT_KB > 0 && firstKey) {
        // Con archivos, Inertia envía multipart/form-data.
        body = { businessUnit: viewData.businessUnit, privacy_accepted: '1', web: '' };
        for (const [k, v] of Object.entries(contact)) body[`contact[${k}]`] = v;
        for (const [k, v] of Object.entries(answers)) body[`answers[${k}]`] = v;
        body['documents[0][question]'] = firstKey;
        body['documents[0][file]'] = http.file(fakePdf(ATTACHMENT_KB), 'documento-prueba.pdf', 'application/pdf');
    } else {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify({
            contact,
            businessUnit: viewData.businessUnit,
            answers,
            documents: [],
            privacy_accepted: true,
            web: '',
        });
    }

    // Laravel responde con una redirección al formulario; k6 la sigue y la página
    // trae el código de la solicitud en flash.quotation_sent.
    const res = http.post(`${BASE_URL}/cotizar`, body, { headers, tags: { name: 'POST /cotizar' } });
    const sent = pageOf(res);
    const ok = check(res, {
        'POST /cotizar → 200': (r) => r.status === 200,
        'solicitud registrada (tiene código)': () =>
            !!(sent && sent.props && sent.props.flash && sent.props.flash.quotation_sent && sent.props.flash.quotation_sent.code),
    });
    if (ok) solicitudesEnviadas.add(1);
    else if (sent && sent.props && sent.props.errors && Object.keys(sent.props.errors).length > 0) {
        console.warn(`Solicitud rechazada: ${JSON.stringify(sent.props.errors).slice(0, 300)}`);
    }
}

/**
 * Recorre el formulario como lo haría el cliente: desde la primera pregunta,
 * siguiendo la rama de la opción elegida. Las claves son el texto de cada
 * pregunta, igual que en el formulario real (resources/js/features/quotation-wizard/answers.ts).
 */
function answersFor(conditions, initialId) {
    const answers = {};
    const seen = {};
    let id = initialId;
    while (id !== null && id !== undefined && conditions[id] && !seen[id]) {
        seen[id] = true;
        const c = conditions[id];
        let next = c.next_condition;
        let value = null;

        if (c.interaction_type === 'options' && c.options.length > 0) {
            const regular = c.options.filter((o) => !o.is_other);
            const option = pick(regular.length > 0 ? regular : c.options);
            value = option.is_other ? 'Otra respuesta (prueba de carga)' : option.label;
            if (!c.multiple && option.next_condition) next = option.next_condition;
        } else if (c.interaction_type === 'range') {
            value = c.type === 'date' ? 'Desde 1/02/2027 hasta 28/02/2027' : 'Desde 10 hasta 50';
        } else if (c.type === 'file') {
            value = null; // el documento va aparte (ADJUNTO_KB)
        } else if (c.type === 'number') {
            value = '25';
        } else if (c.type === 'date') {
            value = '15/02/2027';
        } else {
            value = c.multiline
                ? 'Texto de prueba de carga. Necesitamos el servicio para mejorar nuestros procesos internos y cumplir la norma.'
                : 'Respuesta de prueba';
        }

        if (value !== null) {
            let key = c.label;
            for (let n = 2; key in answers; n++) key = `${c.label} (${n})`;
            answers[key] = value;
        }
        id = next;
    }
    return answers;
}

const pdfCache = {};
function fakePdf(kb) {
    if (!pdfCache[kb]) {
        const header = '%PDF-1.4\n% documento de prueba k6\n';
        pdfCache[kb] = header + 'x'.repeat(Math.max(0, kb * 1024 - header.length));
    }
    return pdfCache[kb];
}
