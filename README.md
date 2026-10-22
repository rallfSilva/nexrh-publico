# NexRH Público

Gestão de pessoas para a administração pública: estrutura do órgão, usuários e perfis, servidores, cargos, férias e dossiê, com trilha de auditoria de tudo o que é feito. Órgão piloto: SELC/RR.

> **Situação:** Fase 1, Entrega 1 (base do sistema). Veja o que entra em cada entrega em [docs/plano-implementacao.md](docs/plano-implementacao.md).

## O que já funciona (Entrega 1)

- **Entrada segura**: e-mail e senha, "esqueci a senha" por e-mail, verificação em duas etapas (aplicativo autenticador) obrigatória para administradores, limite de 5 tentativas por minuto, bloqueio de usuário ou órgão desativado.
- **Perfis de acesso**: administrador geral, administrador do órgão, RH, chefia, servidor e auditor.
- **Vários órgãos no mesmo sistema**: cada órgão só enxerga os próprios dados. Endereços de outro órgão respondem "página não encontrada".
- **Órgãos e unidades**: organograma em árvore, chefe de cada unidade e mínimo de pessoas no setor durante as férias.
- **Usuários**: cadastro sem senha definida pelo administrador (a pessoa recebe um link por e-mail), troca de perfil, desativação imediata.
- **Trilha de auditoria**: entradas, saídas, tentativas recusadas, cadastros, alterações (com valor antigo e novo), exclusões, mudanças de perfil, senha e downloads. Ninguém consegue alterar nem apagar um registro, nem direto no banco de dados.
- **Modelos de planilha** para importação: unidades, cargos e vagas, servidores, dependentes, férias e afastamentos.

## Instalação com Docker (recomendado)

Precisa de: um servidor Linux com [Docker](https://docs.docker.com/engine/install/) e o plugin `docker compose`.

```bash
git clone https://github.com/rallfSilva/nexrh-publico.git
cd nexrh-publico
cp .env.example .env
```

Edite o `.env` e ajuste pelo menos:

| Variável | O que colocar |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | o endereço do sistema, ex.: `https://rh.selc.rr.gov.br` |
| `APP_KEY` | gere com `docker run --rm php:8.3-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"` |
| `DB_PASSWORD` | uma senha forte para o banco |
| `MAIL_*` | o servidor de e-mail do órgão (para os links de senha) |

Depois, um comando sobe tudo (sistema, fila e banco PostgreSQL):

```bash
docker compose up -d --build
```

E um comando cria o primeiro órgão e o administrador geral:

```bash
docker compose exec app php artisan nexrh:instalar
```

O sistema fica em `http://<servidor>:8080` (mude a porta com `NEXRH_PORTA` no `.env`). Em produção, coloque um proxy com HTTPS na frente (Nginx, Caddy ou o balanceador do órgão) e defina `TRUSTED_PROXIES` com o IP dele.

### Para demonstração

Com `NEXRH_DEMO=true` no `.env` (e `APP_ENV` diferente de `production`), o sistema cria dados **fictícios**: o órgão SELC com GAB, ASJUR, COAM, DTI, DRH e DAC, um segundo órgão para mostrar o isolamento, e um usuário por perfil. A senha de todos é `NexRH@demo2026`.

| E-mail | Perfil |
|---|---|
| `admin@nexrh.local` | Administrador geral |
| `admin.selc@nexrh.local` | Administrador do órgão (SELC) |
| `rh.selc@nexrh.local` | RH |
| `chefia.dti@nexrh.local` | Chefia (DTI) |
| `servidor.selc@nexrh.local` | Servidor |
| `auditor.selc@nexrh.local` | Auditor |
| `admin.sedemo@nexrh.local` | Administrador de outro órgão |

Os administradores precisam ativar a verificação em duas etapas no primeiro acesso. Para uma demonstração rápida, dá para desligar com `NEXRH_EXIGIR_2FA=false` (nunca em produção).

## Cópia de segurança (backup) e restauração

```bash
./scripts/backup.sh                 # grava em ./backups/nexrh_AAAA-MM-DD_HHMMSS
./scripts/restaurar.sh ./backups/nexrh_2026-10-22_020000
```

O backup copia o banco de dados e os arquivos enviados, confere tudo com soma de verificação e apaga cópias com mais de 30 dias (`NEXRH_BACKUP_DIAS`). Agende todo dia, por exemplo às 2h:

```
0 2 * * * cd /opt/nexrh-publico && ./scripts/backup.sh >> /var/log/nexrh-backup.log 2>&1
```

Guarde uma cópia fora do servidor. A restauração pede que você digite `RESTAURAR` antes de substituir os dados atuais.

## Atualização

```bash
git pull
docker compose up -d --build
```

As mudanças no banco rodam sozinhas ao subir. Faça um backup antes.

## Desenvolvimento

Precisa de PHP 8.3 (com `pdo_pgsql`, `intl`, `zip`, `gd`), Composer, Node 22 e PostgreSQL 16.

```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate --seed       # perfis + dados fictícios
php artisan serve
```

Testes (usam o banco `nexrh_test`):

```bash
php artisan test
vendor/bin/pint        # padrão de código
```

## Documentação

- [Requisitos da Fase 1](docs/requisitos.md)
- [Plano de implementação](docs/plano-implementacao.md)
- [Decisões técnicas](docs/decisoes.md)

## Tecnologia

Laravel 13, PHP 8.3, PostgreSQL 16, Fortify (autenticação e duas etapas), spatie/laravel-permission (perfis), Tailwind CSS 4. Fonte Plus Jakarta Sans hospedada no próprio sistema, sem chamadas a servidores externos.
