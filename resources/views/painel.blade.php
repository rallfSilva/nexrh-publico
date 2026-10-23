@php
    $user = auth()->user();
    $maxUnidade = max(1, $porUnidade->max('total') ?? 1);
    $maxMes = max(1, collect($feriasPorMes)->max(fn ($m) => $m['aprovadas'] + $m['pedidas']) ?? 1);
    $totalMeses = collect($feriasPorMes)->sum(fn ($m) => $m['aprovadas'] + $m['pedidas']);
@endphp
<x-layouts.app titulo="Painel">
    <div class="row">
        <div>
            <h1 class="h1">Olá, {{ \Illuminate\Support\Str::of($user->name)->before(' ') }}</h1>
            <p class="sub">{{ now()->locale('pt_BR')->translatedFormat('l, d \d\e F \d\e Y') }} · {{ $chefia ? 'sua equipe' : 'visão geral do '.($user->orgao?->sigla ?? 'sistema') }}</p>
        </div>
        <div class="btns">
            @can('ver-relatorios')
                <a href="{{ route('relatorios.index') }}" class="btn btn-s"><x-icone nome="relatorio" />Relatórios</a>
            @endcan
            @can('servidores.gerenciar')
                <a href="{{ route('servidores.create') }}" class="btn btn-p"><x-icone nome="mais" />Novo servidor</a>
            @elsecan('usuarios.gerenciar')
                <a href="{{ route('usuarios.create') }}" class="btn btn-p"><x-icone nome="mais" />Novo usuário</a>
            @endcan
        </div>
    </div>

    @if ($minha)
        <section class="card minha">
            <span class="avatar" aria-hidden="true">{{ $minha['servidor']->iniciais() }}</span>
            <div class="fx">
                <span class="lbl">Saldo de férias</span>
                <b>{{ $minha['saldo'] }} {{ $minha['saldo'] === 1 ? 'dia' : 'dias' }}</b>
            </div>
            <div class="fx">
                <span class="lbl">Próximas férias</span>
                @if ($minha['proximas'])
                    <b>{{ $minha['proximas']->periodoTexto() }}</b>
                    <small>{{ $minha['proximas']->selo()[0] }}</small>
                @else
                    <b class="muted" style="font-weight:600">Nenhuma marcada</b>
                @endif
            </div>
            <div class="fx">
                <span class="lbl">Na minha ficha</span>
                @php
                    $nPend = count($minha['pendencias']);
                @endphp
                <b>{{ $nPend ? $nPend.' '.($nPend === 1 ? 'pendência' : 'pendências') : 'Tudo em dia' }}</b>
            </div>
            <div class="fx">
                <span class="lbl">Meu ponto</span>
                <b>{{ $minha['ocorrencias'] ? $minha['ocorrencias'].' '.($minha['ocorrencias'] === 1 ? 'ocorrência' : 'ocorrências').' para justificar' : 'Nada para justificar' }}</b>
            </div>
            <div class="btns">
                @if ($minha['saldo'] > 0)
                    <a href="{{ route('minha-ficha.ferias.create') }}" class="btn btn-s"><x-icone nome="sol" />Pedir férias</a>
                @endif
                <a href="{{ route('meu-ponto') }}" class="btn btn-s"><x-icone nome="relogio" />Meu ponto</a>
                <a href="{{ route('minha-ficha') }}" class="btn btn-p"><x-icone nome="pasta" />Minha ficha</a>
            </div>
        </section>
    @endif

    <div class="kpis">
        @if ($totais['orgaos'] !== null)
            <div class="card kpi">
                <span class="lbl"><span class="chip c-navy"><x-icone nome="predio" /></span>Órgãos</span>
                <span class="val">{{ $totais['orgaos'] }}</span>
                <span class="foot">usando o sistema</span>
            </div>
        @endif
        @if ($servidores)
            <a href="{{ route('servidores.index') }}" class="card kpi kpi-link">
                <span class="lbl"><span class="chip c-navy"><x-icone nome="servidor" /></span>{{ $chefia ? 'Minha equipe' : 'Servidores ativos' }}</span>
                <span class="val">{{ $servidores['ativos'] }}</span>
                <span class="foot">{{ $servidores['efetivos'] }} {{ $servidores['efetivos'] === 1 ? 'efetivo' : 'efetivos' }}, {{ $servidores['ativos'] - $servidores['efetivos'] }} só em comissão</span>
            </a>
        @endif
        @if ($hoje)
            <a href="{{ route('ferias.escala') }}" class="card kpi kpi-link">
                <span class="lbl"><span class="chip c-teal"><x-icone nome="sol" /></span>De férias hoje</span>
                <span class="val">{{ $hoje['ferias'] }}</span>
                <span class="foot">{{ $hoje['afastados'] }} {{ $hoje['afastados'] === 1 ? 'pessoa' : 'pessoas' }} de licença ou afastamento</span>
            </a>
        @endif
        @if ($aAprovar !== null)
            <a href="{{ route('ferias.pendentes') }}" class="card kpi kpi-link">
                <span class="lbl"><span class="chip {{ $aAprovar ? 'c-warn' : 'c-teal' }}"><x-icone nome="ok" /></span>Férias a aprovar</span>
                <span class="val">{{ $aAprovar }}</span>
                <span class="foot">{{ $aAprovar ? 'pedidos esperando decisão' : 'nenhum pedido esperando' }}</span>
            </a>
        @endif
        @if ($justificativasAAnalisar !== null)
            <a href="{{ route('justificativas.index') }}" class="card kpi kpi-link">
                <span class="lbl"><span class="chip {{ $justificativasAAnalisar ? 'c-warn' : 'c-teal' }}"><x-icone nome="frequencia" /></span>Justificativas de ponto</span>
                <span class="val">{{ $justificativasAAnalisar }}</span>
                <span class="foot">{{ $justificativasAAnalisar ? 'esperando abono ou recusa' : 'nenhuma esperando' }}</span>
            </a>
        @endif
        @if ($documentosAValidar !== null)
            <a href="{{ route('documentos.pendentes') }}" class="card kpi kpi-link">
                <span class="lbl"><span class="chip {{ $documentosAValidar ? 'c-warn' : 'c-teal' }}"><x-icone nome="pasta" /></span>Documentos a validar</span>
                <span class="val">{{ $documentosAValidar }}</span>
                <span class="foot">enviados pelos servidores</span>
            </a>
        @endif
        @if ($cargos)
            <a href="{{ route('cargos.index', ['com_vagas' => 1]) }}" class="card kpi kpi-link">
                <span class="lbl"><span class="chip c-teal"><x-icone nome="cargos" /></span>Cargos vagos</span>
                <span class="val">{{ $cargos['vagas'] }}</span>
                <span class="foot">{{ $cargos['ocupadas'] }} ocupados no quadro</span>
            </a>
        @endif
        @if ($totais['usuarios'] !== null)
            <a href="{{ route('usuarios.index') }}" class="card kpi kpi-link">
                <span class="lbl"><span class="chip {{ $totais['semDoisFatores'] ? 'c-warn' : 'c-teal' }}"><x-icone nome="escudo" /></span>Contas sem 2 etapas</span>
                <span class="val">{{ $totais['semDoisFatores'] }}</span>
                <span class="foot">de {{ $totais['usuarios'] }} usuários ativos · {{ $totais['acessosHoje'] }} {{ $totais['acessosHoje'] === 1 ? 'acessou' : 'acessaram' }} hoje</span>
            </a>
        @endif
    </div>

    @if ($minha && ! $servidores)
        <div class="g2">
            <section class="card">
                <h2>O que falta na sua ficha</h2>
                <p class="hint">Resolva com o RH ou envie o que for pedido em Minha ficha.</p>
                <div class="list">
                    @forelse ($minha['pendencias'] as $p)
                        <a class="li li-link" href="{{ route('minha-ficha') }}">
                            <span class="chip c-warn"><x-icone nome="alerta" /></span>
                            <div><b>{{ $p['titulo'] }}</b><div class="meta">{{ $p['detalhe'] }}</div></div>
                        </a>
                    @empty
                        <div class="li"><span class="chip c-teal"><x-icone nome="ok" /></span><div>Sua ficha está em dia.</div></div>
                    @endforelse
                </div>
            </section>
            <section class="card">
                <h2>Como usar o sistema</h2>
                <p class="hint">Passo a passo do que o servidor faz no NexRH.</p>
                <div class="list">
                    <a class="li li-link" href="{{ $manual }}#servidor-ficha"><span class="chip c-navy"><x-icone nome="pasta" /></span><div><b>Conferir a ficha e baixar o PDF</b><div class="meta">Seus dados como estão no RH.</div></div></a>
                    <a class="li li-link" href="{{ $manual }}#servidor-documentos"><span class="chip c-navy"><x-icone nome="enviar" /></span><div><b>Enviar documentos</b><div class="meta">PDF, JPG ou PNG de até 10 MB. O RH valida.</div></div></a>
                    <a class="li li-link" href="{{ $manual }}#servidor-ferias"><span class="chip c-navy"><x-icone nome="sol" /></span><div><b>Pedir férias</b><div class="meta">Com as datas livres que o sistema sugere.</div></div></a>
                </div>
            </section>
        </div>
    @endif

    @if ($porUnidade->count() > 1 || $feriasPorMes)
        <div class="g2">
            @if ($porUnidade->count() > 1)
                <section class="card">
                    <h2>Servidores por unidade</h2>
                    <p class="hint">Ativos lotados em cada setor. A marca mostra o mínimo de presentes nas férias.</p>
                    <div class="ub-lista" role="table" aria-label="Servidores ativos por unidade">
                        @foreach ($porUnidade as $linha)
                            @php
                                $minimo = (int) $linha['unidade']->minimo_presentes_ferias;
                            @endphp
                            <a class="ub" role="row" href="{{ route('servidores.index', ['unidade' => $linha['unidade']->id]) }}" title="{{ $linha['unidade']->nome }}: {{ $linha['total'] }} servidores{{ $minimo ? ', mínimo de '.$minimo.' presentes' : '' }}">
                                <span class="rot" role="rowheader">{{ $linha['unidade']->sigla }}</span>
                                <span class="trilho" role="cell">
                                    <span class="enche" style="width: {{ round($linha['total'] / $maxUnidade * 100, 1) }}%"></span>
                                    @if ($minimo)
                                        <span class="minimo" style="left: {{ round(min($minimo, $maxUnidade) / $maxUnidade * 100, 1) }}%" aria-hidden="true"></span>
                                    @endif
                                </span>
                                <span class="num" role="cell">{{ $linha['total'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($feriasPorMes)
                <section class="card">
                    <div class="row">
                        <div>
                            <h2>Férias nos próximos 12 meses</h2>
                            <p class="hint">Dias de férias marcados em cada mês{{ $chefia ? ' na sua equipe' : '' }}.</p>
                        </div>
                        <a href="{{ route('ferias.escala', ['visao' => 'ano']) }}" class="btn btn-s btn-sm">Ver a escala</a>
                    </div>
                    @if ($totalMeses === 0)
                        <div class="vazio"><b>Nenhuma férias marcada.</b>Os dias aparecem aqui assim que forem marcados ou pedidos.</div>
                    @else
                        <div class="legenda legenda-solta">
                            <span><i class="lg ferias"></i>Aprovadas</span>
                            <span><i class="lg pedido"></i>Aguardando aprovação</span>
                        </div>
                        <div class="colunas" role="table" aria-label="Dias de férias por mês">
                            @foreach ($feriasPorMes as $m)
                                @php
                                    $soma = $m['aprovadas'] + $m['pedidas'];
                                    $rotulo = ucfirst($m['mes']->translatedFormat('F \d\e Y')).': '.$m['aprovadas'].' dias aprovados'.($m['pedidas'] ? ', '.$m['pedidas'].' aguardando aprovação' : '');
                                @endphp
                                <a class="coluna" role="row" href="{{ route('ferias.escala', ['visao' => 'mes', 'mes' => $m['mes']->format('Y-m')]) }}" title="{{ $rotulo }}" aria-label="{{ $rotulo }}">
                                    <span class="pilha" role="cell">
                                        @if ($soma)<span class="v">{{ $soma }}</span>@endif
                                        @if ($m['pedidas'])<span class="pt pedido" style="height: {{ round($m['pedidas'] / $maxMes * 100, 1) }}%"></span>@endif
                                        @if ($m['aprovadas'])<span class="pt ferias" style="height: {{ round($m['aprovadas'] / $maxMes * 100, 1) }}%"></span>@endif
                                    </span>
                                    <span class="mes" role="rowheader">{{ mb_substr($m['mes']->translatedFormat('M'), 0, 3) }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif
        </div>
    @endif

    <div class="g2">
        @if ($proximas->isNotEmpty() || $hoje)
            <section class="card">
                <h2>Férias agora e nos próximos 30 dias</h2>
                <p class="hint">Quem está de férias e quem sai em breve.</p>
                <div class="list">
                    @forelse ($proximas as $f)
                        <div class="li">
                            <span class="chip {{ $f->situacao === 'programada' ? 'c-warn' : 'c-navy' }}"><x-icone nome="sol" tamanho="16" /></span>
                            <div style="min-width:0">
                                <b>{{ $f->servidor->nome }}</b>
                                <div class="meta">{{ $f->servidor->unidade?->sigla }} · {{ $f->periodoTexto() }} · {{ $f->dias }} dias</div>
                            </div>
                            <span class="r"><span class="b {{ $f->selo()[1] }}">{{ $f->selo()[0] }}</span></span>
                        </div>
                    @empty
                        <div class="li muted">Ninguém de férias agora nem nos próximos 30 dias.</div>
                    @endforelse
                </div>
            </section>
        @endif

        @if ($pendencias || $unidadesSemChefe->isNotEmpty())
            <section class="card">
                <h2>Pendências</h2>
                <p class="hint">O que pede ação para os cadastros ficarem em dia.</p>
                <div class="list">
                    @foreach ($pendencias as $p)
                        <a class="li li-link" href="{{ $p['link'] }}">
                            <span class="chip c-warn"><x-icone nome="alerta" /></span>
                            <div><b>{{ $p['total'] }}</b> {{ $p['total'] === 1 ? str_replace(['unidades', 'servidores'], ['unidade', 'servidor'], $p['titulo']) : $p['titulo'] }}<div class="meta">{{ $p['detalhe'] }}</div></div>
                        </a>
                    @endforeach
                    @foreach ($unidadesSemChefe as $unidade)
                        <div class="li">
                            <span class="chip c-warn"><x-icone nome="alerta" /></span>
                            <div><b class="sigla">{{ $unidade->sigla }}</b> sem chefe definido<div class="meta">{{ $unidade->nome }}</div></div>
                            @can('unidades.gerenciar')
                                <a class="r btn btn-s btn-sm" href="{{ route('unidades.edit', $unidade) }}">Definir</a>
                            @endcan
                        </div>
                    @endforeach
                </div>
            </section>
        @elseif ($user->can('unidades.ver'))
            <section class="card">
                <h2>Pendências</h2>
                <div class="list"><div class="li"><span class="chip c-teal"><x-icone nome="ok" /></span><div>Nada pendente: todas as unidades têm chefe e os cadastros estão em dia.</div></div></div>
            </section>
        @endif

        @can('auditoria.ver')
            <section class="card">
                <div class="row">
                    <div><h2>Atividade recente</h2><p class="hint">Últimos registros da trilha de auditoria.</p></div>
                    <a href="{{ route('auditoria.index') }}" class="btn btn-s btn-sm">Ver tudo</a>
                </div>
                <div class="list">
                    @forelse ($ultimosEventos as $registro)
                        <div class="li">
                            <span class="avatar" style="width:34px;height:34px;font-size:12px" aria-hidden="true">{{ $registro->usuario?->iniciais() ?? 'S' }}</span>
                            <div style="min-width:0">
                                <div>{{ $registro->descricao }}</div>
                                <div class="meta">{{ $registro->usuario?->name ?? 'Sistema' }} · {{ $registro->created_at->diffForHumans() }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="li muted">Nenhum registro ainda.</div>
                    @endforelse
                </div>
            </section>
        @endcan
    </div>
</x-layouts.app>
