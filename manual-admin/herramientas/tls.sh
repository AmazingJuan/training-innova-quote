#!/usr/bin/env bash
# HTTPS local para el dominio final: socat recibe TLS en PUERTO_TLS con un
# certificado autofirmado y reenvía la conexión al servidor de PHP.
set -euo pipefail
source "$(dirname "$0")/entorno.sh"

if [ ! -f "$ESTADO/cert.pem" ]; then
  openssl req -x509 -newkey rsa:2048 -nodes -days 30 \
    -keyout "$ESTADO/key.pem" -out "$ESTADO/cert.pem" \
    -subj "/CN=$DOMINIO" -addext "subjectAltName=DNS:$DOMINIO" 2>/dev/null
fi

exec socat "OPENSSL-LISTEN:$PUERTO_TLS,reuseaddr,fork,cert=$ESTADO/cert.pem,key=$ESTADO/key.pem,verify=0" \
  "TCP:127.0.0.1:$PUERTO_PHP"
