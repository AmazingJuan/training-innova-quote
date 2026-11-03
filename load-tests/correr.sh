#!/usr/bin/env bash
# Ejecuta un perfil de capacidad.js y guarda el informe HTML (gráficas en el tiempo)
# y el resumen JSON en load-tests/resultados/.
#
#   ./load-tests/correr.sh humo
#   ./load-tests/correr.sh carga 50
#   ./load-tests/correr.sh estres 50
#   BASE_URL=https://localhost:8443 INSECURE=1 ./load-tests/correr.sh humo
#
# Las demás variables (PCT_SOLICITUDES, ASSETS, PENSAR, ADJUNTO_KB, MAX_RPS, DURACION)
# se pasan igual, como variables de entorno.
set -euo pipefail
cd "$(dirname "$0")"

PERFIL="${1:-humo}"
USUARIOS="${2:-${USUARIOS:-50}}"
STAMP="$(date +%F-%H%M)"
mkdir -p resultados

export PERFIL USUARIOS
export K6_WEB_DASHBOARD=true
export K6_WEB_DASHBOARD_EXPORT="resultados/${PERFIL}-${USUARIOS}u-${STAMP}.html"

echo "Perfil: $PERFIL | usuarios: $USUARIOS | destino: ${BASE_URL:-https://cotizaciones.trainingcorporation.com.co}"
echo "Mientras corre, el panel en vivo está en http://localhost:5665"

status=0
k6 run --summary-export "resultados/${PERFIL}-${USUARIOS}u-${STAMP}.json" capacidad.js || status=$?

echo
echo "Informe: load-tests/$K6_WEB_DASHBOARD_EXPORT"
exit $status
