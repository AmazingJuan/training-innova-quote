#!/usr/bin/env bash
# Respaldo de la base de datos en backups/db-AAAA-MM-DD-HHMM.sql.gz (guarda 14 días).
# preparar-servidor.sh lo programa todos los días a las 03:30.
#
# Restaurar un respaldo:
#   gunzip -c backups/db-....sql.gz | docker compose exec -T db sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
set -euo pipefail
cd "$(dirname "$0")/.."

mkdir -p backups
file="backups/db-$(date +%F-%H%M).sql.gz"

docker compose exec -T db sh -c \
    'exec mysqldump --single-transaction --routines --no-tablespaces -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
    | gzip > "$file"

find backups -name 'db-*.sql.gz' -mtime +14 -delete
echo "$(date '+%F %T') respaldo creado: $file ($(du -h "$file" | cut -f1))"
