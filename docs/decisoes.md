# Decisões técnicas

## Laravel com páginas renderizadas no servidor (Blade)

Menos peças para manter e hospedar em um órgão público: um contêiner, sem servidor Node em produção. Telas interativas mais ricas (como o calendário de férias) podem receber JavaScript pontual depois.

## PostgreSQL

Banco livre, comum em governo, com JSONB (valores antigos e novos da auditoria) e triggers. A imutabilidade da auditoria é garantida no próprio banco por trigger que bloqueia UPDATE, DELETE e TRUNCATE.

## Vários órgãos em um só banco

Cada tabela de dados tem `orgao_id`, e um escopo global (`BelongsToOrgao`) filtra tudo pelo órgão de quem está logado. Registros de outro órgão respondem 404, para não revelar que existem. O administrador geral (`orgao_id` nulo) vê todos. O modelo `User` não usa o escopo global (ele é carregado durante a própria autenticação); a filtragem de usuários é explícita (`User::visiveisPara`).

## Usuário não é servidor

`users` guarda quem acessa o sistema. O cadastro de servidores (Entrega 3) é outra tabela, ligada ao usuário quando o servidor tiver acesso. Assim é possível cadastrar servidores que nunca vão entrar no sistema.

## Senha definida pelo próprio usuário

O administrador nunca define nem vê senha: o cadastro envia um link de criação de senha por e-mail. Senhas exigem 10 caracteres, maiúscula, minúscula e número.

## Verificação em duas etapas obrigatória para administradores

Controlada por `NEXRH_EXIGIR_2FA` (padrão: ligada). Usa TOTP (aplicativos autenticadores), sem custo de SMS.

## Fonte e arquivos sem servidores externos

A fonte Plus Jakarta Sans é empacotada no build (Fontsource). Nenhuma página chama CDN externa, o que evita repassar o IP dos usuários a terceiros (LGPD).

## Auditoria de modelos

O trait `Auditable` grava criação, alteração e exclusão com os campos alterados. Campos sensíveis (senha, segredos de duas etapas, tokens) nunca são gravados. Eventos de autenticação vêm de um subscriber de eventos (`AuditarAutenticacao`).

## Dados pessoais do servidor criptografados

CPF, documentos e dados bancários do servidor ficam criptografados no banco (`encrypted` do Laravel, com a chave da aplicação). Para buscar e impedir CPF repetido sem decifrar, o servidor guarda também `cpf_hash`, um HMAC-SHA256 dos dígitos com a mesma chave, único por órgão. Por isso a `APP_KEY` precisa entrar no plano de backup: sem ela os dados não podem ser lidos.

## Campos protegidos na auditoria

Além dos campos que nunca entram na trilha (senha, segredos), o `Auditable` aceita campos "protegidos": a trilha registra que mudaram, com o valor trocado por "[protegido]". Assim fica a prova da alteração sem espalhar CPF, documentos ou dados sensíveis da LGPD pela trilha. Abrir uma ficha e baixar o PDF também ficam registrados.

## PDF gerado no servidor

A ficha em PDF usa o dompdf (`barryvdh/laravel-dompdf`), em PHP puro, sem navegador nem serviço externo, com carregamento de arquivos remotos desligado.

## Arquivos do dossiê criptografados, conferidos e nunca apagados

O arquivo é cifrado com a chave da aplicação antes de ir para o disco privado (`storage/app/private/dossie`), e o SHA-256 do original é conferido a cada abertura. O tipo é verificado pela assinatura do conteúdo (finfo), não pela extensão nem pelo que o navegador informa; só PDF, JPG e PNG. Na entrega, `nosniff` impede o navegador de adivinhar outro tipo e imagens abrem com CSP `sandbox` (o PDF não, porque o leitor de PDF do Chrome não funciona em sandbox). Documento não é excluído: é arquivado com motivo, e o arquivo continua guardado.

## Dossiê e dependentes sem permissão nova

Usam as permissões que já existiam: `servidores.dados_pessoais` para ver e `servidores.gerenciar` junto com ela para alterar (gate `gerenciar-dossie`). Assim, auditor e chefia, que não veem dados pessoais, também não veem dossiê nem dependentes. O próprio servidor vê os dele por **Minha ficha**.

## "Universitário" sai do código de parentesco

A ficha tem o código 04 (filho universitário) e 07 (equiparado universitário) e, à parte, a coluna "universitário". Para os dois nunca se contradizerem, o sistema grava o código e deriva o "universitário" dele; na importação, "universitário = sim" com o código 03 vira 04. Os limites de idade não bloqueiam o cadastro: viram pendência, porque a idade muda com o tempo.

## Regras de férias provisórias e por órgão

A norma de férias da SELC ainda não chegou. As regras ficam numa tabela por órgão (`regras_ferias`), com valores provisórios (30 dias por período, até 3 etapas de pelo menos 10 dias, no máximo 2 períodos acumulados, 30 dias de antecedência no pedido do servidor) que o RH muda na tela. Quando a norma chegar, basta ajustar os números; nada fica fixo no código.

## Cada etapa é uma linha, e "em gozo" e "gozada" são calculadas

A tabela `ferias` guarda uma linha por etapa, com a situação de decisão: programada (aguardando aprovação), aprovada, recusada, cancelada ou interrompida. "Em gozo" e "gozada" vêm das datas de uma etapa aprovada, para nunca ficarem desatualizadas. Na interrupção, a etapa passa a ter só os dias gozados (o fim previsto fica guardado) e o resto volta ao saldo.

