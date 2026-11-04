#!/usr/bin/env bash
# Carga los datos iniciales en una base de datos vacía:
#   - usuarios administradores (DatabaseSeeder)
#   - unidades de negocio, líneas de gestión, servicios y el formulario de encuadre (ExampleDataSeeder)
#   ./deploy/datos-iniciales.sh
set -euo pipefail
cd "$(dirname "$0")/.."

units="$(docker compose exec -T app php artisan tinker --execute 'echo \App\Models\BusinessUnit::count();' | tail -n 1 | tr -dc '0-9')"
if [ "${units:-0}" != "0" ]; then
    echo "La base de datos ya tiene $units unidades de negocio; no se carga nada." >&2
    exit 1
fi

docker compose exec -T app php artisan db:seed --force
docker compose exec -T app php artisan db:seed --class=ExampleDataSeeder --force

echo
echo "Datos cargados. Los usuarios administradores tienen las contraseñas que están en"
echo "database/seeders/DatabaseSeeder.php: cámbielas desde el panel en cuanto entre."
echo "Configure también el correo de notificaciones en Configuración."
