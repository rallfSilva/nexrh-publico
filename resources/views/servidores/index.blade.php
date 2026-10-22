<x-layouts.app titulo="Servidores">
    <div class="row">
        <div>
            <h1 class="h1">Servidores</h1>
            <p class="sub">
                @can('servidores.ver')
                    Cadastro funcional, lotação e atos de nomeação em um só lugar.
                @else
                    Servidores das unidades que você chefia.
                @endcan
            </p>
        </div>
        @can('servidores.gerenciar')
            <div class="btns">
                <a href="{{ route('importacoes.create', ['tipo' => 'servidores']) }}" class="btn btn-s"><x-icone nome="planilha" />Importar planilha</a>
                <a href="{{ route('servidores.create') }}" class="btn btn-p"><x-icone nome="mais" />Cadastrar servidor</a>
            </div>
        @endcan
    </div>

    <div class="kpis">
        <div class="card kpi">
            <span class="lbl"><span class="chip c-navy"><x-icone nome="servidor" /></span>Servidores ativos</span>
            <span class="val">{{ $kpis['ativos'] }}</span>
            <span class="foot">{{ $kpis['efetivos'] }} do quadro efetivo</span>
        </div>
        <div class="card kpi">
            <span class="lbl"><span class="chip c-warn"><x-icone nome="pasta" /></span>Declaração de bens</span>
            <span class="val">{{ $kpis['bens_pendentes'] }}</span>
            <span class="foot">sem a declaração de {{ $ano }}</span>
        </div>
        <div class="card kpi">
            <span class="lbl"><span class="chip {{ $kpis['sem_vaga'] > 0 ? 'c-warn' : 'c-teal' }}"><x-icone nome="cargos" /></span>Sem vaga vinculada</span>
            <span class="val">{{ $kpis['sem_vaga'] }}</span>
            <span class="foot">ativos sem vaga no quadro</span>
        </div>
        <div class="card kpi">
            <span class="lbl"><span class="chip c-navy"><x-icone nome="sair" /></span>Desligados</span>
            <span class="val">{{ $kpis['desligados'] }}</span>
            <span class="foot">histórico preservado</span>
        </div>
    </div>

    <form method="GET" class="card filtros" role="search">
        <input type="hidden" name="aba" value="{{ $aba }}">
        <div class="campo" style="flex:2 1 280px">
            <label for="f-busca">Nome, matrícula, decreto ou CPF</label>
            <input id="f-busca" name="busca" value="{{ request('busca') }}" class="input" placeholder="Ex.: Maria, 0152310 ou 000.000.000-00" autocomplete="off">
        </div>
        <div class="campo">
            <label for="f-unidade">Lotação</label>
            <select id="f-unidade" name="unidade" class="input">
                <option value="">Todas</option>
                @foreach ($unidades as $u)
                    <option value="{{ $u->id }}" @selected((int) request('unidade') === $u->id)>{{ $u->sigla }}</option>
                @endforeach
            </select>
        </div>
        <div class="campo">
            <label for="f-cargo">Cargo</label>
            <select id="f-cargo" name="cargo" class="input">
                <option value="">Todos</option>
                @foreach ($cargos as $c)
                    <option value="{{ $c->id }}" @selected((int) request('cargo') === $c->id)>{{ $c->denominacao }} ({{ $c->simbolo }})</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-s"><x-icone nome="busca" />Filtrar</button>
    </form>

    <div class="card card-flush">
        <nav class="abas-lista" aria-label="Situação">
            <a class="aba" href="{{ request()->fullUrlWithQuery(['aba' => 'ativos', 'page' => null]) }}" aria-selected="{{ $aba === 'ativos' ? 'true' : 'false' }}">Ativos<span class="n">{{ $kpis['ativos'] }}</span></a>
            <a class="aba" href="{{ request()->fullUrlWithQuery(['aba' => 'desligados', 'page' => null]) }}" aria-selected="{{ $aba === 'desligados' ? 'true' : 'false' }}">Desligados<span class="n">{{ $kpis['desligados'] }}</span></a>
        </nav>
        @if ($servidores->isEmpty())
            <div class="vazio">
                @if (request()->filled('busca') || request()->filled('unidade') || request()->filled('cargo'))
                    <b>Nenhum servidor encontrado com esses filtros.</b>Tente outro nome ou limpe os filtros.
                @elseif ($aba === 'desligados')
                    <b>Nenhum servidor desligado.</b>Quem for exonerado continua aqui, com o histórico.
                @else
                    <b>Nenhum servidor cadastrado ainda.</b>
                    @can('servidores.gerenciar')Cadastre um por vez ou importe a planilha modelo 03.@endcan
                @endif
            </div>
        @else
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Servidor</th><th>Cargo e símbolo</th><th>Lotação</th><th>Vínculo</th><th>{{ $aba === 'desligados' ? 'Desligamento' : 'Ato de nomeação' }}</th></tr></thead>
                    <tbody>
                        @foreach ($servidores as $s)
                            <tr>
                                <td>
                                    <a class="quem-cel" href="{{ route('servidores.show', $s) }}">
                                        <span class="avatar" aria-hidden="true">{{ $s->iniciais() }}</span>
                                        <span><b>{{ $s->nome }}</b><small>{{ $s->matricula() ? 'Matrícula '.$s->matricula() : 'CPF '.$s->cpfMascarado() }}</small></span>
                                    </a>
                                </td>
                                <td class="t2">
                                    @if ($s->vaga)
                                        <b>{{ $s->vaga->cargo->denominacao }}</b><small>{{ $s->vaga->cargo->simbolo }} · vaga nº {{ $s->vaga->numero }}</small>
                                    @elseif ($s->ativo())
                                        <span class="b b-warn">Sem vaga</span>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td>@if ($s->unidade)<span class="sigla">{{ $s->unidade->sigla }}</span>@else<span class="muted">—</span>@endif</td>
                                <td><span class="b b-mute">{{ $s->rotuloVinculo() }}</span></td>
                                <td class="t2">
                                    @if ($aba === 'desligados')
                                        <b>{{ $s->data_desligamento?->format('d/m/Y') }}</b><small>{{ $s->motivo_desligamento }}</small>
                                    @elseif ($s->decreto_nomeacao)
                                        <b>Decreto nº {{ $s->decreto_nomeacao }}</b><small>Efeito {{ $s->efeitos_a_partir_de?->format('d/m/Y') ?? '—' }}</small>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    <div class="paginacao">{{ $servidores->links() }}</div>
</x-layouts.app>
