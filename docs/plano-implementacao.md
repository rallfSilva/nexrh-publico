# Plano de implementação da Fase 1

| Entrega | Conteúdo | Situação |
|---|---|---|
| 1 | Base: repositório, Docker, login, perfis, órgãos e unidades, auditoria, layout | Concluída |
| 2 | Cargos e vagas, com importação e relatório de divergências | Em teste pelo cliente |
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

## Entrega 2: o que testar

Use só a planilha de exemplo fictícia (menu **Modelos de planilha** ou `resources/modelos-importacao/exemplo_estrutura_cargos_ficticia.xlsx`). A planilha real só entra depois de validados segurança, permissões e backup.

1. Entrar como `admin.selc@nexrh.local` e abrir **Cargos e vagas**: devem aparecer 168 cargos, 49 remanejados, 95 ocupados e 24 vagos.
2. Abrir um cargo, editar uma vaga: marcar como ocupada sem CPF ou com CPF inválido (deve recusar); conferir que o CPF aparece mascarado.
3. Criar um cargo novo com 3 vagas, aumentar para 5 e tentar reduzir para 2 com a última vaga ocupada (deve recusar).
4. Em **Importar planilha**, enviar a planilha de exemplo e ler o relatório: o resumo da planilha diz 97 ocupados e 23 vagos, mas a contagem das vagas dá 95 e 24; o cabeçalho do Assistente Executivo não fecha (17 ocupadas informadas, 15 na lista).
5. Imprimir ou salvar o relatório em PDF.
6. Confirmar a importação e ver que os números não mudam (a mesma planilha não duplica nada). Na trilha de auditoria, a importação aparece em um registro só.
7. Entrar como `auditor.selc@nexrh.local`: vê os cargos, mas não edita nem importa. Entrar como `servidor.selc@nexrh.local`: o menu de cargos não aparece.
8. Entrar como `admin.sedemo@nexrh.local` e confirmar que não aparece nada do quadro da SELC.
