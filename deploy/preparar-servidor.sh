#!/usr/bin/env bash
# Prepara una EC2 con Ubuntu 24.04 recién creada. Se ejecuta UNA vez:
#   sudo ./deploy/preparar-servidor.sh
#
# Instala Docker y Docker Compose, crea 2 GB de swap, pone la hora de Colombia
# y programa el respaldo diario de la base de datos (03:30).
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
    echo "Ejecute con sudo: sudo ./deploy/preparar-servidor.sh" >&2
    exit 1
fi

REPO_DIR="$(cd "$(dirname "$0")/.." && pwd)"
APP_USER="${SUDO_USER:-ubuntu}"

echo "==> Zona horaria"
timedatectl set-timezone America/Bogota

echo "==> Paquetes del sistema"
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get -y upgrade
apt-get -y install ca-certificates curl git

echo "==> Docker"
if ! command -v docker >/dev/null 2>&1; then
    curl -fsSL https://get.docker.com | sh
fi
systemctl enable --now docker
usermod -aG docker "$APP_USER"

echo "==> Swap de 2 GB (evita quedarse sin memoria al compilar la imagen)"
if ! swapon --show | grep -q .; then
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi
echo 'vm.swappiness=10' > /etc/sysctl.d/99-swappiness.conf
sysctl -p /etc/sysctl.d/99-swappiness.conf >/dev/null

echo "==> Respaldo diario de la base de datos"
CRON_LINE="30 3 * * * cd $REPO_DIR && mkdir -p backups && ./deploy/backup-db.sh >> backups/backup.log 2>&1"
( crontab -u "$APP_USER" -l 2>/dev/null | grep -v 'deploy/backup-db.sh' || true; echo "$CRON_LINE" ) | crontab -u "$APP_USER" -

chmod +x "$REPO_DIR"/deploy/*.sh

echo
docker --version
docker compose version
echo
echo "Listo. Cierre la sesión SSH y vuelva a entrar para usar docker sin sudo."
echo "Siguiente paso: ./deploy/configurar-env.sh"
