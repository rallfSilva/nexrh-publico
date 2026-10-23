# NexRH Público

Gestão de pessoas para a administração pública: estrutura do órgão, usuários e perfis, servidores, cargos, férias e dossiê, com trilha de auditoria de tudo o que é feito. Órgão piloto: SELC/RR.

> **Situação:** Fase 1, Entrega 5 (férias, afastamentos e escala do setor). Veja o que entra em cada entrega em [docs/plano-implementacao.md](docs/plano-implementacao.md).

## O que já funciona

### Entrega 5: férias, afastamentos e escala do setor

- **Regras de férias por órgão**, editáveis pelo RH em **Férias › Regras de férias**: dias por período, número de etapas, mínimo de dias por etapa, períodos que podem acumular e antecedência do pedido. As regras atuais são **provisórias** (30 dias, até 3 etapas de pelo menos 10 dias, no máximo 2 períodos acumulados, pedido com 30 dias de antecedência) até chegar a norma da SELC.
- **Períodos aquisitivos** gerados sozinhos a partir do ingresso no órgão. Quem traz tempo de outro vínculo com o Estado pode ter outra data-base, ajustada pelo RH (ou pela importação) enquanto não há férias registradas.
- **Pedido e aprovação**: o servidor pede em **Minha ficha**; a chefia do setor (ou o RH) aprova ou recusa com motivo, na fila **Férias a aprovar**. Ninguém aprova as próprias férias. O RH também marca direto, já aprovadas. Cancelar e interromper (com o motivo da lista da Lei 8.112, art. 80) devolvem o saldo.
- **Bloqueio de conflitos**: o sistema não aceita férias que coincidam com outras férias ou afastamentos do servidor, nem que deixem o setor abaixo do **mínimo de presentes** da unidade. A mensagem diz o dia e quem já está fora.
- **Sugestão de datas**: ao marcar ou pedir, aparecem as três próximas janelas livres, de preferência começando numa segunda-feira.
- **Licenças e afastamentos** (13 tipos do modelo 06), com documento e processo SEI. O tipo só aparece para quem vê dados pessoais; os demais veem "Licença ou afastamento". Nada é apagado: cancela-se com motivo.
- **Escala do setor**: grade do mês, dia a dia, com a linha de presentes (vermelha abaixo do mínimo), e mapa do ano. A chefia vê os setores que chefia.
- **Importação dos modelos 05 e 06**, identificando o servidor pelo CPF, com exemplos **fictícios**: 14 afastamentos (3 linhas erradas de propósito) e 397 etapas de férias de 94 servidores (5 linhas erradas de propósito).

### Entrega 4: dossiê e dependentes

- **Dossiê digital**: documentos de cada servidor organizados por categoria (identificação, escolaridade, admissão, atos funcionais, capacitação, dependentes, saúde e outros), com data, validade e situação. Mostra quantos dos 10 documentos exigidos na posse já estão validados e avisa documento vencido ou perto de vencer.
- **Envio pelo servidor**: em **Minha ficha**, o servidor envia os próprios documentos; eles ficam "aguardando validação" até o RH validar ou recusar (com motivo, que o servidor vê). O RH acompanha tudo em **Documentos a validar**.
- **Arquivos protegidos**: só PDF, JPG e PNG, conferidos pelo conteúdo (não pela extensão), até 10 MB. Ficam criptografados no disco, com conferência de integridade a cada abertura. Cada visualização e download fica na trilha de auditoria. Nada é apagado: o RH arquiva, com motivo.
- **Dependentes** com os códigos de parentesco da ficha (tabela do eSocial) e as finalidades: imposto de renda, previdência e salário-família. O sistema confere CPF, um só cônjuge ativo e salário-família só para filho ou equiparado, e aponta nas pendências quem passou do limite de idade (IR até 21 anos ou 24 se universitário, salário-família até 14, previdência até 21; sem limite para pessoa com deficiência). Dependente não é apagado: é encerrado com data e motivo, e pode ser reativado.
- **Ficha em PDF** com a seção de dependentes.
- **Quem vê**: dossiê e dependentes só para RH, administração do órgão e o próprio servidor. Auditor e chefia não veem.
- **Importação do modelo 04** (dependentes), com relatório por linha e exemplo **fictício** em `resources/modelos-importacao/exemplo_dependentes_ficticio.xlsx` (67 dependentes dos servidores do exemplo 03 e 5 linhas com erro de propósito).

### Entrega 3: servidores

