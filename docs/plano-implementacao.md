# Plano de implementação da Fase 1

| Entrega | Conteúdo | Situação |
|---|---|---|
| 1 | Base: repositório, Docker, login, perfis, órgãos e unidades, auditoria, layout | Concluída |
| 2 | Cargos e vagas, com importação e relatório de divergências | Concluída |
| 3 | Servidores (ficha SEGAD, ficha em PDF) | Concluída |
| 4 | Dossiê e dependentes | Concluída |
| 5 | Férias com bloqueio de conflito e mínimo por setor | Em teste pelo cliente |
| 6 | Painel, relatórios, API inicial e manual | A fazer |

## Pendências do cliente

- Lista oficial de unidades e chefes (a demonstração usa GAB, ASJUR, COAM, DTI, DRH e DAC).
- Norma de férias usada pela SELC: as regras atuais são provisórias e o RH ajusta em **Férias › Regras de férias**.
- Mínimo de pessoas por setor durante as férias (a demonstração usa GAB 7, ASJUR 6, COAM 16, DTI 11, DRH 5 e DAC 22).
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

## Entrega 4: o que testar

Use só os dados fictícios da demonstração e a planilha `exemplo_dependentes_ficticio.xlsx` (menu **Modelos de planilha**). Para testar envio de documentos, use arquivos sem dados reais (por exemplo, um PDF qualquer de teste).

1. Entrar como `rh.selc@nexrh.local`, abrir a ficha de Ana Beatriz Martins, aba **Dossiê**: 8 de 10 documentos da posse validados, uma CNH vencida, um documento recusado com o motivo e dois aguardando validação.
2. Clicar em **Abrir** e **Baixar** em um documento. Depois, na trilha de auditoria, conferir que a visualização e o download ficaram registrados.
3. **Incluir documento**: tentar sem arquivo, com um arquivo .txt renomeado para .pdf (deve recusar) e com validade antes da data do documento (deve recusar). Incluir um PDF válido: entra como validado.
4. Abrir **Documentos a validar** no menu: validar um documento e recusar o outro informando o motivo.
5. Arquivar um documento (pede motivo). Ele sai da lista e aparece em "Arquivados", com o motivo. Não há como apagar.
6. Aba **Dependentes**: ver os dois filhos da Ana. Cadastrar um dependente novo; testar CPF inválido, o CPF da própria servidora, um segundo cônjuge e deficiência sem o tipo (devem recusar).
7. Cadastrar um filho de 16 anos com salário-família e um de 23 anos com IR: os dois aparecem nas pendências da ficha. Marcar o de 23 como universitário (código 04): a pendência dele some.
8. Encerrar um dependente (data e motivo) e reativar.
9. Baixar a **Ficha em PDF** e conferir a seção de dependentes.
10. Em **Importar planilha** › Dependentes, enviar o exemplo fictício: 67 linhas prontas, 5 com erro (titular inexistente, CPF inválido, segundo cônjuge, sem data de nascimento e salário-família para o pai) e 2 avisos de idade. Nenhum CPF completo aparece. Confirmar e enviar de novo: nada é duplicado.
11. Entrar como `servidor.selc@nexrh.local`: em **Minha ficha**, aba Dossiê, ver o motivo da recusa e enviar um documento (fica aguardando validação). Ver os próprios dependentes, sem poder alterar.
12. Entrar como `auditor.selc@nexrh.local` e como `chefia.dti@nexrh.local`: as abas Dossiê e Dependentes não aparecem, e os endereços dos arquivos não abrem (acesso negado ou página não encontrada).
13. Entrar como `admin.sedemo@nexrh.local` e confirmar que nada do dossiê da SELC aparece.

## Entrega 5: o que testar

Use só os dados fictícios da demonstração e as planilhas `exemplo_afastamentos_ficticio.xlsx` e `exemplo_ferias_ficticio.xlsx` (menu **Modelos de planilha**). As regras de férias são provisórias até chegar a norma da SELC.

1. Entrar como `rh.selc@nexrh.local` e abrir **Férias**: escolher o setor DTI e ir para janeiro de 2027. Três pessoas estão fora de 11 a 30/01 e a linha **Presentes** fica em 11, o mínimo do setor. Trocar para **Ano** e ver o mapa de 2026.
2. Abrir a ficha de Ana Beatriz Martins, aba **Férias**: cinco períodos desde 14/03/2022, os anteriores todos gozados e 30 dias de saldo no período em curso.
3. Clicar em **Marcar férias** num servidor de outro setor: escolher o período, a quantidade de dias e clicar numa das datas sugeridas. Conferir o último dia e a volta ao trabalho. Tentar 5 dias (abaixo do mínimo da etapa), mais dias do que o saldo e uma data que coincida com férias já marcadas: deve recusar.
4. Na DTI, tentar marcar férias para mais alguém entre 11 e 30/01/2027: o sistema recusa e diz em que dia o setor ficaria abaixo do mínimo e quem já está fora.
5. **Registrar afastamento** numa ficha (por exemplo, licença para tratamento de saúde) e ver que a escala passa a mostrar a pessoa fora. Tentar outro afastamento ou férias em cima dele: deve recusar. Cancelar o afastamento, com motivo.
6. Ver a ficha de Ana Pacheco Teixeira (férias interrompidas e remarcadas) e a de João Gomes Siqueira (férias canceladas). Interromper férias em gozo de alguém e cancelar férias futuras: o saldo volta.
7. Em **Regras de férias**, mudar o máximo de etapas para 2 e conferir que a regra nova vale para as próximas marcações. Voltar para 3.
8. Entrar como `servidor.selc@nexrh.local`: em **Minha ficha**, aba Férias, clicar em **Pedir férias**, usar uma data sugerida e enviar. O pedido fica "Aguardando aprovação" e pode ser retirado. Tentar uma data com menos de 30 dias de antecedência: deve recusar.
9. Entrar como `chefia.dti@nexrh.local`: o menu **Férias** mostra 2 pedidos a aprovar. Aprovar um e recusar o outro com motivo. A chefia vê a escala só da DTI.
10. Entrar de novo como o RH: o pedido da Ana (DAC, sem chefia cadastrada) aparece em **Férias a aprovar**.
11. Em **Importar planilha** › Afastamentos e depois › Férias, enviar os exemplos fictícios: 14 afastamentos prontos e 3 com erro; 397 etapas de férias prontas e 5 com erro (CPF de ninguém do órgão, período aquisitivo que não confere, datas em cima de outra etapa, DTI abaixo do mínimo e saldo estourado). Nenhum CPF completo aparece. Confirmar e enviar de novo: nada é duplicado.
12. Entrar como `auditor.selc@nexrh.local`: vê a escala e as férias, sem botões de marcar ou aprovar, e os afastamentos aparecem só como "Licença ou afastamento".
13. Entrar como `admin.sedemo@nexrh.local` e confirmar que nada das férias da SELC aparece.
