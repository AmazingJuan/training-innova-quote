#!/usr/bin/env bash
# Carga los datos iniciales en una base de datos vacía:
#   - el administrador inicial (DatabaseSeeder), con los datos que se piden aquí
#   - unidades de negocio, líneas de gestión, servicios y el formulario de encuadre (ExampleDataSeeder)
#   ./deploy/datos-iniciales.sh
set -euo pipefail
cd "$(dirname "$0")/.."

units="$(docker compose exec -T app php artisan tinker --execute 'echo \App\Models\BusinessUnit::count();' | tail -n 1 | tr -dc '0-9')"
if [ "${units:-0}" != "0" ]; then
    echo "La base de datos ya tiene $units unidades de negocio; no se carga nada." >&2
    exit 1
fi

read -r -p "Correo del administrador inicial: " admin_email
read -r -s -p "Contraseña (mínimo 12 caracteres): " admin_password
echo
docker compose exec -T -e ADMIN_EMAIL="$admin_email" -e ADMIN_PASSWORD="$admin_password" app php artisan db:seed --force
docker compose exec -T app php artisan db:seed --class=ExampleDataSeeder --force

echo
echo "Datos cargados. Entre al panel con el administrador que acaba de crear."
echo "Configure también el correo de notificaciones en Configuración."
