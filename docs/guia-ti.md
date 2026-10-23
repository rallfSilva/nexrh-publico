# Guia da TI: instalação, operação e cópia de segurança

Este guia é para quem instala e mantém o NexRH Público. O uso do sistema, por perfil, está no manual dentro do próprio sistema (menu **Ajuda**).

## 1. O que o sistema precisa

- Um computador ou servidor com Docker e o plugin `docker compose`. Serve Linux (recomendado para produção) ou Windows com Docker Desktop.
- 2 GB de memória livre e 10 GB de disco para começar. O dossiê cresce com os arquivos enviados.
- Para produção: um endereço com HTTPS (Nginx, Caddy ou o balanceador do órgão na frente) e o servidor de e-mail do órgão para os links de senha.

São três contêineres: `app` (sistema, porta 8080 dentro do contêiner), `fila` (tarefas em segundo plano) e `db` (PostgreSQL 16). Os dados ficam em volumes do Docker (`db`, `arquivos` e `logs`), que **não** são apagados por `docker compose down`.

## 2. Instalação

```bash
git clone https://github.com/rallfSilva/nexrh-publico.git
cd nexrh-publico
cp .env.example .env
```

Ajuste no `.env`:

| Variável | O que colocar |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | o endereço do sistema, ex.: `https://rh.selc.rr.gov.br` |
| `APP_KEY` | gere com `docker run --rm php:8.3-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"` |
| `DB_PASSWORD` | senha forte para o banco |
| `NEXRH_PORTA` | porta no computador (padrão 8080). Use `127.0.0.1:8090` para abrir só no próprio computador |
| `MAIL_*` | servidor de e-mail do órgão |
| `TRUSTED_PROXIES` | IP do proxy com HTTPS, se houver |

Suba e crie o primeiro órgão e o administrador geral:

```bash
docker compose up -d --build
docker compose exec app php artisan nexrh:instalar
```

No Windows, os mesmos comandos funcionam no PowerShell. Sem o Git, baixe o zip do repositório e extraia numa pasta (ex.: `C:\NexRH\nexrh-publico`).

## 3. Atualização

1. Faça uma cópia de segurança (seção 5).
2. Traga o código novo: `git pull`, ou extraia o zip novo por cima da pasta **sem sobrescrever o `.env`** (no Windows: `robocopy <pasta-nova> C:\NexRH\nexrh-publico /E /XF .env`).
3. `docker compose up -d --build`. As mudanças no banco rodam sozinhas ao subir.
4. Confira: `docker compose ps` (tudo `healthy`) e a página de entrada abrindo.

## 4. Segurança

- **Guarde a `APP_KEY`** num cofre de senhas, separada das cópias. CPF, documentos, dados bancários e os arquivos do dossiê são criptografados com ela. Sem a mesma chave, nada disso pode ser lido, nem numa cópia restaurada.
- Verificação em duas etapas obrigatória para administradores e RH. `NEXRH_EXIGIR_2FA=false` só em demonstração.
- Dados de demonstração (`NEXRH_DEMO=true`) nunca rodam com `APP_ENV=production`.
- Dados reais só depois de validados segurança, permissões e cópia de segurança.
- A trilha de auditoria registra entradas, alterações, aberturas de ficha, downloads e exportações de relatório.

## 5. Cópia de segurança e restauração

A cópia leva o banco de dados e os arquivos do dossiê, com soma de verificação (SHA-256). Uma cópia feita no Windows restaura no Linux e vice-versa.

### Windows (PowerShell, dentro da pasta do sistema)

```powershell
.\scripts\backup.ps1                                   # cópia agora, em .\backups\nexrh_AAAA-MM-DD_HHMMSS
.\scripts\backup.ps1 -Destino D:\Copias -ManterDias 60
.\scripts\agendar-backup.ps1                           # todo dia às 12h30, no Agendador de Tarefas
.\scripts\agendar-backup.ps1 -Hora 18:00 -Destino D:\Copias
.\scripts\restaurar.ps1 .\backups\nexrh_2026-10-22_123000
```

