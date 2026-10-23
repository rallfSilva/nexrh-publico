@php
    $base = url('/api/v1');
    $secoes = [
        'inicio' => ['Primeiros passos', ['todos']],
        'perfis' => ['Quem faz o quê', ['todos']],
        'servidor' => ['Minha ficha, documentos e férias', ['servidor', 'todos']],
        'ponto' => ['Meu ponto', ['servidor', 'chefia', 'todos']],
        'chefia' => ['Chefia: equipe, férias e frequência', ['chefia']],
        'rh-servidores' => ['Cadastro de servidores', ['rh', 'administrador-orgao']],
        'rh-importar' => ['Importar planilhas', ['rh', 'administrador-orgao']],
        'rh-dossie' => ['Dossiê e dependentes', ['rh', 'administrador-orgao']],
        'rh-ferias' => ['Férias e afastamentos', ['rh', 'administrador-orgao']],
        'rh-frequencia' => ['Frequência: jornadas, ajustes e relógio de ponto', ['rh', 'administrador-orgao']],
        'relatorios' => ['Painel e relatórios', ['rh', 'administrador-orgao', 'chefia', 'auditor']],
        'admin' => ['Administração do órgão', ['administrador-orgao', 'administrador-geral']],
        'api' => ['API de consulta', ['administrador-orgao', 'administrador-geral']],
        'backup' => ['Cópia de segurança', ['administrador-orgao', 'administrador-geral']],
        'duvidas' => ['Dúvidas frequentes', ['todos']],
    ];
    $paraMim = fn (array $quem) => in_array('todos', $quem, true) || in_array($perfil, $quem, true);
    $selo = fn (array $quem) => in_array('todos', $quem, true) ? 'Todos' : collect($quem)->map(fn ($p) => $perfis[$p] ?? $p)->implode(', ');
    $figura = function (string $nome, string $legenda) use ($imagem) {
        $src = $imagem($nome);
        return $src ? '<figure class="manual-fig"><img src="'.e($src).'" alt="'.e($legenda).'" loading="lazy" width="1400" height="900"><figcaption>'.e($legenda).'</figcaption></figure>' : '';
    };
