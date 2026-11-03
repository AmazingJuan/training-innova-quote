// Pruebas de capacidad del sitio público (inicio, elegir unidad, formulario y envío).
//
//   k6 run -e PERFIL=carga -e USUARIOS=50 load-tests/capacidad.js
//
// Perfiles (PERFIL):
//   humo         2 usuarios, ~2 min. Confirma que el script y el sitio funcionan.
//   carga        sube a USUARIOS simultáneos y los mantiene 10 min. ¿Aguanta lo esperado?
//   estres       escalones de USUARIOS x1, x2, x4, x6, x8. ¿Dónde se rompe?
//   pico         de pocos usuarios a USUARIOS x4 en 30 s. ¿Aguanta una avalancha?
//   resistencia  USUARIOS durante DURACION (60m). ¿Se degrada con el tiempo?
//   rps          peticiones por segundo crecientes hasta MAX_RPS, sin pausas. ¿Cuál es el máximo?
//
// Otras variables: BASE_URL, PCT_SOLICITUDES (10), ASSETS (1), PENSAR (1), ADJUNTO_KB (0), INSECURE (0).
// Ver load-tests/README.md.
import { visitante, solicitante, paginaSuelta } from './lib/flows.js';

export { visitante, solicitante, paginaSuelta };

const PERFIL = __ENV.PERFIL || 'humo';
const USUARIOS = Number(__ENV.USUARIOS || 50);
const PCT_SOLICITUDES = Number(__ENV.PCT_SOLICITUDES || 10) / 100;
const MAX_RPS = Number(__ENV.MAX_RPS || 150);

const visitantes = (n) => Math.max(1, Math.round(n * (1 - PCT_SOLICITUDES)));
const solicitantes = (n) => Math.max(1, Math.round(n * PCT_SOLICITUDES));

/** Dos escenarios en paralelo (visitantes y solicitantes) con las mismas etapas. */
function mixed(stages) {
    const scenario = (exec, share) => ({
        executor: 'ramping-vus',
        exec,
        startVUs: 0,
        stages: stages.map((s) => ({ duration: s.duration, target: s.target === 0 ? 0 : share(s.target) })),
        gracefulRampDown: '30s',
    });
    return { visitantes: scenario('visitante', visitantes), solicitantes: scenario('solicitante', solicitantes) };
}

function stressStages() {
    const stages = [];
    for (const factor of [1, 2, 4, 6, 8]) {
        stages.push({ duration: '1m', target: USUARIOS * factor });
        stages.push({ duration: '3m', target: USUARIOS * factor });
    }
    stages.push({ duration: '1m', target: 0 });
    return stages;
}

const PERFILES = {
    humo: {
        visitantes: { executor: 'constant-vus', exec: 'visitante', vus: 1, duration: '1m' },
        solicitantes: { executor: 'per-vu-iterations', exec: 'solicitante', vus: 1, iterations: 2, maxDuration: '5m' },
    },
    carga: mixed([
        { duration: '3m', target: USUARIOS },
        { duration: '10m', target: USUARIOS },
        { duration: '2m', target: 0 },
    ]),
    estres: mixed(stressStages()),
    pico: mixed([
        { duration: '1m', target: Math.ceil(USUARIOS * 0.2) },
        { duration: '30s', target: USUARIOS * 4 },
        { duration: '2m', target: USUARIOS * 4 },
        { duration: '30s', target: Math.ceil(USUARIOS * 0.2) },
        { duration: '1m', target: Math.ceil(USUARIOS * 0.2) },
        { duration: '30s', target: 0 },
    ]),
    resistencia: mixed([
        { duration: '3m', target: USUARIOS },
        { duration: __ENV.DURACION || '60m', target: USUARIOS },
        { duration: '2m', target: 0 },
    ]),
    rps: {
        paginas: {
            executor: 'ramping-arrival-rate',
            exec: 'paginaSuelta',
            startRate: 5,
            timeUnit: '1s',
            preAllocatedVUs: 50,
            maxVUs: Math.max(100, MAX_RPS * 4),
            stages: [0.1, 0.25, 0.5, 0.75, 1].flatMap((f) => [
                { duration: '1m', target: Math.ceil(MAX_RPS * f) },
                { duration: '2m', target: Math.ceil(MAX_RPS * f) },
            ]),
        },
    },
};

if (!PERFILES[PERFIL]) {
    throw new Error(`PERFIL desconocido: ${PERFIL}. Use: ${Object.keys(PERFILES).join(', ')}`);
}

// Criterio de "aguanta": menos de 1 % de errores y el 95 % de las páginas en menos de 800 ms.
const thresholds = {
    http_req_failed: ['rate<0.01'],
    'http_req_duration{name:GET /}': ['p(95)<800'],
    'http_req_duration{name:GET /cotizar}': ['p(95)<800'],
    'http_req_duration{name:GET /cotizar/{unidad}}': ['p(95)<800'],
    checks: ['rate>0.99'],
};
if (PERFIL !== 'rps') {
    thresholds['http_req_duration{name:POST /cotizar}'] = ['p(95)<2000'];
}
if (PERFIL !== 'humo') {
    // Si más del 10 % falla, el servidor ya está caído: se detiene la prueba.
    thresholds.http_req_failed.push({ threshold: 'rate<0.10', abortOnFail: true, delayAbortEval: '30s' });
}

export const options = {
    scenarios: PERFILES[PERFIL],
    thresholds,
    insecureSkipTLSVerify: __ENV.INSECURE === '1',
    summaryTrendStats: ['avg', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
    userAgent: 'k6-prueba-de-carga',
};
