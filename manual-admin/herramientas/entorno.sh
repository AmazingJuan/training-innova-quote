#!/usr/bin/env bash
# Variables del entorno aislado con el que se toman las capturas del manual.
#
# No modifica el .env del proyecto ni su base de datos: las variables de
# entorno del sistema tienen prioridad sobre las del archivo .env, así que
# aquí se redefinen solo las que cambian (una instancia propia de MariaDB,
# correo sin enviar, cola síncrona y la dirección final del cotizador).
# Cárguelo con bash (no con zsh).
#
#   source manual-admin/herramientas/entorno.sh

HERRAMIENTAS="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RAIZ="$(cd "$HERRAMIENTAS/../.." && pwd)"
ESTADO="$HERRAMIENTAS/.entorno"
mkdir -p "$ESTADO"

export DOMINIO="cotizaciones.trainingcorporation.com.co"
export PUERTO_PHP=8001
export PUERTO_TLS=8443

export APP_ENV=local
export APP_DEBUG=false
export APP_URL="https://$DOMINIO"
export DB_CONNECTION=mysql
export DB_HOST=127.0.0.1
export DB_PORT=3307
export DB_DATABASE=manual_capturas
export DB_USERNAME=manual
export DB_PASSWORD=manual
export SESSION_DRIVER=database
export SESSION_DOMAIN=null
export SESSION_SECURE_COOKIE=true
export CACHE_STORE=database
export QUEUE_CONNECTION=database
export MAIL_MAILER=array
export LOG_CHANNEL=stderr
export PHP_CLI_SERVER_WORKERS=4
