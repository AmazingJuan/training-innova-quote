#!/usr/bin/env bash
# Worker de la cola para las capturas (correos y exportaciones a Excel), igual que en producción.
set -euo pipefail
source "$(dirname "$0")/entorno.sh"
cd "$RAIZ"
exec php artisan queue:work --sleep=1 --tries=1
