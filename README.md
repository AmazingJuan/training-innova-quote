# Training InnovaQuote

Cotizador en línea de **Training Corporation**. Las personas solicitan una cotización de auditoría, consultoría o formación desde el sitio, respondiendo el formulario de encuadre de cada unidad de negocio; el equipo de Gerencia la revisa en el panel, carga la cotización y el sistema se la envía al cliente.

Producción: <https://cotizaciones.trainingcorporation.com.co>

## Stack

| Capa | Tecnología |
|---|---|
| Backend | Laravel 12, PHP 8.4 |
| Frontend | React 18 + TypeScript con Inertia 2, Tailwind CSS 3, Vite 7 |
| Base de datos | MariaDB 11.4 (SQLite en las pruebas) |
| Servidor | FrankenPHP (Alpine) detrás de Caddy, con Docker Compose |
| Correo | Microsoft Graph, como el buzón de Gerencia |
| Tareas en segundo plano | Cola de Laravel (worker) y scheduler |

## Desarrollo local

Requisitos: PHP 8.4, Composer, Node.js 22 y MariaDB/MySQL (o SQLite).

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed          # unidades de negocio y formulario de solicitud
php artisan app:create-admin        # el primer usuario del panel
composer dev                        # servidor, cola, logs y Vite a la vez
```

En local, `MAIL_MAILER=log` deja los correos en `storage/logs` en vez de enviarlos.

## Calidad

Lo mismo que corre la integración continua en cada PR:

| Comando | Qué revisa |
|---|---|
| `php artisan test` | Pruebas (PHPUnit) |
| `vendor/bin/pint --test` | Estilo del código PHP |
| `vendor/bin/phpstan analyse` | Análisis estático (Larastan) |
| `composer audit` | Vulnerabilidades conocidas en dependencias |
| `npm run lint:check` | ESLint |
| `npm run format:check` | Prettier |
| `npm test` | Pruebas de la lógica del frontend (Vitest) |
| `npm run build` | TypeScript y compilación del frontend |

Las pruebas también corren contra MariaDB 11.4, la misma versión de producción.

## Despliegue

Cada push a `development` que pasa todas las revisiones publica la rama `deployment` (código con el frontend ya compilado). En el servidor:

```bash
./deploy/desplegar.sh
```

La guía completa del servidor (instalación, `.env`, correo por Microsoft Graph, recordatorios, respaldos y operación diaria) está en [`deploy/README.md`](deploy/README.md).

## Estructura

```
app/
  Console/Commands/   recordatorios a Gerencia, limpieza de adjuntos y exportaciones, crear administrador
  Http/Controllers/   Admin/ (panel) y Quotation/ (sitio público)
  Mail/               correos de la aplicación y Transport/ (Microsoft Graph)
  Services/           árbol de decisión del formulario, envío de correos
  Support/            calendario de días hábiles de Colombia, adjuntos
resources/js/
  pages/              una página de Inertia por ruta
  features/           formulario público (quotation-wizard), editor del formulario (decision-tree), etc.
  components/         componentes compartidos del panel y del sitio
deploy/               scripts y guía del servidor
```