## Mínimo de presentes por unidade, sem contar as subunidades

O bloqueio usa o `minimo_presentes_ferias` da própria unidade e conta só quem está lotado nela, sem as subordinadas: é a equipe que a chefia de fato organiza. Férias aguardando aprovação já ocupam a vaga, para dois pedidos não passarem juntos. Afastamentos não passam pelo bloqueio (licença médica não espera vaga), mas contam como ausência para as férias dos colegas.

## Data-base dos períodos aquisitivos

Os períodos contam do ingresso no órgão. Quem traz tempo de outro vínculo com o Estado pode ter outra data-base: o RH ajusta na ficha, ou a planilha de férias traz períodos anteriores ao ingresso. A troca só é aceita enquanto o servidor não tem férias registradas, porque depois disso mudaria o período de etapas já gozadas. A troca fica na trilha de auditoria.

## Tipo do afastamento é dado de saúde

O tipo de licença pode revelar dado sensível (saúde, gestação, doença na família). Só quem vê dados pessoais do servidor vê o tipo; chefia, auditor e a escala mostram "Licença ou afastamento". Na trilha de auditoria, tipo e observação ficam "[protegido]".

## Férias e afastamentos não são apagados

Como no dossiê, nada é excluído: férias são recusadas, canceladas ou interrompidas, e afastamentos são cancelados, sempre com motivo e com quem fez.

## Chaves de API próprias, sem Sanctum

A API é para sistemas, não para pessoas, então a chave pertence ao órgão e não a um usuário. Cada chave tem nome, escopos (unidades, cargos, servidores, férias), prazo opcional e pode ser revogada. O texto `nxr_...` aparece uma vez; o banco guarda só o SHA-256 e um prefixo para reconhecer a chave na lista. O limite é de 60 consultas por minuto por chave, e cada uso atualiza a data, o IP e o contador.

## A API consulta como um usuário técnico do órgão

Durante a chamada, a chave vira um usuário técnico, só em memória, com o `orgao_id` da chave. Assim o mesmo escopo global de órgão das telas vale para a API, e nenhuma consulta precisa lembrar de filtrar por órgão. A API não usa `withoutGlobalScopes()`.

## A API e os relatórios não entregam dados pessoais

A API devolve só o que um sistema de ponto ou de escala precisa: nome, matrícula, CPF mascarado, cargo, unidade, vínculo e situação, e nas ausências só "férias" ou "licença ou afastamento". Os relatórios e as exportações também saem com o CPF mascarado, e o tipo de licença só aparece para quem vê dados pessoais.

## Toda exportação de relatório fica na trilha

Excel e PDF saem do sistema e podem circular, então cada exportação gera um registro `exportacao` com o relatório, o formato, os filtros e o número de linhas. As telas de relatório mostram até 300 linhas; a exportação leva todas.

## Marcação de ponto não é apagada

Marcações do servidor, do relógio ou do RH nunca são apagadas nem editadas. Para corrigir, o RH anula com motivo (a marcação fica no espelho, riscada, e deixa de contar) e inclui a certa, também com motivo e com a marca **RH**. Tudo fica na trilha de auditoria, com o IP de quem registrou.

## Ponto só de dentro da rede do órgão

O registro pelo sistema usa a hora do servidor (nunca a do navegador) e só é aceito de IPs das redes cadastradas em **Regras e feriados**. Por padrão valem as faixas privadas (10/8, 172.16/12, 192.168/16) e a máquina local, o que serve para a instalação no computador do órgão. Quando o sistema for publicado para a rede, o RH ou a TI devem restringir às faixas das sedes. O órgão pode desligar a exigência, por exemplo para quem bate o ponto só pelo relógio.

## Jornada padrão pela carga da ficha

Ninguém precisa cadastrar jornada para começar: a carga horária da ficha (6h ou 8h diárias) define o horário padrão do órgão (07:30 às 13:30, ou 08:00 às 12:00 e 14:00 às 18:00, de segunda a sexta). O RH só grava jornada para quem foge do padrão (outro horário, teletrabalho, dispensa do ponto), com data de início; a anterior termina na véspera, então os meses passados não mudam. Mudar o horário padrão nas regras vale para todos os meses de quem não tem jornada própria.

## Relógio de ponto pelo arquivo AFD

O relógio de ponto (REP) entra pelo arquivo AFD, que todos os modelos exportam, nos leiautes da Portaria 671/2021 (CPF) e da 1.510/2009 (PIS). Só as marcações (registro tipo 3) são lidas. A mesma pessoa no mesmo minuto é uma marcação só, então o arquivo do mês pode ser enviado de novo a cada semana sem duplicar. Marcações com data no futuro bloqueiam a importação, porque indicam relógio com a hora errada.

## Feriados sugeridos, conferidos pelo RH

O sistema calcula os feriados nacionais (inclusive os móveis, pela data da Páscoa) e os de Roraima e Boa Vista, mas só os grava quando o RH pede, ano a ano, para conferir com o decreto do Estado. Carnaval, Corpus Christi e Dia do Servidor entram como ponto facultativo. Feriado e ponto facultativo não contam como dia de trabalho no espelho.

## Manual dentro do sistema

O manual fica em **Ajuda**, na mesma versão do sistema instalado, e marca as partes do perfil de quem está lendo. As imagens usam só dados fictícios. A TI tem um guia à parte, em `docs/guia-ti.md`.
