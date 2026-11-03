#!/usr/bin/env bash
# BORRA TODA la base de datos y vuelve a cargar los datos iniciales.
# Pensado para limpiar después de las pruebas de carga, ANTES de salir a producción.
#   ./deploy/reiniciar-datos.sh
set -euo pipefail
cd "$(dirname "$0")/.."

echo "Esto borra todas las solicitudes, clientes, usuarios y configuración."
read -r -p "Escriba BORRAR para continuar: " answer
[ "$answer" = "BORRAR" ] || { echo "Cancelado."; exit 1; }

./deploy/backup-db.sh
docker compose exec -T app php artisan migrate:fresh --force
docker compose exec -T app php artisan cache:clear
./deploy/datos-iniciales.sh