@endphp
<x-layouts.app titulo="Manual do sistema">
    <div class="row">
        <div>
            <h1 class="h1">Manual do sistema</h1>
            <p class="sub">Como usar o NexRH Público, por perfil. Você entra como <b>{{ $perfis[$perfil] ?? 'usuário' }}</b>: as partes marcadas com <span class="b b-info">Para você</span> são as do seu dia a dia.</p>
        </div>
        <div class="btns nao-imprimir">
            <button type="button" class="btn btn-s" onclick="window.print()"><x-icone nome="download" />Imprimir ou salvar em PDF</button>
        </div>
    </div>

    <div class="manual">
        <nav class="card manual-indice nao-imprimir" aria-label="Índice do manual">
            <b>Neste manual</b>
            <ol>
                @foreach ($secoes as $id => [$titulo, $quem])
                    <li><a href="#{{ $id }}" class="{{ $paraMim($quem) && ! in_array('todos', $quem, true) ? 'meu' : '' }}">{{ $titulo }}</a></li>
                @endforeach
            </ol>
        </nav>

        <div class="manual-corpo">
            @foreach ($secoes as $id => [$titulo, $quem])
                <section class="card manual-secao {{ $paraMim($quem) && ! in_array('todos', $quem, true) ? 'minha-secao' : '' }}" id="{{ $id }}">
                    <div class="manual-cab">
                        <h2>{{ $titulo }}</h2>
                        <span class="b {{ $paraMim($quem) && ! in_array('todos', $quem, true) ? 'b-info' : 'b-mute' }}">{{ $paraMim($quem) && ! in_array('todos', $quem, true) ? 'Para você' : $selo($quem) }}</span>
                    </div>

                    @switch($id)
                        @case('inicio')
                            <ol class="passos">
                                <li><b>Entre</b> com o e-mail e a senha que o administrador do órgão cadastrou. Na primeira vez, troque a senha pelo link "Esqueci a senha".</li>
                                <li><b>Ative a verificação em duas etapas</b> em <a href="{{ route('seguranca') }}">Minha segurança</a>, com um aplicativo de códigos no celular (Google Authenticator, Microsoft Authenticator ou outro). Para administradores e RH ela é obrigatória.</li>
                                <li><b>Use o menu à esquerda.</b> Ele só mostra o que o seu perfil pode ver. O <b>Painel</b> resume o que pede atenção agora.</li>
                                <li><b>Saia</b> pelo botão no canto de cima quando terminar, principalmente em computador compartilhado. A sessão também cai sozinha depois de um tempo parado.</li>
                            </ol>
                            <div class="nota"><x-icone nome="escudo" tamanho="18" /><span>Tudo o que é feito no sistema fica registrado na trilha de auditoria: quem fez, quando, de onde, e o valor antigo e o novo.</span></div>
                            @break

                        @case('perfis')
                            <div class="tbl-wrap">
                                <table class="tbl tbl-rel">
                                    <thead><tr><th scope="col">Perfil</th><th scope="col">O que faz</th></tr></thead>
                                    <tbody>
                                        <tr><td><b>Servidor</b></td><td>Consulta a própria ficha e baixa o PDF, envia documentos para o dossiê, pede férias e registra o ponto.</td></tr>
                                        <tr><td><b>Chefia</b></td><td>Vê a equipe das unidades que chefia, aprova ou recusa os pedidos de férias, acompanha a escala e a frequência do setor.</td></tr>
                                        <tr><td><b>Recursos humanos</b></td><td>Cadastra servidores, cargos e vagas, valida documentos, cuida dos dependentes, marca férias, registra afastamentos, ajusta jornadas e marcações de ponto, importa planilhas e o relógio de ponto e tira relatórios.</td></tr>
                                        <tr><td><b>Administrador do órgão</b></td><td>Tudo o que o RH faz, mais usuários e perfis, unidades e organograma, chaves de API e a trilha de auditoria.</td></tr>
                                        <tr><td><b>Auditor</b></td><td>Consulta cadastros, escala, frequência, relatórios e a trilha de auditoria, sem alterar nada e sem ver CPF completo nem documentos.</td></tr>
                                        <tr><td><b>Administrador geral</b></td><td>Cadastra os órgãos e os administradores de cada um. Cada órgão só vê os próprios dados.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            @break

                        @case('servidor')
                            <h3 id="servidor-ficha">Conferir a ficha</h3>
                            <p>Em <b>Minha ficha</b> aparecem os seus dados como estão no RH, nas abas Funcionais, Pessoais, Declarações, Dossiê, Dependentes e Férias. Se algo estiver errado, avise o RH: o servidor não altera a própria ficha. O botão <b>Baixar minha ficha em PDF</b> gera a ficha no modelo da SEGAD, pronta para assinar.</p>
                            {!! $figura('servidor-ficha', 'Minha ficha, aba Férias: saldo, períodos e pedidos.') !!}
                            <h3 id="servidor-documentos">Enviar documentos</h3>
                            <p>Na aba <b>Dossiê</b>, clique em <b>Enviar documento</b>, escolha o tipo e o arquivo (PDF, JPG ou PNG, até 10 MB). Ele fica "aguardando validação" até o RH conferir. Se o RH recusar, o motivo aparece ali, e você pode enviar de novo.</p>
                            <h3 id="servidor-ferias">Pedir férias</h3>
                            <ol class="passos">
                                <li>Na aba <b>Férias</b>, clique em <b>Pedir férias</b>.</li>
                                <li>Escolha o período aquisitivo e quantos dias. O sistema sugere as próximas datas livres, sem coincidir com outras férias ou licenças suas e sem deixar o setor abaixo do mínimo de pessoas. Clique numa sugestão ou escolha outra data.</li>
                                <li>Confira o último dia e a volta ao trabalho, e envie. O pedido vai para a chefia do setor (ou para o RH, se o setor não tiver chefia cadastrada).</li>
                                <li>Até ser aprovado, você pode <b>retirar o pedido</b>. A decisão, com o motivo se for recusado, aparece na mesma aba.</li>
                            </ol>
                            <div class="nota"><x-icone nome="calendario" tamanho="18" /><span>Regras do órgão: {{ $regra->dias_por_periodo }} dias por período, em até {{ $regra->max_etapas }} etapas de pelo menos {{ $regra->min_dias_etapa }} dias, pedido com {{ $regra->antecedencia_dias }} dias de antecedência, no máximo {{ $regra->max_periodos_acumulados }} períodos acumulados.</span></div>
                            @break

                        @case('ponto')
                            <ol class="passos">
                                <li>Em <b>Meu ponto</b> (no menu ou no painel), clique em <b>Registrar entrada</b> ao chegar e em <b>Registrar saída</b> ao sair. O botão alterna sozinho. Quem tem intervalo de almoço registra quatro vezes no dia.</li>
                                <li>A hora gravada é a do servidor do sistema, não a do seu computador ou celular. O registro só funciona de dentro da rede do órgão; de fora, a tela avisa e não registra.</li>
                                <li>Logo abaixo fica o <b>espelho do mês</b>: as marcações de cada dia, as horas feitas e a situação. Férias, licenças, feriados e pontos facultativos já aparecem preenchidos.</li>
                                <li><b>Justificar:</b> faltas, atrasos, saídas antecipadas e registros incompletos aparecem em <b>Ocorrências para justificar</b> no dia seguinte. Clique em <b>Justificar</b>, escolha o motivo (atestado, declaração de comparecimento, serviço externo, esqueci de registrar ou outro), conte o que aconteceu e, para atestado e declaração, anexe o documento. O prazo vai até o fim do mês seguinte.</li>
                                <li>A chefia do setor <b>abona</b> (o dia passa a contar com a jornada inteira) ou <b>recusa</b> com motivo, que aparece em <b>Minhas justificativas</b>. Enquanto está em análise, você pode retirar a justificativa.</li>
                                <li>Registrou errado? Avise o RH, que anula a marcação com motivo; ela continua no espelho, riscada.</li>
                            </ol>
                            <div class="nota"><x-icone nome="escudo" tamanho="18" /><span>Se puder, envie o atestado sem o CID (código da doença): a chefia vê o anexo para decidir. O motivo e o anexo não aparecem na trilha de auditoria, só que foram enviados.</span></div>
                            {!! $figura('meu-ponto', 'Meu ponto: relógio, botão de registro e o espelho do mês.') !!}
                            <div class="tbl-wrap">
                                <table class="tbl tbl-rel">
                                    <thead><tr><th scope="col">Situação no espelho</th><th scope="col">O que quer dizer</th></tr></thead>
                                    <tbody>
                                        <tr><td><span class="b b-ok">Normal</span></td><td>Marcações completas, dentro do horário e da tolerância.</td></tr>
                                        <tr><td><span class="b b-warn">Verificar</span></td><td>Entrada depois do horário, saída antes ou horas abaixo da jornada, além da tolerância. O motivo aparece embaixo.</td></tr>
                                        <tr><td><span class="b b-warn">Registro incompleto</span></td><td>Número ímpar de marcações: falta a saída ou a entrada de algum turno.</td></tr>
                                        <tr><td><span class="b b-err">Sem registro</span></td><td>Dia de trabalho sem nenhuma marcação.</td></tr>
                                        <tr><td><span class="b b-ok">Abonado</span></td><td>Ocorrência justificada e abonada: conta a jornada inteira.</td></tr>
                                        <tr><td><span class="b b-mute">Teletrabalho</span> <span class="b b-mute">Dispensado</span></td><td>Jornada sem registro de ponto, definida pelo RH.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            @break

                        @case('chefia')
                            <p>A chefia vê só a equipe das unidades que chefia (e das subordinadas). No menu aparecem <b>Servidores</b>, <b>Férias</b>, <b>Frequência</b> e <b>Relatórios</b>.</p>
                            <ol class="passos">
                                <li><b>Aprovar férias:</b> em <b>Férias › A aprovar</b> (o número no menu mostra quantos pedidos esperam), confira as datas e clique em <b>Aprovar</b>, ou em <b>Recusar</b> informando o motivo, que o servidor vai ver. Se as datas passaram a conflitar com outra ausência, o sistema avisa e não deixa aprovar.</li>
                                <li><b>Escala do setor:</b> em <b>Férias</b>, a grade do mês mostra quem está fora em cada dia e a linha "Presentes" fica vermelha se o setor ficar abaixo do mínimo. Troque para <b>Ano</b> para ver o mapa do ano.</li>
                                <li><b>Frequência do setor:</b> em <b>Frequência</b>, o resumo do mês de cada pessoa da equipe: dias com registro, sem registro, incompletos, a verificar, abonados e o saldo de horas. Clique no nome para ver o espelho dia a dia.</li>
                                <li><b>Justificativas:</b> em <b>Frequência › Justificativas</b> (o número no menu mostra quantas esperam), leia o motivo e o anexo e clique em <b>Abonar</b>, ou em <b>Recusar</b> informando o motivo, que o servidor vê. Ajustes de marcação são pedidos ao RH.</li>
                                <li>Seus próprios pedidos de férias vão para a chefia superior ou para o RH. Ninguém aprova as próprias férias.</li>
                            </ol>
                            {!! $figura('chefia-aprovar', 'Férias a aprovar, na visão da chefia.') !!}
                            {!! $figura('justificativas', 'Justificativas de ponto da equipe, para abonar ou recusar.') !!}
                            {!! $figura('escala', 'Escala do setor: quem está fora em cada dia e a linha de presentes.') !!}
                            @break

                        @case('rh-servidores')
                            <ol class="passos">
                                <li>Em <b>Servidores › Novo servidor</b>, preencha as 5 etapas da ficha. Cada etapa é conferida antes de seguir (CPF, campos obrigatórios, reservista, vaga livre).</li>
                                <li>Ao escolher a vaga do quadro, ela passa a ficar ocupada pelo servidor. Trocar de vaga ou registrar o desligamento libera a anterior.</li>
                                <li>Registre as declarações de parentesco, acumulação de cargos e bens. As que faltam aparecem como pendência na ficha e no painel.</li>
                                <li>Se o servidor tiver usuário no sistema com o mesmo CPF, ele passa a ver a própria ficha em <b>Minha ficha</b>.</li>
                            </ol>
                            @break

                        @case('rh-importar')
                            <p>Para trazer o que hoje está em planilhas, use os modelos do menu <b>Modelos de planilha</b>, nesta ordem: <b>01</b> unidades, <b>02</b> cargos e vagas, <b>03</b> servidores, <b>04</b> dependentes, <b>06</b> afastamentos e <b>05</b> férias.</p>
                            <ol class="passos">
                                <li>Em <b>Importar planilha</b>, escolha o tipo e envie o arquivo. Nada é gravado ainda.</li>
                                <li>Leia o relatório: as linhas com erro ficam de fora e dizem o motivo; os avisos entram, mas pedem conferência. O CPF aparece sempre mascarado.</li>
                                <li>Clique em <b>Confirmar importação</b>. Enviar a mesma planilha de novo atualiza o que já existe, sem duplicar.</li>
                            </ol>
                            <div class="nota"><x-icone nome="alerta" tamanho="18" /><span>Teste sempre com os exemplos fictícios primeiro. Dados reais só entram depois de validados segurança, permissões e cópia de segurança.</span></div>
                            @break

                        @case('rh-dossie')
                            <p>Em <b>Documentos a validar</b> ficam os arquivos enviados pelos servidores: abra, confira e <b>Valide</b> ou <b>Recuse</b> com o motivo. Na ficha, aba <b>Dossiê</b>, o RH também inclui documentos, que já entram validados, e acompanha quantos dos 10 documentos da posse estão completos. Nada é apagado: um documento é arquivado, com motivo.</p>
                            <p>Na aba <b>Dependentes</b>, cadastre cada dependente com o código de parentesco e as finalidades (imposto de renda, previdência, salário-família). Quem passa do limite de idade aparece nas pendências. Para tirar alguém, use <b>Encerrar</b>, com data e motivo.</p>
                            @break

                        @case('rh-ferias')
                            <ol class="passos">
                                <li><b>Marcar férias:</b> na ficha, aba Férias, <b>Marcar férias</b>. Marcadas pelo RH, já entram aprovadas. O sistema confere o saldo, as regras do órgão, outras ausências do servidor e o mínimo do setor, e sugere datas livres.</li>
                                <li><b>Cancelar ou interromper:</b> férias futuras podem ser canceladas; férias em gozo podem ser interrompidas (com o motivo da Lei 8.112, art. 80). Nos dois casos o saldo volta.</li>
                                <li><b>Licenças e afastamentos:</b> na ficha, <b>Registrar afastamento</b>, com tipo, datas e documento. O tipo só aparece para quem vê dados pessoais; a chefia e a escala mostram "Licença ou afastamento".</li>
                                <li><b>Mínimo do setor:</b> definido em cada unidade, em <b>Unidades e organograma</b>. Sem ele, o bloqueio pelo setor não se aplica.</li>
                                <li><b>Regras:</b> em <b>Férias › Regras de férias</b>. Valem para as próximas marcações; o que já foi marcado não muda.</li>
                            </ol>
                            {!! $figura('marcar-ferias', 'Marcar férias, com as datas livres sugeridas pelo sistema.') !!}
                            @break

                        @case('relatorios')
                            <p>O <b>Painel</b> mostra os números do seu perfil: servidores, quem está de férias hoje, pedidos a aprovar, documentos a validar, cargos vagos, gráficos por setor e por mês, e as pendências. Cada número leva à tela correspondente.</p>
                            {!! $figura('painel', 'Painel do RH.') !!}
                            <p>Em <b>Relatórios</b>, escolha o relatório, ajuste os filtros e baixe em <b>Excel</b> ou <b>PDF</b>: servidores, quadro de cargos e vagas, férias, saldo de férias, licenças e afastamentos, pendências e dependentes. Cada perfil vê só os relatórios e os servidores que pode ver. O CPF sai sempre mascarado, e cada exportação fica na trilha de auditoria.</p>
                            {!! $figura('relatorio', 'Relatório de saldo de férias, com filtros e exportação.') !!}
                            @break

                        @case('admin')
                            <ol class="passos">
                                <li><b>Usuários e perfis:</b> cadastre cada pessoa com um perfil. Desativar um usuário derruba a sessão dele na hora. Quem é servidor deve ter o CPF informado para ver a própria ficha.</li>
                                <li><b>Unidades e organograma:</b> defina a unidade superior, a chefia (que passa a aprovar férias da equipe) e o mínimo de pessoas presentes nas férias.</li>
                                <li><b>Trilha de auditoria:</b> filtre por pessoa, evento e período. Os registros não podem ser alterados nem apagados.</li>
                            </ol>
                            @break

                        @case('api')
                            <p>A API deixa outros sistemas (ponto eletrônico, portal da transparência, painéis) consultarem o NexRH. É só leitura, vê um órgão só e nunca devolve dados pessoais: o CPF vai mascarado e licenças aparecem sem o tipo.</p>
                            <ol class="passos">
                                <li>Em <b>Chaves de API</b>, crie uma chave por sistema, marcando só o que ele precisa consultar. Copie a chave na hora: ela não aparece de novo.</li>
                                <li>O sistema manda a chave no cabeçalho de cada chamada. São até 60 consultas por minuto por chave.</li>
                                <li>Revogue a chave quando o sistema deixar de usar ou se ela vazar. A tela mostra o último uso de cada uma.</li>
                            </ol>
                            <pre class="codigo"><code>curl -H "Authorization: Bearer nxr_SUA_CHAVE" {{ $base }}/servidores?unidade=4</code></pre>
                            <div class="tbl-wrap">
                                <table class="tbl tbl-rel">
                                    <thead><tr><th scope="col">Consulta</th><th scope="col">Escopo</th><th scope="col">O que devolve</th></tr></thead>
                                    <tbody>
                                        <tr><td><code>GET /api/v1</code></td><td>qualquer</td><td>Órgão, escopos da chave e as consultas liberadas.</td></tr>
                                        <tr><td><code>GET /api/v1/unidades</code></td><td>unidades</td><td>Sigla, nome, unidade superior, chefe, mínimo de presentes e servidores ativos.</td></tr>
                                        <tr><td><code>GET /api/v1/cargos</code></td><td>cargos</td><td>Cargos com símbolo, natureza e totais de vagas (ocupadas, livres, remanejadas).</td></tr>
                                        <tr><td><code>GET /api/v1/servidores</code></td><td>servidores</td><td>Nome, CPF mascarado, matrícula, cargo, setor, vínculo e ingresso. Filtros <code>unidade</code>, <code>situacao</code> (ativo ou desligado), <code>por_pagina</code> (até 100) e <code>pagina</code>.</td></tr>
                                        <tr><td><code>GET /api/v1/servidores/{id}</code></td><td>servidores</td><td>Um servidor.</td></tr>
                                        <tr><td><code>GET /api/v1/ferias</code></td><td>ferias</td><td>Etapas aprovadas, aguardando aprovação e interrompidas entre <code>de</code> e <code>ate</code> (AAAA-MM-DD; padrão: hoje e os 90 dias seguintes). Filtro <code>unidade</code>.</td></tr>
                                        <tr><td><code>GET /api/v1/ausencias</code></td><td>ferias</td><td>Quem está fora entre <code>de</code> e <code>ate</code>: férias ou "licença ou afastamento", sem o tipo.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="small muted">Respostas em JSON. Erros: 401 (chave ausente, inválida, revogada ou expirada), 403 (escopo não liberado), 404 (não encontrado), 422 (filtro inválido) e 429 (muitas consultas em um minuto).</p>
                            @break

                        @case('backup')
                            <p>A cópia de segurança leva o banco de dados e os arquivos do dossiê, com soma de verificação. No Windows, no PowerShell, dentro da pasta do sistema:</p>
                            <pre class="codigo"><code>.\scripts\backup.ps1                       # faz uma cópia agora, em .\backups
