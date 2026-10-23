<#
.SYNOPSIS
    Agenda a cópia de segurança diária no Agendador de Tarefas do Windows.
.DESCRIPTION
    Rode uma vez, no PowerShell (de qualquer pasta; vale a do NexRH onde o script está):
        .\scripts\agendar-backup.ps1               # todo dia às 12h30
        .\scripts\agendar-backup.ps1 -Hora 18:00 -Destino D:\Copias
    A tarefa roda com o seu usuário, quando você estiver conectado, e grava um registro em backups\backup.log.
    Para remover: Unregister-ScheduledTask -TaskName 'NexRH - backup diario'
#>
param(
    [string]$Hora = '12:30',
    [string]$Destino = (Join-Path (Split-Path -Parent $PSScriptRoot) 'backups')
)
$ErrorActionPreference = 'Stop'

$pastaSistema = Split-Path -Parent $PSScriptRoot
if (-not (Test-Path (Join-Path $pastaSistema 'artisan'))) { throw "A pasta $pastaSistema não é a do NexRH Público." }
$Destino = $ExecutionContext.SessionState.Path.GetUnresolvedProviderPathFromPSPath($Destino)
New-Item -ItemType Directory -Force -Path $Destino | Out-Null

$log = Join-Path $Destino 'backup.log'
$comando = "Set-Location '$pastaSistema'; & '.\scripts\backup.ps1' -Destino '$Destino' *>> '$log'"
$acao = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -ExecutionPolicy Bypass -Command `"$comando`""
$gatilho = New-ScheduledTaskTrigger -Daily -At $Hora
$ajustes = New-ScheduledTaskSettingsSet -StartWhenAvailable -DontStopIfGoingOnBatteries -AllowStartIfOnBatteries

Register-ScheduledTask -TaskName 'NexRH - backup diario' -Action $acao -Trigger $gatilho -Settings $ajustes -Description 'Cópia de segurança diária do NexRH Público (banco + arquivos).' -Force | Out-Null
Write-Host "Agendado: todo dia às $Hora, gravando em $Destino. Registro em $log."
Write-Host 'Se o computador estiver desligado nesse horário, a cópia roda assim que ele for ligado.'
