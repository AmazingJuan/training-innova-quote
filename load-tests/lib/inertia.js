// Utilidades para hablar con una app Laravel + Inertia desde k6.
import http from 'k6/http';
import { parseHTML } from 'k6/html';

/**
 * El objeto "page" de Inertia de una respuesta: en la primera visita viene en el
 * atributo data-page del HTML; en las visitas Inertia (X-Inertia) viene como JSON.
 */
export function pageOf(res) {
    if (!res || !res.body) return null;
    const type = String(res.headers['Content-Type'] || '');
    try {
        if (type.includes('application/json')) return JSON.parse(res.body);
        const raw = parseHTML(res.body).find('#app').attr('data-page');
        return raw ? JSON.parse(raw) : null;
    } catch (_) {
        return null;
    }
}

/** Archivos JS y CSS de Vite (/build/...) que el navegador descarga con la página. */
export function assetUrls(res) {
    const doc = parseHTML(res.body);
    const urls = new Set();
    doc.find('script[src]').each((_, el) => urls.add(el.attributes().src.value));
    doc.find('link[rel="stylesheet"], link[rel="modulepreload"]').each((_, el) => {
        const href = el.attributes().href;
        if (href) urls.add(href.value);
    });
    return [...urls].filter((url) => url.includes('/build/'));
}

/** Token CSRF que Laravel deja en la cookie XSRF-TOKEN (el que Inertia envía en X-XSRF-TOKEN). */
export function xsrfToken(baseUrl) {
    const cookies = http.cookieJar().cookiesForURL(baseUrl);
    const value = cookies['XSRF-TOKEN'] && cookies['XSRF-TOKEN'][0];
    return value ? decodeURIComponent(value) : null;
}
