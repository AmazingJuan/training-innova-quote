#!/usr/bin/env bash
# Respaldo de la base de datos en backups/db-AAAA-MM-DD-HHMM.sql.gz (guarda 14 días).
# preparar-servidor.sh lo programa todos los días a las 03:30.
#
# Restaurar un respaldo:
#   gunzip -c backups/db-....sql.gz | docker compose exec -T db sh -c 'exec mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"'
set -euo pipefail
cd "$(dirname "$0")/.."

mkdir -p backups
file="backups/db-$(date +%F-%H%M).sql.gz"

docker compose exec -T db sh -c \
    'exec mariadb-dump --single-transaction --routines -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' \
    | gzip > "$file"

# Un respaldo vacío (disco lleno, base caída) no sirve y daría una falsa tranquilidad.
if [ "$(gunzip -c "$file" | head -c 1 | wc -c)" -eq 0 ]; then
    rm -f "$file"
    echo "$(date '+%F %T') ERROR: el respaldo salió vacío" >&2
    exit 1
fi

find backups -name 'db-*.sql.gz' -mtime +14 -delete
echo "$(date '+%F %T') respaldo creado: $file ($(du -h "$file" | cut -f1))"
