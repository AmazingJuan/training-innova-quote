#!/usr/bin/env bash
# Servidor de la aplicación para las capturas (PHP embebido, sin TLS).
set -euo pipefail
source "$(dirname "$0")/entorno.sh"
cd "$RAIZ/public"
exec php -S "127.0.0.1:$PUERTO_PHP" "$HERRAMIENTAS/router.php"
