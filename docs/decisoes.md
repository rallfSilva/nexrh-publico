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
