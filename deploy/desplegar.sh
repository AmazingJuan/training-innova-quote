#!/usr/bin/env bash
# Despliega (la primera vez) o actualiza la aplicación.
#   ./deploy/desplegar.sh            trae los últimos cambios (git pull) y despliega
#   ./deploy/desplegar.sh --sin-pull despliega el código tal como está
#
# Construye la imagen, levanta los servicios y espera a que la app responda.
# Las migraciones se ejecutan solas al arrancar el contenedor app.
set -euo pipefail
cd "$(dirname "$0")/.."

env_value() {
    grep -E "^$1=" .env | tail -n 1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'
}

if [ ! -f .env ]; then
    echo "Falta .env. Ejecute primero ./deploy/configurar-env.sh" >&2
    exit 1
fi

missing=()
for key in APP_KEY DB_PASSWORD DB_ROOT_PASSWORD APP_DOMAIN ACME_EMAIL MAIL_FROM_ADDRESS; do
    [ -n "$(env_value "$key")" ] || missing+=("$key")
done
if [ ${#missing[@]} -gt 0 ]; then
    echo "Complete en .env: ${missing[*]}" >&2
    exit 1
fi

if [ "${1:-}" != "--sin-pull" ]; then
    if [ "$(git branch --show-current)" = "deployment" ]; then
        echo "==> Actualizando la rama deployment"
        if ! git diff --quiet || ! git diff --cached --quiet; then
            echo "Hay cambios locales en archivos versionados; guárdelos antes de desplegar." >&2
            exit 1
        fi
        git fetch origin deployment
        git reset --hard FETCH_HEAD
    else
        echo "==> git pull"
        git pull --ff-only
    fi
fi

DOMAIN="$(env_value APP_DOMAIN)"
if [ "$DOMAIN" != "localhost" ]; then
    public_ip="$(curl -fsS --max-time 5 https://checkip.amazonaws.com || true)"
    dns_ip="$(getent ahostsv4 "$DOMAIN" | awk 'NR==1 {print $1}' || true)"
    if [ -n "$public_ip" ] && [ "$dns_ip" != "$public_ip" ]; then
        echo "AVISO: $DOMAIN apunta a '${dns_ip:-nada}', pero la IP pública de este servidor es $public_ip."
        echo "Sin el registro DNS correcto Let's Encrypt no puede emitir el certificado HTTPS."
        read -r -p "¿Continuar de todos modos? [s/N] " answer
        [ "$answer" = "s" ] || [ "$answer" = "S" ] || exit 1
    fi
fi

echo "==> Construyendo la imagen (la primera vez tarda varios minutos)"
docker compose build app

echo "==> Levantando los servicios"
docker compose up -d --remove-orphans

echo "==> Esperando a que la app esté lista"
for _ in $(seq 1 60); do
    status="$(docker inspect --format '{{.State.Health.Status}}' "$(docker compose ps -q app)" 2>/dev/null || true)"
    [ "$status" = "healthy" ] && break
    sleep 5
done
if [ "$status" != "healthy" ]; then
    echo "La app no quedó sana. Revise: docker compose logs app" >&2
    exit 1
fi

docker compose ps

units="$(docker compose exec -T app php artisan tinker --execute 'echo \App\Models\BusinessUnit::count();' 2>/dev/null | tail -n 1 | tr -dc '0-9')"
if [ "${units:-0}" = "0" ]; then
    echo
    echo "La base de datos está vacía. Cargue los datos iniciales con: ./deploy/datos-iniciales.sh"
fi

docker image prune -f >/dev/null

echo
echo "Desplegado en https://$DOMAIN"
