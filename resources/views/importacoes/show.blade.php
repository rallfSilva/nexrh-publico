<x-layouts.app titulo="Relatório de importação">
    @php
        $servidores = $importacao->tipo === 'servidores';
        $dependentes = $importacao->tipo === 'dependentes';
        $ferias = $importacao->tipo === 'ferias';
        $afastamentos = $importacao->tipo === 'afastamentos';
        $ponto = $importacao->tipo === 'ponto';
        $porLinha = $servidores || $dependentes || $ferias || $afastamentos || $ponto;
        $calc = $importacao->resumo['calculado'] ?? [];
        $decl = $importacao->resumo['declarado'] ?? null;
        $grupos = [
            'bloqueia' => ['Impedem a importação', 'flash-erro', $ponto ? 'Corrija no relógio e exporte o arquivo de novo.' : 'Corrija na planilha e envie de novo.'],
            'erro' => $ponto
                ? ['Marcações que não serão importadas', 'flash-aviso', 'As demais seguem normalmente. Depois de corrigir o cadastro, envie o arquivo de novo: o que já foi importado não se repete.']
                : ($porLinha
                ? ['Linhas que não serão importadas', 'flash-aviso', 'As demais linhas seguem normalmente. Corrija estas na planilha e envie de novo depois: quem já foi importado é atualizado, não duplicado.']
                : ['Inconsistências da planilha', 'flash-aviso', 'Não impedem a importação: o sistema usa as linhas de cada vaga como verdade. Vale corrigir na origem.']),
            'aviso' => ['Avisos', 'flash-ok', 'Pontos para conferir.'],
        ];
        $rotulos = $ponto ? [
            'linhas' => 'Marcações no arquivo',
            'validas' => 'Prontas para importar',
            'ja_registradas' => 'Já estão no sistema (ignoradas)',
            'com_erro' => 'Com erro (ficam de fora)',
            'servidores' => 'Servidores',
        ] : ($ferias || $afastamentos ? [
            'linhas' => 'Linhas preenchidas na aba '.($ferias ? 'Férias' : 'Afastamentos'),
            'validas' => 'Prontas para importar',
            'com_erro' => 'Com erro (ficam de fora)',
            'novos' => $ferias ? 'Etapas novas' : 'Afastamentos novos',
            'atualizados' => 'Já cadastrados (serão atualizados)',
            'servidores' => 'Servidores na planilha',
        ] : ($dependentes ? [
            'linhas' => 'Linhas preenchidas na aba Dependentes',
            'validas' => 'Prontas para importar',
            'com_erro' => 'Com erro (ficam de fora)',
            'novos' => 'Dependentes novos',
            'atualizados' => 'Já cadastrados (serão atualizados)',
            'servidores' => 'Servidores com dependentes na planilha',
        ] : ($servidores ? [
            'linhas' => 'Linhas preenchidas na aba Servidores',
            'validas' => 'Prontas para importar',
            'com_erro' => 'Com erro (ficam de fora)',
            'novos' => 'Servidores novos',
            'atualizados' => 'Já cadastrados (serão atualizados)',
            'sem_cargo' => 'Cargo não encontrado no quadro',
            'declaracoes' => 'Declarações',
        ] : ['total' => 'Cargos na estrutura', 'remanejadas' => 'Remanejados para outros órgãos', 'disponiveis' => 'Disponíveis', 'ocupadas' => 'Ocupados', 'vagas' => 'Vagos'])));
        $confirmar = match ($importacao->tipo) {
            'ponto' => 'Gravar estas marcações no espelho de ponto dos servidores?',
            'ferias' => 'Gravar estas férias? A etapa que começa no mesmo dia para o mesmo servidor é atualizada.',
            'afastamentos' => 'Gravar estes afastamentos? O do mesmo tipo que começa no mesmo dia é atualizado.',
            'dependentes' => 'Gravar estes dependentes? Quem já está cadastrado (mesmo CPF para o mesmo servidor) é atualizado.',
            'servidores' => 'Gravar estes servidores? Quem já está cadastrado (mesmo CPF) é atualizado.',
            default => 'Aplicar esta planilha ao quadro de cargos? Os cargos e vagas da planilha substituem os atuais.',
        };
    @endphp

    <div class="row">
        <div>
            <a href="{{ route('importacoes.index') }}" class="small nao-imprimir" style="font-weight:600;display:inline-flex;gap:4px;align-items:center"><x-icone nome="voltar" tamanho="16" />Histórico de importações</a>
            <h1 class="h1" style="margin-top:8px">Relatório de divergências</h1>
            <p class="sub">{{ $importacao->arquivo_nome }} · enviada em {{ $importacao->created_at->format('d/m/Y H:i') }} por {{ $importacao->usuario?->name ?? '—' }} · {{ \App\Models\Importacao::FORMATOS[$importacao->formato] ?? 'Formato não reconhecido' }}</p>
        </div>
        <div class="btns nao-imprimir">
            <button type="button" class="btn btn-s" onclick="window.print()"><x-icone nome="download" />Imprimir ou salvar PDF</button>
            @if ($importacao->podeAplicar())
                <form method="POST" action="{{ route('importacoes.cancelar', $importacao) }}">@csrf
                    <button type="submit" class="btn btn-s">Cancelar</button>
                </form>
                <form method="POST" id="form-aplicar" action="{{ route('importacoes.aplicar', $importacao) }}" data-confirmar="{{ $confirmar }}" onsubmit="return confirm(this.dataset.confirmar)">@csrf
                    <button type="submit" class="btn btn-p"><x-icone nome="ok" />Confirmar importação</button>
                </form>
            @endif
        </div>
    </div>

    @php
        $classeSituacao = ['analisada' => 'flash-aviso', 'aplicada' => 'flash-ok', 'cancelada' => 'flash-aviso', 'recusada' => 'flash-erro'][$importacao->situacao] ?? 'flash-aviso';
    @endphp
    <div class="flash {{ $classeSituacao }}">
        <x-icone nome="{{ $importacao->situacao === 'aplicada' ? 'ok' : 'alerta' }}" style="flex:none" />
        <div>
            <b>{{ \App\Models\Importacao::SITUACOES[$importacao->situacao] }}</b>
            @if ($importacao->situacao === 'aplicada') em {{ $importacao->aplicada_em->format('d/m/Y H:i') }}.
            @elseif ($importacao->situacao === 'analisada') Confira a prévia abaixo e confirme para gravar {{ match ($importacao->tipo) { 'dependentes' => 'nos dependentes dos servidores', 'servidores' => 'no cadastro de servidores', 'ferias' => 'nas férias dos servidores', 'afastamentos' => 'nos afastamentos dos servidores', 'ponto' => 'no espelho de ponto dos servidores', default => 'no quadro de cargos' } }}.
            @endif
        </div>
    </div>

    @if ($importacao->tipo === 'cargos' && $importacao->podeAplicar() && auth()->user()->can('servidores.gerenciar'))
        <section class="card nao-imprimir" style="margin-bottom:16px">
            <label class="check"><input type="checkbox" name="criar_fichas" value="1" form="form-aplicar" checked> Criar também a ficha de cada ocupante que ainda não está em Servidores ({{ $calc['ocupadas'] ?? 0 }} vagas ocupadas na planilha)</label>
            <p class="muted small" style="margin:4px 0 0 28px">A ficha nasce com o nome, o CPF, a vaga e o ato de nomeação. Fica com a pendência "Ficha incompleta" até o RH preencher o resto. Quem já tem ficha (mesmo CPF) não muda. CPF inválido ou repetido não gera ficha.</p>
        </section>
    @endif

    <section class="card">
        <h2>Totais</h2>
        <p class="hint">{{ $porLinha ? 'Contagem feita pelo sistema linha a linha.' : 'Contagem feita pelo sistema a partir de cada vaga'.($decl ? ', comparada com o resumo escrito na planilha' : '').'.' }}</p>
        <div class="tbl-wrap" style="margin-top:12px">
            <table class="tbl">
                <thead><tr><th>Item</th><th class="num">Contado pelo sistema</th>@if ($decl)<th class="num">Escrito na planilha</th><th>Confere?</th>@endif</tr></thead>
                <tbody>
                    @foreach ($rotulos as $campo => $rotulo)
                        <tr>
                            <td>{{ $rotulo }}</td>
                            <td class="num"><b>{{ $calc[$campo] ?? 0 }}</b></td>
                            @if ($decl)
                                <td class="num">{{ $decl[$campo] ?? '—' }}</td>
                                <td>
                                    @if (! isset($decl[$campo]))<span class="muted">—</span>
                                    @elseif ($decl[$campo] === ($calc[$campo] ?? null))<span class="b b-ok">Confere</span>
                                    @else<span class="b b-err">Diferença de {{ abs($decl[$campo] - ($calc[$campo] ?? 0)) }}</span>@endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @foreach ($grupos as $nivel => [$titulo, $classe, $explicacao])
        @php
            $itens = $importacao->divergenciasDoNivel($nivel);
        @endphp
        @if ($itens !== [])
            <section class="card">
                <h2>{{ $titulo }} ({{ count($itens) }})</h2>
                <p class="hint">{{ $explicacao }}</p>
                <div class="list">
                    @foreach ($itens as $d)
                        <div class="li">
                            <span class="chip {{ $nivel === 'aviso' ? 'c-navy' : 'c-warn' }}"><x-icone nome="alerta" /></span>
                            <div>@if ($d['cargo'])<b>{{ $d['cargo'] }}</b><br>@endif{{ $d['mensagem'] }}</div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach

    @if ($importacao->divergencias === [])
        <div class="flash flash-ok"><x-icone nome="ok" style="flex:none" /><div>Nenhuma divergência encontrada. {{ $ponto ? 'O arquivo está consistente.' : 'A planilha está consistente.' }}</div></div>
    @endif

    @if ($previa !== [] && $ponto)
        @php
            $porServidor = collect($previa)->groupBy('servidor_id')->map(fn ($m) => [
                'nome' => $m[0]['servidor_nome'], 'sigla' => $m[0]['unidade_sigla'], 'n' => $m->count(),
                'de' => $m->min('momento'), 'ate' => $m->max('momento'),
            ])->sortBy('nome');
        @endphp
        <section class="card card-flush">
            <div style="padding:22px 24px 8px"><h2>Prévia: {{ count($previa) }} marcações de {{ $porServidor->count() }} servidores</h2><p class="hint">De {{ \Illuminate\Support\Carbon::parse($calc['de'])->format('d/m/Y') }} a {{ \Illuminate\Support\Carbon::parse($calc['ate'])->format('d/m/Y') }}.</p></div>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Servidor</th><th>Setor</th><th class="num">Marcações</th><th>Primeira</th><th>Última</th></tr></thead>
                    <tbody>
                        @foreach ($porServidor as $p)
                            <tr>
                                <td><b>{{ $p['nome'] }}</b></td>
                                <td class="sigla">{{ $p['sigla'] ?? '—' }}</td>
                                <td class="num">{{ $p['n'] }}</td>
                                <td style="white-space:nowrap">{{ \Illuminate\Support\Carbon::parse($p['de'])->format('d/m/Y H:i') }}</td>
                                <td style="white-space:nowrap">{{ \Illuminate\Support\Carbon::parse($p['ate'])->format('d/m/Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @elseif ($previa !== [] && ($ferias || $afastamentos))
        <section class="card card-flush">
            <div style="padding:22px 24px 8px"><h2>Prévia: {{ count($previa) }} {{ $ferias ? 'etapas de férias' : 'afastamentos' }}</h2></div>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th class="num">Linha</th><th>Servidor</th><th>Setor</th>@if ($ferias)<th>Período aquisitivo</th>@else<th>Tipo</th>@endif<th>Datas</th><th class="num">Dias</th>@if ($ferias)<th>Situação</th>@endif<th>Ação</th></tr></thead>
                    <tbody>
                        @foreach ($previa as $p)
                            @php
                                $de = \Illuminate\Support\Carbon::parse($p['dados']['inicio']);
                                $ate = \Illuminate\Support\Carbon::parse($p['dados']['fim']);
                                $selo = $ferias ? (new \App\Models\Ferias(['situacao' => $p['dados']['situacao'], 'inicio' => $de, 'fim' => $ate]))->selo() : null;
                            @endphp
                            <tr>
                                <td class="num muted">{{ $p['linha'] }}</td>
                                <td><b>{{ $p['servidor_nome'] }}</b></td>
                                <td class="sigla">{{ $p['unidade_sigla'] ?? '—' }}</td>
                                @if ($ferias)
                                    <td class="small">{{ \Illuminate\Support\Carbon::parse($p['periodo_inicio'])->format('Y') }}/{{ \Illuminate\Support\Carbon::parse($p['periodo_inicio'])->addYear()->format('Y') }}</td>
                                @else
                                    <td class="small">{{ $p['dados']['tipo'] }}</td>
                                @endif
                                <td style="white-space:nowrap">{{ $de->format('d/m/Y') }} a {{ $ate->format('d/m/Y') }}</td>
                                <td class="num">{{ $ferias ? $p['dados']['dias'] : $de->diffInDays($ate) + 1 }}</td>
                                @if ($ferias)<td><span class="b {{ $selo[1] }}">{{ $selo[0] }}</span></td>@endif
                                <td><span class="b {{ $p['existente_id'] ? 'b-info' : 'b-ok' }}">{{ $p['existente_id'] ? 'Atualiza' : ($ferias ? 'Nova' : 'Novo') }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @elseif ($previa !== [] && $dependentes)
        <section class="card card-flush">
            <div style="padding:22px 24px 8px"><h2>Prévia: {{ count($previa) }} dependentes</h2><p class="hint">CPF mascarado.</p></div>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th class="num">Linha</th><th>Servidor titular</th><th>Dependente</th><th>Parentesco</th><th>Finalidades</th><th>Ação</th></tr></thead>
                    <tbody>
                        @foreach ($previa as $p)
                            @php
                                $dep = new \App\Models\Dependente($p['dados']);
                            @endphp
                            <tr>
                                <td class="num muted">{{ $p['linha'] }}</td>
                                <td>{{ $p['servidor_nome'] }}</td>
                                <td><b>{{ $dep->nome }}</b><div class="muted small">CPF {{ $dep->cpfMascarado() }} · {{ $dep->idade() }} anos</div></td>
                                <td><span class="sigla">{{ $dep->parentesco }}</span> {{ $dep->parentescoCurto() }}</td>
                                <td class="small">{{ implode(', ', $dep->finalidades()) ?: '—' }}</td>
                                <td><span class="b {{ $p['existente_id'] ? 'b-info' : 'b-ok' }}">{{ $p['existente_id'] ? 'Atualiza' : 'Novo' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @elseif ($previa !== [] && $servidores)
        <section class="card card-flush">
            <div style="padding:22px 24px 8px"><h2>Prévia: {{ count($previa) }} servidores</h2><p class="hint">CPF mascarado. A vaga é escolhida ao confirmar: a que já mostra o CPF no quadro ou a primeira livre do cargo.</p></div>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th class="num">Linha</th><th>Servidor</th><th>Cargo</th><th>Lotação</th><th>Ação</th><th class="num">Declarações</th></tr></thead>
                    <tbody>
                        @foreach ($previa as $p)
                            <tr>
                                <td class="num muted">{{ $p['linha'] }}</td>
                                <td><b>{{ $p['dados']['nome'] }}</b><div class="muted small">CPF {{ \App\Support\Cpf::mascarar($p['dados']['cpf']) }}</div></td>
                                <td>@if ($p['cargo_rotulo']){{ $p['cargo_rotulo'] }}@else<span class="b b-warn">Sem cargo no quadro</span>@endif</td>
                                <td class="sigla">{{ $p['unidade_sigla'] }}</td>
                                <td><span class="b {{ $p['existente_id'] ? 'b-info' : 'b-ok' }}">{{ $p['existente_id'] ? 'Atualiza' : 'Novo' }}</span></td>
                                <td class="num">{{ count($p['declaracoes']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @elseif ($previa !== [])
        <section class="card card-flush">
            <div style="padding:22px 24px 8px"><h2>Prévia: {{ count($previa) }} cargos</h2><p class="hint">É assim que o quadro vai ficar depois de confirmar.</p></div>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Cargo</th><th>Símbolo</th><th>Natureza</th><th class="num">Remuneração</th><th class="num">Total</th><th class="num">Remanej.</th><th class="num">Ocupados</th><th class="num">Vagos</th></tr></thead>
                    <tbody>
                        @foreach ($previa as $c)
                            @php
                                $s = array_count_values(array_column($c['vagas'], 'situacao'));
                            @endphp
                            <tr>
                                <td><b>{{ $c['denominacao'] }}</b></td>
                                <td class="sigla">{{ $c['simbolo'] }}</td>
                                <td class="muted small">{{ \App\Models\Cargo::NATUREZAS[$c['natureza']] ?? $c['natureza'] }}</td>
                                <td class="num">{{ $c['remuneracao'] !== null ? 'R$ '.number_format($c['remuneracao'], 2, ',', '.') : '—' }}</td>
                                <td class="num">{{ $c['quantidade_vagas'] }}</td>
                                <td class="num">{{ ($s['remanejada'] ?? 0) + ($s['devolvida'] ?? 0) }}</td>
                                <td class="num">{{ $s['ocupada'] ?? 0 }}</td>
                                <td class="num">{{ $s['vaga'] ?? 0 }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</x-layouts.app>
