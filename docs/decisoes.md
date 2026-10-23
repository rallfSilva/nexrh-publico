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

