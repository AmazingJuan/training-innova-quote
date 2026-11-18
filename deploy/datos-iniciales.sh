#!/usr/bin/env bash
# Carga los datos iniciales en una base de datos vacía:
#   - unidades de negocio y el formulario de solicitud (DatabaseSeeder)
#   - el administrador inicial (app:create-admin pide sus datos)
#   ./deploy/datos-iniciales.sh
set -euo pipefail
cd "$(dirname "$0")/.."

units="$(docker compose exec -T app php artisan tinker --execute 'echo \App\Models\BusinessUnit::count();' | tail -n 1 | tr -dc '0-9')"
if [ "${units:-0}" != "0" ]; then
    echo "La base de datos ya tiene $units unidades de negocio; no se carga nada." >&2
    exit 1
fi

docker compose exec -T app php artisan db:seed --force
echo "==> Administrador inicial"
docker compose exec app php artisan app:create-admin

echo
echo "Datos cargados. Entre al panel con el administrador que acaba de crear."
echo "Configure también el correo de notificaciones en Configuración."
