// Toma las capturas del manual con Chromium sin interfaz.
//
//   node capturar.mjs            todas
//   node capturar.mjs 02-0 03-   solo las que empiezan por esos prefijos
//
// Requiere el entorno de herramientas/ levantado (mariadb.sh, servidor.sh y
// tls.sh) con los datos de datos-demo.php. Las imágenes quedan en ../capturas/
// con el nombre que espera cada \captura{...} del manual.
import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const AQUI = path.dirname(fileURLToPath(import.meta.url));
const SALIDA = path.join(AQUI, '..', 'capturas');
const DOMINIO = 'cotizaciones.trainingcorporation.com.co';
const URL = `https://${DOMINIO}`;
const USUARIO = { correo: 'laura.restrepo@trainingcorporation.com.co', clave: 'Manual2026*' };
const ANCHO = 1180; // escritorio: el contenido (sin barra lateral) queda en ~900 px
const ANCHO_COMPLETO = 1024; // capturas con la barra lateral incluida
const PAUSA_FORM = 24; // margen que se recorta del borde del contenido

fs.mkdirSync(SALIDA, { recursive: true });

const browser = await chromium.launch({
    executablePath: '/usr/bin/chromium',
    args: [`--host-resolver-rules=MAP ${DOMINIO}:443 127.0.0.1:8443`, '--font-render-hinting=none'],
});

const nuevoContexto = (extra = {}) =>
    browser.newContext({
        ignoreHTTPSErrors: true,
        viewport: { width: ANCHO, height: 900 },
        deviceScaleFactor: 2,
        locale: 'es-CO',
        timezoneId: 'America/Bogota',
        reducedMotion: 'reduce',
        ...extra,
    });

