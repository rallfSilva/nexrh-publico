<x-layouts.app titulo="Painel">
    <div class="row">
        <div>
            <h1 class="h1">Olá, {{ \Illuminate\Support\Str::of(auth()->user()->name)->before(' ') }}</h1>
            <p class="sub">{{ now()->locale('pt_BR')->translatedFormat('l, d \d\e F \d\e Y') }} · visão geral do {{ auth()->user()->orgao?->sigla ?? 'sistema' }}</p>
        </div>
        <div class="btns">
            @can('unidades.gerenciar')
                <a href="{{ route('unidades.create') }}" class="btn btn-s"><x-icone nome="mais" />Nova unidade</a>
            @endcan
            @can('usuarios.gerenciar')
                <a href="{{ route('usuarios.create') }}" class="btn btn-p"><x-icone nome="mais" />Novo usuário</a>
            @endcan
        </div>
    </div>

    <div class="kpis">
        @if ($totais['orgaos'] !== null)
            <div class="card kpi">
                <span class="lbl"><span class="chip c-navy"><x-icone nome="predio" /></span>Órgãos</span>
                <span class="val">{{ $totais['orgaos'] }}</span>
                <span class="foot">usando o sistema</span>
            </div>
        @endif
        <div class="card kpi">
            <span class="lbl"><span class="chip c-navy"><x-icone nome="organograma" /></span>Unidades ativas</span>
            <span class="val">{{ $totais['unidades'] }}</span>
            <span class="foot">no organograma</span>
        </div>
        <div class="card kpi">
            <span class="lbl"><span class="chip c-teal"><x-icone nome="usuarios" /></span>Usuários ativos</span>
            <span class="val">{{ $totais['usuarios'] }}</span>
            <span class="foot">{{ $totais['acessosHoje'] }} {{ $totais['acessosHoje'] === 1 ? 'acessou' : 'acessaram' }} hoje</span>
        </div>
        <div class="card kpi">
            <span class="lbl"><span class="chip {{ $totais['semDoisFatores'] ? 'c-warn' : 'c-teal' }}"><x-icone nome="escudo" /></span>Sem verificação em 2 etapas</span>
            <span class="val">{{ $totais['semDoisFatores'] }}</span>
            <span class="foot">{{ $totais['semDoisFatores'] ? 'usuários ainda podem ativar' : 'todos protegidos' }}</span>
        </div>
    </div>

    <div class="g2">
        <section class="card">
            <h2>Pendências da estrutura</h2>
            <p class="hint">O que falta para o organograma ficar completo.</p>
            <div class="list">
                @forelse ($unidadesSemChefe as $unidade)
                    <div class="li">
                        <span class="chip c-warn"><x-icone nome="alerta" /></span>
                        <div><b class="sigla">{{ $unidade->sigla }}</b> sem chefe definido<div class="meta">{{ $unidade->nome }}</div></div>
                        @can('unidades.gerenciar')
                            <a class="r btn btn-s btn-sm" href="{{ route('unidades.edit', $unidade) }}">Definir</a>
                        @endcan
                    </div>
                @empty
                    <div class="li"><span class="chip c-teal"><x-icone nome="ok" /></span><div>Todas as unidades ativas têm chefe definido.</div></div>
                @endforelse
            </div>
        </section>

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
        @else
            <section class="card">
                <h2>Próximas entregas</h2>
                <p class="hint">O que chega ao sistema nas próximas etapas da Fase 1.</p>
                <div class="list">
                    <div class="li"><span class="chip c-navy"><x-icone nome="cargos" /></span><div>Cargos e vagas<div class="meta">Quadro com importação da planilha atual</div></div></div>
                    <div class="li"><span class="chip c-navy"><x-icone nome="servidor" /></span><div>Servidores<div class="meta">Ficha cadastral completa</div></div></div>
                    <div class="li"><span class="chip c-navy"><x-icone nome="ferias" /></span><div>Férias<div class="meta">Planejamento sem conflito entre colegas</div></div></div>
                </div>
            </section>
        @endcan
    </div>
</x-layouts.app>
