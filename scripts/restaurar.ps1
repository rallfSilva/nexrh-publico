<#
.SYNOPSIS
    Restaura uma cópia feita pelo backup.ps1 ou pelo backup.sh. APAGA os dados atuais e põe os da cópia no lugar.
.DESCRIPTION
    Pode rodar de qualquer pasta: o script sempre restaura o NexRH da pasta onde ele está.
        .\scripts\restaurar.ps1 .\backups\nexrh_2026-10-22_020000
    O script confere a soma de verificação e pede que você digite RESTAURAR antes de continuar.
    A APP_KEY do .env precisa ser a mesma da época da cópia: sem ela, CPF, documentos e o dossiê não podem ser lidos.
#>
param(
    [Parameter(Mandatory = $true, HelpMessage = 'Pasta da cópia, ex.: .\backups\nexrh_2026-10-22_020000')]
    [string]$Pasta,
    # Só para automação (testes): dispensa a pergunta quando vale RESTAURAR.
    [string]$Confirmar
)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 3

function Executar([string]$descricao, [scriptblock]$comando) {
    & $comando
    if ($LASTEXITCODE -ne 0) { throw "Falhou: $descricao (código $LASTEXITCODE)." }
}

# Sempre na pasta do NexRH (a deste script), seja qual for a pasta atual do PowerShell:
# rodar em outra pasta com docker-compose.yml faria o comando valer para outro sistema.
$raiz = Split-Path -Parent $PSScriptRoot
if (-not (Test-Path (Join-Path $raiz 'artisan')) -or -not (Test-Path (Join-Path $raiz 'docker-compose.yml'))) { throw "A pasta $raiz não é a do NexRH Público." }
$Pasta = (Resolve-Path $Pasta).Path
Push-Location $raiz
try {
    $banco = Join-Path $Pasta 'banco.dump'
    $arquivos = Join-Path $Pasta 'arquivos.tar.gz'
    if (-not (Test-Path $banco)) { throw "Não achei $banco" }

    foreach ($linha in Get-Content (Join-Path $Pasta 'SHA256SUMS')) {
        if (-not $linha.Trim()) { continue }
        $esperado, $nome = $linha -split '\s+', 2
        $nome = $nome.TrimStart('*')
        $atual = (Get-FileHash -Algorithm SHA256 (Join-Path $Pasta $nome)).Hash.ToLower()
        if ($atual -ne $esperado.ToLower()) { throw "A cópia está corrompida: a soma de verificação de $nome não confere." }
    }

    $resposta = if ($Confirmar) { $Confirmar } else { Read-Host "Isso vai SUBSTITUIR todos os dados atuais pelos da cópia $Pasta. Digite RESTAURAR para continuar" }
    if ($resposta -cne 'RESTAURAR') { Write-Host 'Cancelado.'; exit 1 }

    Write-Host 'Parando a fila de tarefas...'
    Executar 'parar a fila' { docker compose stop fila }

    Write-Host 'Restaurando os arquivos...'
    Executar 'copiar os arquivos' { docker compose cp $arquivos app:/tmp/nexrh-arquivos.tar.gz }
    Executar 'extrair os arquivos' { docker compose exec -T app sh -c 'rm -rf /var/www/html/storage/app/* && tar -xzf /tmp/nexrh-arquivos.tar.gz -C /var/www/html/storage && rm -f /tmp/nexrh-arquivos.tar.gz' }

    Write-Host 'Parando o sistema...'
    Executar 'parar o sistema' { docker compose stop app }

    Write-Host 'Restaurando o banco de dados...'
    Executar 'copiar o banco' { docker compose cp $banco db:/tmp/nexrh-banco.dump }
    Executar 'recriar o banco' { docker compose exec -T db sh -c 'dropdb -U "$POSTGRES_USER" --if-exists --force "$POSTGRES_DB" && createdb -U "$POSTGRES_USER" "$POSTGRES_DB"' }
    Executar 'pg_restore' { docker compose exec -T db sh -c 'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --no-owner /tmp/nexrh-banco.dump && rm -f /tmp/nexrh-banco.dump' }

    Write-Host 'Subindo o sistema...'
    Executar 'subir o sistema' { docker compose start app fila }

    Write-Host 'Restauração concluída.'
}
finally {
    Pop-Location
}
