#!/bin/sh
# Cópia de segurança do NexRH Público: banco de dados + arquivos enviados.
# Uso: ./scripts/backup.sh [pasta-destino]      (padrão: ./backups)
# Agende diariamente (ex.: cron "0 2 * * * cd /opt/nexrh && ./scripts/backup.sh").
set -eu

DESTINO="${1:-./backups}"
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