- **Ficha cadastral completa**, com todos os campos da ficha da SEGAD: nomeação, dados pessoais, documentos, formação e vínculos, endereço, contato e dados bancários. O cadastro é feito em 5 etapas, cada uma conferida antes de seguir, e os campos condicionais (reservista, CNH, deficiência, vínculo efetivo) só aparecem quando se aplicam.
- **Ligação com o quadro de cargos**: o servidor ocupa uma vaga do cargo; a vaga passa a mostrar nome, CPF e ato dele. Trocar de vaga ou registrar o desligamento libera a anterior. A readmissão volta a ocupar uma vaga.
- **Declarações** de grau de parentesco, acumulação de cargos e bens (por ano), com histórico e pendências na ficha (sem declaração, bens do ano em falta, CNH vencida, reservista).
- **Linha do tempo** do servidor: ingresso, nomeação, mudança de lotação, desligamento e readmissão.
- **Ficha em PDF** no padrão da SEGAD, pronta para assinar, e **Minha ficha** para o próprio servidor consultar e baixar a dele.
- **Quem vê o quê**: RH e administrador do órgão veem tudo; o auditor vê a ficha sem CPF completo, documentos nem dados bancários; a chefia vê só a equipe das unidades que chefia; o servidor vê só a própria ficha.
- **Proteção dos dados pessoais**: CPF, documentos e dados bancários ficam criptografados no banco. A trilha de auditoria registra que esses campos mudaram, sem guardar o valor, e registra cada abertura de ficha e cada download do PDF.
- **Importação do modelo 03** (abas Servidores, Parentesco, Acumulação e Bens): linhas com erro ficam de fora e aparecem no relatório; as demais entram. Reimportar atualiza quem já existe (mesmo CPF), sem duplicar servidores nem declarações.
- Planilha de exemplo **fictícia** em `resources/modelos-importacao/exemplo_servidores_ficticio.xlsx`, com 94 servidores que ocupam as vagas do quadro fictício e 5 linhas com erro de propósito.

### Entrega 2: cargos e vagas

- **Quadro de cargos**: cargos com símbolo, natureza (comissão, função gratificada, subsídio ou efetivo), remuneração e base legal; cada vaga numerada com situação (ocupada, vaga, remanejada ou devolvida), ocupante, ato de nomeação e órgão de destino.
- **Totais sempre conferidos**: total, remanejados, disponíveis, ocupados e vagos são contados vaga a vaga, nunca digitados.
- **Importação da planilha de estrutura de cargos** (formato usado pela SELC ou modelo NexRH): o sistema analisa, mostra a prévia e o **relatório de divergências** (números do cabeçalho que não fecham, resumo que não confere, CPF inválido, numeração repetida ou com falhas), e só grava depois da confirmação. Pode ser impresso ou salvo em PDF.
- **Proteção do CPF**: guardado criptografado no banco, mostrado sempre mascarado e fora da trilha de auditoria.
- Planilha de exemplo **fictícia** em `resources/modelos-importacao/exemplo_estrutura_cargos_ficticia.xlsx`, com a mesma estrutura e as mesmas inconsistências da planilha de junho/2026.

### Entrega 1: base

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

Com `NEXRH_DEMO=true` no `.env` (e `APP_ENV` diferente de `production`), o sistema cria dados **fictícios**: o órgão SELC com GAB, ASJUR, COAM, DTI, DRH e DAC, um segundo órgão para mostrar o isolamento, um usuário por perfil o quadro de cargos importado da planilha de exemplo fictícia (168 cargos) 94 servidores fictícios importados do exemplo do modelo 03, com 67 dependentes do exemplo do modelo 04, e um dossiê de demonstração (os arquivos são PDFs gerados com a marca "documento fictício"). O usuário `servidor.selc@nexrh.local` é a servidora fictícia Ana Beatriz Martins: vê a própria ficha em **Minha ficha**, com documentos validados, dois aguardando validação e um recusado. A senha de todos é `NexRH@demo2026`.

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

Guarde uma cópia fora do servidor. **Guarde também a `APP_KEY` do `.env`** em local seguro e separado do backup (cofre de senhas do órgão, por exemplo): CPF, documentos, dados bancários e os arquivos do dossiê ficam criptografados com ela, e sem a mesma chave o backup restaurado não consegue ler esses dados. Ela fica fora da pasta de backup de propósito, para que uma cópia vazada não sirva sozinha. A restauração pede que você digite `RESTAURAR` antes de substituir os dados atuais.

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
