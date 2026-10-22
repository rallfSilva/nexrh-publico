# Plano de implementação da Fase 1

| Entrega | Conteúdo | Situação |
|---|---|---|
| 1 | Base: repositório, Docker, login, perfis, órgãos e unidades, auditoria, layout | Em teste pelo cliente |
| 2 | Cargos e vagas, com importação e relatório de divergências | A fazer |
| 3 | Servidores (ficha SEGAD, ficha em PDF) | A fazer |
| 4 | Dossiê e dependentes | A fazer |
| 5 | Férias com bloqueio de conflito e mínimo por setor | A fazer |
| 6 | Painel, relatórios, API inicial e manual | A fazer |

## Pendências do cliente

- Lista oficial de unidades e chefes (a demonstração usa GAB, ASJUR, COAM, DTI, DRH e DAC).
- Norma de férias usada pela SELC, antes da Entrega 5.
- Mínimo de pessoas por setor durante as férias (provisório: 2, ou 1 para unidades pequenas).
- Onde o sistema vai ficar hospedado, antes da Entrega 6.

## Entrega 1: o que testar

1. Entrar com cada perfil de demonstração e ver que o menu muda conforme o perfil.
2. Entrar como `admin.selc@nexrh.local`, ativar a verificação em duas etapas no celular, sair e entrar de novo com o código.
3. Cadastrar e editar unidades; tentar pôr uma unidade subordinada a ela mesma (deve recusar).
4. Cadastrar um usuário, trocar o perfil dele, desativar e ver a sessão dele cair.
5. Abrir a trilha de auditoria e conferir que tudo isso ficou registrado com valor antigo e novo.
6. Entrar como `admin.sedemo@nexrh.local` e confirmar que não aparece nada da SELC.
7. Baixar os modelos de planilha.
