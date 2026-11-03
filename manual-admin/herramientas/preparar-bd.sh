#!/usr/bin/env bash
# Crea la base y el usuario de las capturas en la instancia de MariaDB que levanta mariadb.sh.
# Ejecútelo una vez, con mariadb.sh ya corriendo.
set -euo pipefail
source "$(dirname "$0")/entorno.sh"

for _ in $(seq 1 40); do
  [ -S "$ESTADO/mysql.sock" ] && break
  sleep 0.5
done

mariadb --no-defaults -uroot --socket="$ESTADO/mysql.sock" <<SQL
CREATE DATABASE IF NOT EXISTS $DB_DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USERNAME'@'127.0.0.1' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL ON $DB_DATABASE.* TO '$DB_USERNAME'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
echo "Base $DB_DATABASE lista en el puerto $DB_PORT."