async function ir(page, ruta) {
    await page.goto(`${URL}${ruta}`, { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(150);
}

async function iniciarSesion(page) {
    await ir(page, '/login');
    await page.fill('#email', USUARIO.correo);
    await page.fill('#password', USUARIO.clave);
    await Promise.all([page.waitForURL('**/dashboard'), page.click('button:has-text("Iniciar sesión")')]);
    await page.waitForLoadState('networkidle');
}

const guardar = (id) => path.join(SALIDA, `${id}.png`);

/** Recorta una imagen ya guardada (coordenadas en píxeles CSS). Se usa con las ventanas modales:
 *  recortar al capturar hace que el desenfoque del fondo se refleje en los bordes. */
function recortar(archivo, x, y, ancho, alto, escala = 2) {
    execFileSync('magick', [archivo, '-crop', `${ancho * escala}x${alto * escala}+${x * escala}+${y * escala}`, '+repage', archivo]);
}

/** Ajusta la ventana para que todo lo que se va a capturar quepa sin desplazarse. */
async function ajustarAlto(page, ancho, alto) {
    await page.setViewportSize({ width: ancho, height: Math.max(500, Math.ceil(alto)) });
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.waitForTimeout(350);
}

/** Recorta el contenido (sin la barra lateral) desde su borde superior hasta el final del último bloque. */
async function contenido(page, id, { alto = null, desde = 0, ancho = ANCHO } = {}) {
    await page.evaluate(() => document.fonts.ready);
    await page.evaluate(() => window.scrollTo(0, 0));
    const medir = () =>
        page.evaluate(() => {
            const main = document.querySelector('#contenido');
            const r = main.getBoundingClientRect();
            let fondo = 0;
            for (const hijo of main.children) {
                const b = hijo.getBoundingClientRect();
                if (b.height > 0) fondo = Math.max(fondo, b.bottom);
            }
            return { x: r.left, y: r.top, ancho: r.width, fondo };
        });
    let caja = await medir();
    const y = caja.y + 16 + desde;
    const altura = Math.ceil(alto ? Math.min(caja.fondo - y + 12, alto) : caja.fondo - y + 12);
    await ajustarAlto(page, ancho, y + altura + 40);
    caja = await medir();
    const archivo = guardar(id);
    await page.screenshot({ path: archivo });
    recortar(archivo, Math.round(caja.x + 16), Math.round(caja.y + 16 + desde), Math.round(caja.ancho - 32), altura);
}

/** Pantalla completa con la barra lateral, ajustando el alto de la ventana al contenido. */
async function pantalla(page, id, { minimo = 700 } = {}) {
    await page.evaluate(() => document.fonts.ready);
    const fondo = await page.evaluate(() => {
        const main = document.querySelector('#contenido');
        let f = 0;
        for (const hijo of main.children) f = Math.max(f, hijo.getBoundingClientRect().bottom + window.scrollY);
        return f;
    });
    await page.setViewportSize({ width: ANCHO_COMPLETO, height: Math.max(minimo, Math.ceil(fondo + 48)) });
    await page.waitForTimeout(200);
    await page.screenshot({ path: guardar(id) });
}

async function elemento(page, id, localizador, { alto = null, margen = 10 } = {}) {
    const el = page.locator(localizador).first();
    await el.waitFor();
    await el.scrollIntoViewIfNeeded();
    const medir = async () => {
        const c = await el.boundingBox();
        const sy = await page.evaluate(() => window.scrollY);
        return { x: c.x, y: c.y + sy, ancho: c.width, alto: c.height };
    };
    let caja = await medir();
    const altura = Math.ceil(alto ? Math.min(caja.alto, alto) : caja.alto);
    await ajustarAlto(page, ANCHO, caja.y + altura + 40);
    caja = await medir();
    const archivo = guardar(id);
    await page.screenshot({ path: archivo });
    recortar(archivo, Math.round(caja.x - margen), Math.round(caja.y - margen), Math.round(caja.ancho + 2 * margen), altura + 2 * margen);
}

const marcar = async (page, texto) => page.waitForSelector(`text=${texto}`, { timeout: 15000 });

// ---------------------------------------------------------------------------
// Capturas, en el orden del manual. Cada una recibe { visitante, panel }:
// dos páginas, una sin sesión y otra con la sesión iniciada.
// ---------------------------------------------------------------------------
const CAPTURAS = {
    '02-01-login': async ({ visitante }) => {
        await ir(visitante, '/login');
        await visitante.screenshot({ path: guardar('02-01-login'), fullPage: true });
    },
    '02-02-login-error': async ({ visitante }) => {
        await ir(visitante, '/login');
        await visitante.fill('#email', USUARIO.correo);
        await visitante.fill('#password', 'clave-incorrecta');
        await visitante.click('button:has-text("Iniciar sesión")');
        await marcar(visitante, 'These credentials do not match our records');
        await visitante.waitForTimeout(200);
        await visitante.screenshot({ path: guardar('02-02-login-error'), fullPage: true });
    },
    '02-03-recuperar-contrasena': async ({ visitante }) => {
        await ir(visitante, '/forgot-password');
        await visitante.fill('#email', USUARIO.correo);
        await visitante.screenshot({ path: guardar('02-03-recuperar-contrasena'), fullPage: true });
    },
    '02-04-panel-inicio': async ({ panel }) => {
        await ir(panel, '/dashboard');
        await pantalla(panel, '02-04-panel-inicio');
    },
    '02-05-barra-lateral': async ({ panel }) => {
        await panel.setViewportSize({ width: ANCHO_COMPLETO, height: 800 });
        await ir(panel, '/dashboard/ordenes-cotizacion');
        await panel.screenshot({ path: guardar('02-05-barra-lateral'), clip: { x: 0, y: 0, width: 256, height: 800 } });
    },
    // El aviso se toma en 04-06, al crear la línea de gestión: aquí solo se deja el nombre reservado.
    '02-07-lista-tipica': async ({ panel }) => {
        await ir(panel, '/dashboard/unidad-negocio');
        await pantalla(panel, '02-07-lista-tipica');
    },
    '02-09-menu-movil': async () => {
        const ctx = await nuevoContexto({ viewport: { width: 390, height: 760 }, isMobile: true, hasTouch: true, deviceScaleFactor: 3 });
        await ctx.addInitScript(sinCorrector);
        const movil = await ctx.newPage();
        await iniciarSesion(movil);
        await ir(movil, '/dashboard');
        await movil.click('button[aria-label="Abrir menú"]');
        await movil.waitForSelector('[role="dialog"][aria-label="Menú"]');
        await movil.waitForTimeout(500);
        await movil.screenshot({ path: guardar('02-09-menu-movil') });
        await ctx.close();
    },
    '03-01-cotizaciones-lista': async ({ panel }) => {
        await panel.setViewportSize({ width: 1320, height: 900 });
        await ir(panel, '/dashboard/ordenes-cotizacion');
        await contenido(panel, '03-01-cotizaciones-lista', { alto: 720, ancho: 1320 });
    },
    '03-02-cotizacion-detalle': async ({ panel }) => {
        await ir(panel, '/dashboard/ordenes-cotizacion');
        await panel.click('a:has-text("CNN-V-2602")');
        await marcar(panel, 'Cotización CNN-V-2602');
        await contenido(panel, '03-02-cotizacion-detalle', { alto: 660 });
    },
    '03-03-enviar-propuesta': async ({ panel }) => {
        await panel.setViewportSize({ width: ANCHO, height: 900 });
        await panel.click('button:has-text("Enviar propuesta")');
        await panel.waitForSelector('[role="dialog"]');
        await panel.fill('#quotation_url', 'https://drive.google.com/file/d/1aB2cD3eF4gH5iJ6kL7mN8oP/view');
        await panel.press('#quotation_url', 'Home');
        await panel.waitForTimeout(300);
        await panel.screenshot({ path: guardar('03-03-enviar-propuesta') });
        recortar(guardar('03-03-enviar-propuesta'), 264, 0, ANCHO - 264, 700);
    },
    '03-04-cotizacion-generada': async ({ panel }) => {
        await panel.click('button:has-text("Guardar y notificar")');
        await marcar(panel, 'URL de cotización subida exitosamente');
        await panel.waitForTimeout(500);
        await contenido(panel, '03-04-cotizacion-generada', { alto: 560 });
    },
    '03-05-clientes-lista': async ({ panel }) => {
        await ir(panel, '/dashboard/clientes');
        await contenido(panel, '03-05-clientes-lista', { alto: 720 });
    },
    '03-06-cliente-detalle': async ({ panel }) => {
        await ir(panel, '/dashboard/clientes');
        await panel.click('a:has-text("Carolina Ríos")');
        await marcar(panel, 'Historial de cotizaciones');
        await contenido(panel, '03-06-cliente-detalle', { alto: 900 });
    },
    '03-07-excel-clientes': async ({ panel }) => {
        await panel.setViewportSize({ width: ANCHO, height: 900 });
        await ir(panel, '/dashboard/clientes');
        const [descarga] = await Promise.all([
            panel.waitForEvent('download', { timeout: 60000 }),
            panel.click('button:has-text("Exportar a Excel")'),
        ]);
        const xlsx = path.join(AQUI, '.entorno', 'clientes.xlsx');
        const html = path.join(AQUI, '.entorno', 'clientes.html');
        await descarga.saveAs(xlsx);
        console.log('  archivo:', descarga.suggestedFilename());
        console.log('  ' + execFileSync('php', [path.join(AQUI, 'excel-a-html.php'), xlsx, html]).toString().trim());
        // Calibri no está instalado aquí: se usa una tipografía sin serifas equivalente.
        fs.writeFileSync(html, fs.readFileSync(html, 'utf8').replaceAll("'Calibri'", "'Liberation Sans'").replaceAll('Calibri,', "'Liberation Sans',"));
        // Las columnas se estrechan y el texto largo se ajusta, para que la hoja se lea en la página del manual.
        const estilo = '<style>table{table-layout:fixed !important;width:940px !important}td,th{white-space:normal !important;overflow-wrap:anywhere;font-size:10px !important}col{width:auto !important}</style>';
        fs.writeFileSync(html, fs.readFileSync(html, 'utf8').replace('</head>', estilo + '</head>'));
        const hoja = await panel.context().newPage();
        await hoja.setViewportSize({ width: 1000, height: 800 });
        await hoja.goto('file://' + html);
        await hoja.waitForTimeout(500);
        const caja = await hoja.evaluate(() => {
            const t = document.querySelector('table');
            const r = t.getBoundingClientRect();
            return { x: r.left, y: r.top, w: r.width, h: r.height };
        });
        await hoja.setViewportSize({ width: Math.ceil(caja.x + caja.w + 20), height: Math.ceil(caja.y + caja.h + 20) });
        await hoja.waitForTimeout(300);
        await hoja.screenshot({ path: guardar('03-07-excel-clientes') });
        await hoja.close();
    },
    '04-01-unidades-lista': async ({ panel }) => {
        await ir(panel, '/dashboard/unidad-negocio');
        await contenido(panel, '04-01-unidades-lista');
    },
    '04-02-unidad-crear': async ({ panel }) => {
        await ir(panel, '/dashboard/unidad-negocio/crear');
        await panel.fill('#display_name', 'Gestión de activos');
        await panel.fill('#abbreviation', 'GA');
        await contenido(panel, '04-02-unidad-crear');
    },
    '04-03-unidad-editar': async ({ panel }) => {
        await ir(panel, '/dashboard/unidad-negocio/1/editar');
        await contenido(panel, '04-03-unidad-editar');
    },
    '04-04-unidad-detalle': async ({ panel }) => {
        await ir(panel, '/dashboard/unidad-negocio/1');
        await contenido(panel, '04-04-unidad-detalle', { alto: 640 });
    },
    '04-05-lineas-lista': async ({ panel }) => {
        await ir(panel, '/dashboard/lineas-gestion');
        await contenido(panel, '04-05-lineas-lista', { alto: 640 });
    },
    '04-06-linea-crear': async ({ panel }) => {
        await ir(panel, '/dashboard/lineas-gestion/crear');
        await panel.fill('#name', 'Seguridad de la información');
        await contenido(panel, '04-06-linea-crear');
        // Se guarda de verdad para tomar el aviso de confirmación (02-06).
        await panel.click('button:has-text("Crear línea")');
        await marcar(panel, 'Línea de gestión creada correctamente');
        await contenido(panel, '02-06-aviso', { alto: 150 });
    },
    '04-07-servicios-lista': async ({ panel }) => {
        await ir(panel, '/dashboard/servicios');
        await panel.selectOption('#business-unit-filter', { label: 'Consultoría' });
        await panel.waitForLoadState('networkidle');
        await panel.waitForTimeout(400);
        await contenido(panel, '04-07-servicios-lista', { alto: 720 });
    },
    '04-08-servicio-crear': async ({ panel }) => {
        await ir(panel, '/dashboard/servicios/crear');
        await panel.fill('#name', 'Auditoría de seguridad vial');
        await panel.selectOption('#business_unit_id', { label: 'Auditoría' });
        await panel.selectOption('#gestion_line_id', { label: 'Seguridad y Salud en el Trabajo' });
        await contenido(panel, '04-08-servicio-crear');
    },
    '04-09-arbol-vista-general': async ({ panel }) => {
        await ir(panel, '/dashboard/arbol-decision');
        await contenido(panel, '04-09-arbol-vista-general', { alto: 820 });
    },
    '04-10-arbol-unidad-vacia': async ({ panel }) => {
        // Unidad temporal sin preguntas: se crea, se captura y se elimina al final.
        await ir(panel, '/dashboard/unidad-negocio/crear');
        await panel.fill('#display_name', 'Gestión de activos');
        await panel.fill('#abbreviation', 'GA');
        await panel.click('button:has-text("Crear unidad")');
        await marcar(panel, 'ha sido creada exitosamente');
        await ir(panel, '/dashboard/arbol-decision');
        await panel.click('[role="tab"]:has-text("Gestión de activos")');
        await panel.waitForSelector('text=aún no tiene preguntas');
        await contenido(panel, '04-10-arbol-unidad-vacia', { alto: 620 });
        await ir(panel, '/dashboard/unidad-negocio');
        panel.once('dialog', (d) => d.accept());
        await panel.click('tr:has-text("Gestión de activos") button[aria-label="Eliminar"]');
        await marcar(panel, 'ha sido eliminada exitosamente');
    },
    '04-11-arbol-editar-pregunta': async ({ panel }) => {
        await ir(panel, '/dashboard/arbol-decision');
        await panel.click('button:has-text("Descripción del servicio a solicitar")');
        await panel.waitForSelector('text=Editando pregunta');
        await panel.waitForTimeout(300);
        await elemento(panel, '04-11-arbol-editar-pregunta', 'li:has-text("Editando pregunta")', { alto: 760 });
    },
    '04-12-arbol-tipos-respuesta': async ({ panel }) => {
        await elemento(panel, '04-12-arbol-tipos-respuesta', 'fieldset:has(legend:has-text("¿Cómo responde el cliente?"))');
    },
    '04-13-arbol-opciones': async ({ panel }) => {
        await panel.click('button:has-text("Cancelar")');
        await panel.click('button:has-text("Modalidad de prestación del servicio")');
        await panel.waitForSelector('text=Editando pregunta');
        await panel.waitForTimeout(300);
        await elemento(panel, '04-13-arbol-opciones', 'fieldset:has(legend:has-text("Opciones"))');
        await panel.click('button:has-text("Cancelar")');
    },
    '04-14-arbol-preguntas-rama': async ({ panel }) => {
        await ir(panel, '/dashboard/arbol-decision');
        const alto = await panel.evaluate(() => {
            const seccion = document.querySelector('section[aria-labelledby="ramas"]');
            const tarjetas = seccion.querySelectorAll('ul > li');
            const tercera = tarjetas[Math.min(2, tarjetas.length - 1)];
            return Math.ceil(tercera.getBoundingClientRect().bottom - seccion.getBoundingClientRect().top + 6);
        });
        await elemento(panel, '04-14-arbol-preguntas-rama', 'section[aria-labelledby="ramas"]', { alto });
    },
    '04-15-arbol-probar': async ({ panel }) => {
        await panel.click('button:has-text("Probar como cliente")');
        await panel.waitForSelector('[role="dialog"]');
        await panel.locator('[role="dialog"] textarea').first().fill('Auditoría interna al sistema integrado de gestión, antes de la auditoría de renovación.');
        await panel.click('button:has-text("Validar como al enviar")');
        await panel.waitForSelector('text=campos por corregir');
        await panel.waitForTimeout(300);
        await panel.setViewportSize({ width: ANCHO, height: 900 });
        await panel.waitForTimeout(300);
        await panel.locator('[role="dialog"]').screenshot({ path: guardar('04-15-arbol-probar') });
        await panel.keyboard.press('Escape');
    },
    '04-16-arbol-validacion': async ({ panel }) => {
        await ir(panel, '/dashboard/arbol-decision');
        await panel.setViewportSize({ width: ANCHO, height: 900 });
        await panel.click('button:has-text("Añadir pregunta al final")');
        await panel.waitForSelector('text=Nueva pregunta');
        await panel.waitForTimeout(400);
        const li = panel.locator('li:has-text("Nueva pregunta")').last();
        await li.scrollIntoViewIfNeeded();
        let caja = await li.boundingBox();
        const sy = await panel.evaluate(() => window.scrollY);
        await ajustarAlto(panel, ANCHO, caja.y + sy + caja.height + 40);
        caja = await li.boundingBox();
        const archivo = guardar('04-16-arbol-validacion');
        await panel.screenshot({ path: archivo });
        recortar(archivo, Math.round(caja.x), Math.round(caja.y + caja.height - 420), Math.round(caja.width), 420);
        panel.once('dialog', (d) => d.accept());
        await panel.click('button:has-text("Cancelar")');
    },
    '05-01-usuarios-lista': async ({ panel }) => {
        await ir(panel, '/dashboard/usuarios');
        await contenido(panel, '05-01-usuarios-lista');
    },
    '05-02-usuario-crear': async ({ panel }) => {
        await ir(panel, '/dashboard/usuarios/crear');
        await panel.fill('#name', 'Camilo Torres');
        await panel.fill('#email', 'camilo.torres@trainingcorporation.com.co');
        await panel.fill('#phone', '3001234567');
        await panel.fill('#password', 'Clave-de-prueba-1');
        await contenido(panel, '05-02-usuario-crear');
    },
    '05-03-usuario-editar': async ({ panel }) => {
        await ir(panel, '/dashboard/usuarios/2/editar');
        await contenido(panel, '05-03-usuario-editar');
    },
    '05-04-correos-bienvenida': async ({ panel }) => {
        await ir(panel, '/dashboard/correos-bienvenida');
        await panel.fill('textarea', 'cliente1@empresa.com\ncliente2@empresa.com\ncliente3@empresa.com');
        await contenido(panel, '05-04-correos-bienvenida');
    },
    '05-05-configuracion': async ({ panel }) => {
        await ir(panel, '/dashboard/configuraciones');
        await contenido(panel, '05-05-configuracion', { alto: 780 });
    },
    '06-01-sitio-publico': async ({ visitante }) => {
        await visitante.setViewportSize({ width: 1280, height: 780 });
        await ir(visitante, '/');
        await visitante.screenshot({ path: guardar('06-01-sitio-publico') });
        await visitante.setViewportSize({ width: 600, height: 640 });
    },
    '06-02-cerrar-sesion': async ({ panel }) => {
        await panel.setViewportSize({ width: ANCHO_COMPLETO, height: 800 });
        await ir(panel, '/dashboard');
        await panel.screenshot({ path: guardar('06-02-cerrar-sesion'), clip: { x: 0, y: 800 - 170, width: 256, height: 170 } });
    },
};

const prefijos = process.argv.slice(2);
const elegidas = Object.keys(CAPTURAS).filter((id) => prefijos.length === 0 || prefijos.some((p) => id.startsWith(p)));

const ctxVisitante = await nuevoContexto();
const ctxPanel = await nuevoContexto();
const sinCorrector = () => document.addEventListener('focusin', (e) => e.target.setAttribute?.('spellcheck', 'false'), true);
await ctxVisitante.addInitScript(sinCorrector);
await ctxPanel.addInitScript(sinCorrector);
const visitante = await ctxVisitante.newPage();
await visitante.setViewportSize({ width: 600, height: 640 });
const panel = await ctxPanel.newPage();
await iniciarSesion(panel);

let fallos = 0;
for (const id of elegidas) {
    try {
        await CAPTURAS[id]({ visitante, panel });
        console.log('✔', id);
    } catch (error) {
        fallos++;
        console.log('✘', id, '→', String(error.message).split('\n')[0]);
        await panel.screenshot({ path: path.join(AQUI, '.entorno', `error-${id}.png`) }).catch(() => {});
    }
}

await browser.close();
process.exit(fallos ? 1 : 0);
