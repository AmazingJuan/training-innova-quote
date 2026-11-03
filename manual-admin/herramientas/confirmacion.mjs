// Captura del cuadro de confirmación nativo del navegador (02-08).
//
// Playwright intercepta los cuadros de diálogo y no los dibuja, así que aquí se
// abre Chromium normal dentro de una pantalla virtual (Xvfb), con la sesión ya
// iniciada, y se fotografía la pantalla mientras el cuadro está abierto.
//
//   xvfb-run -a -s "-screen 0 2400x1900x24" node confirmacion.mjs   (pantalla de 2400x1900 para ventana a escala 2)
import { chromium } from 'playwright-core';
import { spawn, execFileSync } from 'node:child_process';
import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';

const AQUI = path.dirname(fileURLToPath(import.meta.url));
const DOMINIO = 'cotizaciones.trainingcorporation.com.co';
const PERFIL = path.join(AQUI, '.entorno', 'perfil-navegador');
const SALIDA = path.join(AQUI, '..', 'capturas', '02-08-confirmar-eliminar.png');
const REGLA = `--host-resolver-rules=MAP ${DOMINIO}:443 127.0.0.1:8443`;
const dormir = (ms) => new Promise((r) => setTimeout(r, ms));

// 1) Iniciar sesión una vez y dejar la cookie en el perfil.
fs.rmSync(PERFIL, { recursive: true, force: true });
const contexto = await chromium.launchPersistentContext(PERFIL, {
    executablePath: '/usr/bin/chromium',
    args: [REGLA],
    ignoreHTTPSErrors: true,
    viewport: { width: 1180, height: 900 },
});
const pagina = await contexto.newPage();
await pagina.goto(`https://${DOMINIO}/login`, { waitUntil: 'networkidle' });
await pagina.fill('#email', 'laura.restrepo@trainingcorporation.com.co');
await pagina.fill('#password', 'Manual2026*');
await Promise.all([pagina.waitForURL('**/dashboard'), pagina.click('button:has-text("Iniciar sesión")')]);
await contexto.close();

// 2) Chromium normal, con la pantalla virtual y sin ningún cliente de automatización conectado a la página.
const navegador = spawn('/usr/bin/chromium', [
    `--user-data-dir=${PERFIL}`, REGLA, '--ignore-certificate-errors', '--no-first-run', '--no-default-browser-check',
    '--password-store=basic', '--force-device-scale-factor=2', '--window-position=0,0', '--window-size=1180,900',
    '--remote-debugging-port=9333', '--test-type', '--lang=es-CO', '--disable-features=Translate,TranslateUI,InfiniteSessionRestore',
    '--disable-infobars', '--disable-session-crashed-bubble', `--app=https://${DOMINIO}/dashboard/servicios`,
], { stdio: 'ignore', env: { ...process.env, GTK_THEME: 'Adwaita:light', LANGUAGE: 'es_CO:es', LANG: 'es_CO.UTF-8' } });

try {
    let destino;
    for (let i = 0; i < 40 && !destino; i++) {
        await dormir(500);
        destino = await fetch('http://127.0.0.1:9333/json').then((r) => r.json()).then((l) => l.find((p) => p.type === 'page')).catch(() => null);
    }
    if (!destino) throw new Error('El navegador no abrió la página');
    await dormir(4000); // carga de la lista

    // 3) Hacer clic en "Eliminar" de la primera fila por un canal que no activa el dominio Page:
    //    así el cuadro lo dibuja el propio navegador. La evaluación queda bloqueada hasta cerrar el cuadro.
    const ws = new WebSocket(destino.webSocketDebuggerUrl);
    await new Promise((ok, fallo) => { ws.onopen = ok; ws.onerror = fallo; });
    ws.send(JSON.stringify({ id: 1, method: 'Runtime.evaluate', params: { expression: "setTimeout(() => document.querySelector('tbody button[aria-label=\"Eliminar\"]').click(), 50); 1" } }));
    await dormir(2500);

    execFileSync('import', ['-window', 'root', SALIDA]);
    // La ventana mide 1180x900 px CSS (2360x1800 reales); se conserva la parte de arriba, donde está el cuadro.
    execFileSync('magick', [SALIDA, '-crop', '2360x1160+0+0', '+repage', SALIDA]);
    console.log('✔ 02-08-confirmar-eliminar');
} finally {
    navegador.kill('SIGKILL');
}
