#!/bin/sh
# Cópia de segurança do NexRH Público: banco de dados + arquivos enviados.
# Uso: ./scripts/backup.sh [pasta-destino]      (padrão: backups, na pasta do sistema)
# Agende diariamente (ex.: cron "0 2 * * * /opt/nexrh/scripts/backup.sh").
# Pode rodar de qualquer pasta: o script sempre usa a pasta do NexRH onde ele está.
set -eu

RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
[ -f "$RAIZ/artisan" ] && [ -f "$RAIZ/docker-compose.yml" ] || { echo "A pasta $RAIZ não é a do NexRH Público." >&2; exit 1; }
DESTINO="${1:-$RAIZ/backups}"
case "$DESTINO" in /*) ;; *) DESTINO="$(pwd)/$DESTINO" ;; esac
cd "$RAIZ"
DATA="$(date +%Y-%m-%d_%H%M%S)"
PASTA="$DESTINO/nexrh_$DATA"
MANTER_DIAS="${NEXRH_BACKUP_DIAS:-30}"

mkdir -p "$PASTA"

echo "Copiando o banco de dados..."
docker compose exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --format=custom --no-owner' > "$PASTA/banco.dump"

echo "Copiando os arquivos enviados..."
docker compose exec -T app tar -czf - -C /var/www/html/storage app > "$PASTA/arquivos.tar.gz"

( cd "$PASTA" && sha256sum banco.dump arquivos.tar.gz > SHA256SUMS )

echo "Apagando cópias com mais de $MANTER_DIAS dias..."
find "$DESTINO" -maxdepth 1 -type d -name 'nexrh_*' -mtime +"$MANTER_DIAS" -exec rm -rf {} +

echo "Pronto: $PASTA"
echo "Lembre de guardar uma cópia fora deste servidor."