.\scripts\agendar-backup.ps1               # agenda uma cópia por dia
.\scripts\restaurar.ps1 .\backups\nexrh_AAAA-MM-DD_HHMMSS</code></pre>
                            <p>No Linux, os equivalentes são <code>./scripts/backup.sh</code> e <code>./scripts/restaurar.sh</code>. Guarde uma cópia fora do computador do sistema e guarde a <b>APP_KEY</b> do arquivo <code>.env</code> em lugar seguro e separado: sem ela, CPF, documentos e dossiê da cópia não podem ser lidos. O passo a passo completo está no guia da TI (<code>docs/guia-ti.md</code>).</p>
                            @break

                        @case('rh-frequencia')
                            <ol class="passos">
                                <li><b>Regras e feriados:</b> em <b>Frequência › Regras e feriados</b>, defina o horário de quem faz 6h e de quem faz 8h por dia, a tolerância e as redes do órgão de onde o ponto pode ser registrado. O botão <b>Incluir feriados nacionais e de RR</b> preenche o calendário do ano; confira e inclua os decretos de ponto facultativo.</li>
                                <li><b>Jornada:</b> cada servidor começa com o horário padrão pela carga da ficha. No espelho do servidor, <b>Alterar jornada</b> muda o horário, os dias da semana ou a modalidade (presencial, teletrabalho ou dispensado do ponto), a partir de uma data. A jornada anterior fica no histórico.</li>
                                <li><b>Incluir marcação:</b> no espelho, para um esquecimento justificado, com data, hora e motivo. Ela aparece com a marca <b>RH</b>.</li>
                                <li><b>Anular marcação:</b> clique na marcação errada e informe o motivo. Ela continua no espelho, riscada, e deixa de contar. Nenhuma marcação é apagada.</li>
                                <li><b>Abonar dias:</b> no espelho, para uma ocorrência com motivo aceito (convocação, por exemplo), mesmo antes do dia. O RH também decide as justificativas de quem não tem chefia e as dos próprios chefes, em <b>Frequência › Justificativas</b>.</li>
                                <li><b>Cargos dispensados:</b> em <b>Regras e feriados</b>, marque os cargos que não registram ponto (hoje, secretário, adjunto e coordenadores). Para exigir o ponto de alguém desses cargos, altere a jornada da pessoa.</li>
                                <li><b>Relógio de ponto:</b> em <b>Frequência › Importar relógio de ponto</b>, envie o arquivo AFD (.txt) exportado do relógio, nos leiautes da Portaria 671/2021 ou 1.510/2009. O sistema acha o servidor pelo CPF ou pelo PIS da ficha, mostra a prévia e só grava ao confirmar. Reenviar o mesmo arquivo não duplica.</li>
                            </ol>
                            {!! $figura('frequencia', 'Espelho do ponto de um servidor, na visão do RH.') !!}
                            <div class="nota"><x-icone nome="alerta" tamanho="18" /><span>O fechamento do mês e o boletim de frequência para a folha chegam na próxima entrega.</span></div>
                            @break

                        @case('duvidas')
                            <dl class="faq">
                                <dt>O sistema não deixa marcar as férias. Por quê?</dt>
                                <dd>A mensagem diz o motivo: saldo insuficiente, regra do órgão (número de etapas, mínimo de dias, antecedência), coincidência com outra ausência do servidor ou o setor ficaria abaixo do mínimo de presentes. Nesse último caso ela diz o dia e quem já está fora. Use uma das datas sugeridas.</dd>
                                <dt>Por que o CPF aparece com asteriscos?</dt>
                                <dd>Por proteção (LGPD). O CPF completo só aparece para o RH e a administração do órgão, na ficha. Relatórios, exportações e a API mostram sempre mascarado.</dd>
                                <dt>Apaguei algo por engano. Dá para desfazer?</dt>
                                <dd>O sistema não apaga: documentos são arquivados, dependentes encerrados, férias canceladas e afastamentos cancelados, sempre com motivo. A trilha de auditoria mostra o valor antigo, para refazer.</dd>
                                <dt>Esqueci a senha ou perdi o celular da verificação em duas etapas.</dt>
                                <dd>Use "Esqueci a senha" na tela de entrada. Para a verificação em duas etapas, use um dos códigos de recuperação guardados ao ativar, ou peça ao administrador do órgão.</dd>
                                <dt>O ponto diz que estou fora da rede do órgão.</dt>
                                <dd>O registro só vale de dentro da rede do órgão. A tela mostra o endereço de onde você está acessando: passe esse dado ao RH ou à TI, que conferem se a rede está cadastrada em Regras e feriados.</dd>
                                <dt>Esqueci de registrar a saída. E agora?</dt>
                                <dd>O dia fica como "Registro incompleto". Em <b>Meu ponto</b>, clique em <b>Justificar</b>, escolha "Esqueci de registrar" e diga o horário. Se a chefia abonar, o dia conta inteiro.</dd>
                                <dt>Os dados de demonstração são de pessoas reais?</dt>
                                <dd>Não. Nomes, CPFs e valores de demonstração e dos exemplos são inventados.</dd>
                            </dl>
                            @break
                    @endswitch
                </section>
            @endforeach
        </div>
    </div>
</x-layouts.app>
