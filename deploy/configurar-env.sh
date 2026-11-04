#!/usr/bin/env bash
# Crea el .env de producción a partir de .env.production.example.
#   ./deploy/configurar-env.sh                 dominio cotizaciones.trainingcorporation.com.co
#   ./deploy/configurar-env.sh otro.dominio    otro dominio (o "localhost" para probar en local)
#
# Genera APP_KEY y las contraseñas de MySQL, y pregunta el correo de Let's Encrypt
# y los datos del SMTP. Si el .env ya existe no lo toca.
set -euo pipefail
cd "$(dirname "$0")/.."

DOMAIN="${1:-cotizaciones.trainingcorporation.com.co}"

if [ -f .env ]; then
    echo "Ya existe .env; no se modifica. Edítelo con: nano .env" >&2
    exit 1
fi

cp .env.production.example .env
chmod 600 .env

set_env() {
    local key="$1" value="$2"
    # Escapa los caracteres especiales de sed en el valor.
    local escaped
    escaped=$(printf '%s' "$value" | sed -e 's/[\\|&]/\\&/g')
    if grep -q "^${key}=" .env; then
        sed -i "s|^${key}=.*|${key}=${escaped}|" .env
    else
        printf '%s=%s\n' "$key" "$value" >> .env
    fi
}

set_env APP_KEY "base64:$(openssl rand -base64 32)"
set_env DB_PASSWORD "$(openssl rand -hex 24)"
set_env DB_ROOT_PASSWORD "$(openssl rand -hex 24)"
set_env APP_DOMAIN "$DOMAIN"
set_env APP_URL "https://$DOMAIN"
set_env SANCTUM_STATEFUL_DOMAINS "$DOMAIN"

ask() {
    local key="$1" prompt="$2" value
    read -r -p "$prompt: " value
    [ -n "$value" ] && set_env "$key" "\"$value\""
    return 0
}

echo "Deje en blanco lo que quiera completar después en .env"
ask ACME_EMAIL "Correo para avisos de Let's Encrypt (vencimiento del certificado)"
ask MAIL_USERNAME "Usuario SMTP (ej. cuenta@gmail.com)"
ask MAIL_PASSWORD "Contraseña SMTP (en Gmail: contraseña de aplicación)"
ask MAIL_FROM_ADDRESS "Remitente de los correos (normalmente el mismo usuario SMTP)"

echo
echo "Creado .env (permisos 600). Revíselo con: nano .env"
echo "Siguiente paso: ./deploy/desplegar.sh"
