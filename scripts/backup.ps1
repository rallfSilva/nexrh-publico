<#
.SYNOPSIS
    Cópia de segurança do NexRH Público no Windows: banco de dados + arquivos enviados.
.DESCRIPTION
    Faz o mesmo que scripts/backup.sh, e as cópias servem para os dois scripts de restauração.
    Rode no PowerShell, dentro da pasta do sistema (onde está o docker-compose.yml):
        .\scripts\backup.ps1                  # grava em .\backups\nexrh_AAAA-MM-DD_HHMMSS
        .\scripts\backup.ps1 -Destino D:\Copias
    Apaga cópias com mais de 30 dias (mude com -ManterDias ou NEXRH_BACKUP_DIAS).
    Para agendar todo dia, use .\scripts\agendar-backup.ps1.
#>
param(
    [string]$Destino = (Join-Path (Get-Location) 'backups'),
    [int]$ManterDias = $(if ($env:NEXRH_BACKUP_DIAS) { [int]$env:NEXRH_BACKUP_DIAS } else { 30 })
)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 3

function Executar([string]$descricao, [scriptblock]$comando) {
    & $comando
    if ($LASTEXITCODE -ne 0) { throw "Falhou: $descricao (código $LASTEXITCODE)." }
}

$pasta = Join-Path $Destino ('nexrh_' + (Get-Date -Format 'yyyy-MM-dd_HHmmss'))
New-Item -ItemType Directory -Force -Path $pasta | Out-Null

# Os arquivos são gerados dentro dos contêineres e copiados com "docker compose cp":
# assim o conteúdo binário não passa pelo PowerShell, que poderia corrompê-lo.
Write-Host 'Copiando o banco de dados...'
Executar 'pg_dump' { docker compose exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --format=custom --no-owner -f /tmp/nexrh-banco.dump' }
Executar 'copiar o banco' { docker compose cp db:/tmp/nexrh-banco.dump (Join-Path $pasta 'banco.dump') }
docker compose exec -T db rm -f /tmp/nexrh-banco.dump | Out-Null

Write-Host 'Copiando os arquivos enviados...'
Executar 'compactar os arquivos' { docker compose exec -T app tar -czf /tmp/nexrh-arquivos.tar.gz -C /var/www/html/storage app }
Executar 'copiar os arquivos' { docker compose cp app:/tmp/nexrh-arquivos.tar.gz (Join-Path $pasta 'arquivos.tar.gz') }
docker compose exec -T app rm -f /tmp/nexrh-arquivos.tar.gz | Out-Null

# Mesmo formato do sha256sum, para a cópia poder ser conferida no Linux também.
$somas = foreach ($nome in 'banco.dump', 'arquivos.tar.gz') {
    '{0}  {1}' -f (Get-FileHash -Algorithm SHA256 (Join-Path $pasta $nome)).Hash.ToLower(), $nome
}
[System.IO.File]::WriteAllText((Join-Path $pasta 'SHA256SUMS'), ($somas -join "`n") + "`n")

foreach ($nome in 'banco.dump', 'arquivos.tar.gz') {
    if ((Get-Item (Join-Path $pasta $nome)).Length -eq 0) { throw "A cópia de $nome ficou vazia." }
}

Write-Host "Apagando cópias com mais de $ManterDias dias..."
Get-ChildItem -Path $Destino -Directory -Filter 'nexrh_*' |
    Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$ManterDias) } |
    Remove-Item -Recurse -Force

Write-Host "Pronto: $pasta"
Write-Host 'Lembre de guardar uma cópia fora deste computador.'
