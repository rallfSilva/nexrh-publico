# Plano de implementação da Fase 1

| Entrega | Conteúdo | Situação |
|---|---|---|
| 1 | Base: repositório, Docker, login, perfis, órgãos e unidades, auditoria, layout | Concluída |
| 2 | Cargos e vagas, com importação e relatório de divergências | Concluída |
| 3 | Servidores (ficha SEGAD, ficha em PDF) | Em teste pelo cliente |
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

## Entrega 3: o que testar

Use só os dados fictícios da demonstração ou a planilha `exemplo_servidores_ficticio.xlsx` (menu **Modelos de planilha**). Dados reais de servidores só entram depois de validados segurança, permissões e backup.

1. Entrar como `rh.selc@nexrh.local` e abrir **Servidores**: devem aparecer 94 ativos, com busca por nome, matrícula, decreto ou CPF completo, e filtros por unidade e cargo.
2. Abrir uma ficha e passar pelas abas: dados funcionais, dados pessoais (CPF, documentos e banco completos para o RH), declarações e linha do tempo. Conferir as pendências ao lado.
3. Clicar em **Ficha em PDF** e conferir o documento no padrão da SEGAD.
4. Em **Novo servidor**, tentar avançar com campos vazios (deve parar na etapa e mostrar o que falta); testar CPF inválido ou repetido, homem sem reservista e escolha de uma vaga já ocupada (deve recusar). Concluir o cadastro e ver a vaga ficar ocupada em **Cargos e vagas**.
5. Editar o servidor trocando a vaga: a vaga antiga volta a ficar livre e a troca aparece na linha do tempo.
6. Registrar uma declaração de bens com valores e uma de parentesco; conferir que a pendência some.
7. Registrar um desligamento: a vaga é liberada e o servidor passa para a aba **Desligados**. Editar e escolher uma vaga para readmitir.
8. Em **Importar planilha** › Servidores, enviar o exemplo fictício: 94 linhas prontas, 5 com erro (3 CPFs inválidos, uma lotação desconhecida e um campo obrigatório vazio). Nenhum CPF completo aparece no relatório. Confirmar e enviar de novo: nada é duplicado.
9. Entrar como `auditor.selc@nexrh.local`: vê as fichas com CPF mascarado, sem documentos nem dados bancários, e sem botão de PDF ou edição.
10. Entrar como `chefia.dti@nexrh.local`: vê só os servidores da DTI.
11. Entrar como `servidor.selc@nexrh.local`: em **Minha ficha** vê a própria ficha e baixa o PDF; o menu **Servidores** não aparece.
12. Abrir a trilha de auditoria: cada abertura de ficha e cada download aparecem; nas alterações de CPF ou documentos aparece "[protegido]" em vez do valor.
13. Entrar como `admin.sedemo@nexrh.local` e confirmar que nenhum servidor da SELC aparece.
