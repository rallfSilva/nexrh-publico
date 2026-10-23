# Plano de implementação

## Fase 1: cadastro, férias e painel

| Entrega | Conteúdo | Situação |
|---|---|---|
| 1 | Base: repositório, Docker, login, perfis, órgãos e unidades, auditoria, layout | Concluída |
| 2 | Cargos e vagas, com importação e relatório de divergências | Concluída |
| 3 | Servidores (ficha SEGAD, ficha em PDF) | Concluída |
| 4 | Dossiê e dependentes | Concluída |
| 5 | Férias com bloqueio de conflito e mínimo por setor | Concluída |
| 6 | Painel, relatórios, API inicial e manual | Concluída |

## Fase 2: frequência

| Entrega | Conteúdo | Situação |
|---|---|---|
| 7 | Jornada e registro do ponto: meu ponto, espelho, frequência do setor, feriados, importação do relógio | Em teste pelo cliente |
| 8 | Ocorrências e abonos: justificativas do servidor, decisão da chefia, abonos pelo RH | Prevista |
| 9 | Fechamento do mês e boletim de frequência para a folha | Prevista |

## Pendências do cliente

- Lista oficial de unidades e chefes (a demonstração usa GAB, ASJUR, COAM, DTI, DRH e DAC).
- Norma de férias usada pela SELC: as regras atuais são provisórias e o RH ajusta em **Férias › Regras de férias**.
- Mínimo de pessoas por setor durante as férias (a demonstração usa GAB 7, ASJUR 6, COAM 16, DTI 11, DRH 5 e DAC 22).
- Norma de frequência da SELC (horários, tolerância, banco de horas) e o decreto de pontos facultativos do Estado: as regras atuais são provisórias e o RH ajusta em **Frequência › Regras e feriados**.
- Faixas de rede (IP) das sedes do órgão, de onde o ponto pode ser registrado.
- Modelo do relógio de ponto em uso, se houver, para testar a importação com um arquivo AFD de verdade (só depois da validação de segurança).
- Onde o sistema vai ficar hospedado. Enquanto não se decide, a premissa é o computador local (Windows com Docker), com a cópia de segurança diária pelo Agendador de Tarefas.

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

## Entrega 7: o que testar

Use só os dados fictícios da demonstração.

1. Entrar como `servidor.selc@nexrh.local` e abrir **Meu ponto**: o relógio mostra a hora do sistema e a jornada de hoje (07:30 às 13:30). Clicar em **Registrar entrada**: a marcação aparece em "Marcações de hoje" e no espelho. Clicar de novo logo em seguida: o sistema pede para esperar. Depois de 2 minutos, o botão vira **Registrar saída**.
2. No espelho do mês, conferir os dias de "Sem expediente", os feriados (5/10 Dia de Roraima, 12/10), os dias com **Verificar** (atraso ou saída antecipada, com o motivo embaixo) e o saldo de horas. Voltar para o mês anterior com a seta e testar **Imprimir**.
3. Entrar como `rh.selc@nexrh.local` e abrir **Frequência**: escolher um setor e um mês. A tabela mostra cada pessoa com dias com registro, sem registro, incompletos, a verificar, horas e saldo. Os servidores de teletrabalho da DTI e o dispensado do GAB não têm registro esperado; quem está de férias aparece como "Férias".
4. Clicar num nome para abrir o espelho. Testar **Incluir marcação** (data, hora e motivo): ela aparece com a marca **RH**. Clicar numa marcação e **anular** com motivo: ela fica riscada e deixa de contar.
5. No mesmo espelho, **Alterar jornada**: por exemplo, 08:00-12:00, 14:00-18:00 a partir de amanhã. A jornada anterior vai para "Jornadas anteriores" e os dias passados não mudam.
6. Abrir **Frequência › Regras e feriados**: conferir o horário de 6h e de 8h, a tolerância e as redes. A tela mostra o endereço de onde você está acessando. Trocar o ano e clicar em **Incluir feriados nacionais e de RR**; incluir e retirar um ponto facultativo.
7. Abrir **Frequência › Importar relógio de ponto** e enviar o arquivo de exemplo (em **Modelos de planilha**, "exemplo_ponto_ficticio.txt"). O relatório mostra 386 marcações prontas, uma linha ilegível e um CPF que não é de ninguém. Confirmar e abrir agosto de 2026 no setor DAC. Enviar o mesmo arquivo de novo: nada é duplicado.
8. Entrar como `chefia.dti@nexrh.local`: **Frequência** mostra só a DTI, sem os botões do RH. Como servidor, o menu não tem **Frequência**.
9. Abrir **Ajuda** › **Meu ponto** e **Frequência**, e a **Trilha de auditoria** com as inclusões, anulações e a importação.

## Entrega 6: o que testar

Use só os dados fictícios da demonstração.

1. Entrar como `rh.selc@nexrh.local`: o **Painel** mostra os números do órgão, os servidores por unidade (a marca é o mínimo de presentes), as férias dos próximos 12 meses, quem está de férias agora e nos próximos 30 dias e as pendências. Clicar num número ou numa pendência leva à lista ou ao relatório certo.
2. Abrir **Relatórios** › **Servidores**: filtrar por unidade e por vínculo, conferir que o CPF aparece mascarado e baixar em **Excel** e em **PDF**. Abrir **Trilha de auditoria**: as duas exportações aparecem, com os filtros usados.
3. Em **Relatórios** › **Saldo de férias**, escolher "Só quem acumulou": aparece quem tem mais de um período com saldo. Ver também **Férias**, **Licenças e afastamentos**, **Pendências** e **Dependentes**.
4. Entrar como `chefia.dti@nexrh.local`: o painel mostra só a equipe da DTI e os relatórios trazem só a DTI. A chefia não vê quadro de cargos nem dependentes.
5. Entrar como `servidor.selc@nexrh.local`: o painel mostra o próprio saldo, as próximas férias e o que falta na ficha. O menu não tem **Relatórios**.
6. Entrar como `auditor.selc@nexrh.local`: vê o painel e os relatórios, sem dependentes, e as licenças aparecem só como "Licença ou afastamento".
7. Entrar como `admin.selc@nexrh.local` e abrir **Chaves de API**: criar uma chave só com "Unidades e organograma" e copiar. Ela aparece uma vez; ao recarregar a página, só o começo fica visível.
8. No PowerShell, testar a chave (troque `nxr_...` pela chave copiada):
   `curl.exe -H "Authorization: Bearer nxr_..." http://127.0.0.1:8090/api/v1/unidades` responde a lista de unidades; `/api/v1/servidores` responde 403 (fora do que a chave pode); sem a chave, 401. Revogar a chave e repetir: 401.
9. Abrir **Ajuda**: as partes do seu perfil vêm marcadas com "Para você". Testar **Imprimir ou salvar em PDF**.
10. Na pasta do sistema, rodar `.\scripts\backup.ps1` e conferir a pasta nova em `.\backups`. Se quiser a cópia diária, `.\scripts\agendar-backup.ps1`.

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