Se o PowerShell recusar rodar scripts, use uma vez: `Set-ExecutionPolicy -Scope CurrentUser RemoteSigned`.

A tarefa agendada roda com o usuário que a criou, quando ele estiver conectado; se o computador estiver desligado no horário, ela roda assim que for ligado. O registro fica em `backups\backup.log`. Para remover: `Unregister-ScheduledTask -TaskName 'NexRH - backup diario'`.

### Linux

```bash
./scripts/backup.sh                     # cópia agora, em ./backups
./scripts/restaurar.sh ./backups/nexrh_2026-10-22_020000
```

Agende no cron, ex.: `0 2 * * * cd /opt/nexrh-publico && ./scripts/backup.sh >> /var/log/nexrh-backup.log 2>&1`.

### Regras para as cópias

- As cópias com mais de 30 dias são apagadas (mude com `NEXRH_BACKUP_DIAS` ou `-ManterDias`).
- Guarde uma cópia **fora** do computador do sistema (outro disco, rede do órgão ou nuvem do Estado).
- A restauração confere a soma de verificação e pede que se digite `RESTAURAR` antes de substituir os dados.
- Teste a restauração pelo menos uma vez por mês numa máquina de teste. O CI do projeto testa os dois scripts a cada mudança.

## 6. API de consulta

Os sistemas que vão consultar o NexRH usam uma chave criada pelo administrador do órgão em **Chaves de API** (ou pela linha de comando):

```bash
docker compose exec app php artisan nexrh:chave-api SELC "Ponto eletrônico" --escopos=unidades,servidores,ferias
```

A última linha é a chave, que não aparece de novo. Cada chamada leva o cabeçalho `Authorization: Bearer nxr_...`. As consultas e os filtros estão no manual (menu **Ajuda › API de consulta**). A API é só leitura, vê um órgão só, devolve o CPF mascarado e não devolve documentos, endereço, contato, dados bancários nem o tipo de licença. O limite é de 60 consultas por minuto por chave.

## 7. Frequência: redes do órgão e relógio de ponto

**Redes.** O servidor só registra o ponto de IPs das redes cadastradas em **Frequência › Regras e feriados** (formato CIDR, separadas por vírgula, ex.: `10.20.0.0/16, 10.21.4.0/24`). A tela mostra o IP de quem está acessando, o que ajuda a conferir.

- Com o Docker Desktop no Windows, o sistema pode ver todos os acessos vindos da rede com o IP interno do Docker (ex.: `172.17.0.1` ou `192.168.65.1`). Nesse caso a restrição por rede não distingue as sedes. Para publicar o sistema na rede do órgão, use um proxy reverso (IIS, nginx) que repasse o IP real no cabeçalho `X-Forwarded-For` e defina `TRUSTED_PROXIES` no `.env` com o IP do proxy.
- Se o ponto for só pelo relógio, o RH pode desmarcar "Só aceitar o registro de dentro da rede do órgão".

**Relógio de ponto.** Exporte do relógio (ou do software dele) o **AFD** do período, em texto, e envie em **Frequência › Importar relógio de ponto**. O CPF (Portaria 671) ou o PIS (Portaria 1.510) precisa estar na ficha do servidor. Enviar o mesmo arquivo de novo não duplica marcações. Confira a data e a hora do relógio: marcações no futuro bloqueiam a importação.

## 8. Problemas comuns

| Sintoma | O que fazer |
|---|---|
| A página não abre | `docker compose ps`; se algum serviço não estiver `healthy`, veja `docker compose logs --tail=100 app` |
| "Porta já em uso" ao subir | Mude `NEXRH_PORTA` no `.env` e rode `docker compose up -d` |
| Links de senha não chegam | Confira `MAIL_*` no `.env` e `docker compose logs fila` |
| Dados criptografados aparecem com erro depois de restaurar | A `APP_KEY` do `.env` não é a mesma da época da cópia |
| Restaurar diz que a cópia está corrompida | A soma de verificação não confere: use outra cópia |
| O ponto diz "fora da rede do órgão" | Compare o IP mostrado em **Regras e feriados** com as redes cadastradas (veja a seção 7) |
