# Requisitos da Fase 1

Escopo aprovado pelo cliente em 22/10/2026. Órgão piloto: SELC/RR (Secretaria de Estado de Licitação e Contratação).

## Problema

Hoje a gestão de pessoas da SELC está em planilhas Excel e pastas. Documentos são difíceis de achar, e as férias de colegas do mesmo setor se sobrepõem por falta de controle.

## Módulos da Fase 1

1. **Acesso e permissões**: perfis administrador geral, administrador do órgão, RH, chefia, servidor e auditor; verificação em duas etapas para administradores.
2. **Órgãos e organograma**: vários órgãos isolados entre si; unidades em árvore com chefe e mínimo de pessoas presentes nas férias.
3. **Cargos e vagas**: quadro com importação da planilha atual e relatório de divergências.
4. **Servidores**: cadastro conforme a ficha cadastral oficial (SEGAD), com geração da ficha em PDF.
5. **Dossiê**: documentos do servidor organizados por tipo.
6. **Dependentes**: com os códigos de parentesco da ficha e finalidade (IR, previdência, salário-família).
7. **Férias**: planejamento com bloqueio de conflito e sugestão de período.
8. **Painel e relatórios**.
9. **Auditoria**: trilha somente inclusão de todas as ações.
10. **API inicial**: consulta para integrações futuras.

Ficam para fases seguintes: frequência, portal do servidor no celular, capacitação, webhooks, integração com DOE, folha e eSocial, tema escuro.

## Regras permanentes

- Demonstrações e testes usam somente dados fictícios. Nenhum nome, CPF ou salário real.
- Dados reais só entram depois de validados segurança, permissões e backup.
- Cada entrega é testada pelo cliente antes da próxima começar.

## Critérios de aceite da Fase 1

- Instala com Docker em um comando.
- A importação da planilha de junho/2026 mostra 168 cargos e aponta a divergência do Assistente Executivo.
- Férias em conflito são bloqueadas.
- Um órgão não vê dados de outro.
- CPF aparece mascarado nas exportações.
- A API responde consultas.
- Backup e restauração funcionam.
