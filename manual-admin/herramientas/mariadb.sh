#!/usr/bin/env bash
# Instancia de MariaDB solo para las capturas, ejecutada con el usuario actual
# (sin sudo). Sus datos viven en herramientas/.entorno/mysql y escucha en
# 127.0.0.1:$DB_PORT, así que no toca la base de datos de desarrollo.
set -euo pipefail
source "$(dirname "$0")/entorno.sh"

DATOS="$ESTADO/mysql"
if [ ! -d "$DATOS/mysql" ]; then
  mkdir -p "$DATOS"
  mariadb-install-db --no-defaults --datadir="$DATOS" --auth-root-authentication-method=normal \
    --skip-test-db > "$ESTADO/mariadb-install.log" 2>&1
fi

exec mariadbd --no-defaults --datadir="$DATOS" --socket="$ESTADO/mysql.sock" \
  --port="$DB_PORT" --bind-address=127.0.0.1 --pid-file="$ESTADO/mysql.pid" \
  --log-error="$ESTADO/mariadb.log" --skip-name-resolve
