<x-layouts.app titulo="Cargos e vagas">
    @php($pct = fn ($a, $b) => $b > 0 ? round($a * 100 / $b) : 0)
    <div class="row">
        <div>
            <h1 class="h1">Cargos e vagas</h1>
            <p class="sub">Quadro de cargos do órgão: quantos existem, quantos foram remanejados, quantos estão ocupados e quantos estão livres.</p>
        </div>
        @can('cargos.gerenciar')
            <div class="btns">
                <a href="{{ route('importacoes.index') }}" class="btn btn-s"><x-icone nome="relogio" />Histórico de importações</a>
                <a href="{{ route('importacoes.create') }}" class="btn btn-s"><x-icone nome="planilha" />Importar planilha</a>
                <a href="{{ route('cargos.create') }}" class="btn btn-p"><x-icone nome="mais" />Novo cargo</a>
            </div>
        @endcan
    </div>

    <div class="kpis">
        <div class="card kpi">
            <span class="lbl"><span class="chip c-navy"><x-icone nome="cargos" /></span>Cargos na estrutura</span>
            <span class="val">{{ $totais['total'] }}</span>
            <span class="foot">{{ $cargos->count() }} denominações</span>
        </div>
        <div class="card kpi">
            <span class="lbl"><span class="chip c-warn"><x-icone nome="seta" /></span>Remanejados</span>
            <span class="val">{{ $totais['remanejadas'] }}</span>
            <span class="foot">cedidos ou devolvidos a outros órgãos</span>
        </div>
        <div class="card kpi">
            <span class="lbl"><span class="chip c-teal"><x-icone nome="servidor" /></span>Ocupados</span>
            <span class="val">{{ $totais['ocupadas'] }}</span>
            <span class="foot">{{ $pct($totais['ocupadas'], $totais['disponiveis']) }}% dos {{ $totais['disponiveis'] }} disponíveis</span>
        </div>
        <div class="card kpi">
            <span class="lbl"><span class="chip c-navy"><x-icone nome="ok" /></span>Vagos</span>
            <span class="val">{{ $totais['vagas'] }}</span>
            <span class="foot">prontos para nomeação</span>
        </div>
    </div>

    <form method="GET" class="card filtros" role="search">
        @if ($orgaos->isNotEmpty())
            <div class="campo">
                <label for="f-orgao">Órgão</label>
                <select id="f-orgao" name="orgao" class="input" onchange="this.form.submit()">
                    @foreach ($orgaos as $o)
                        <option value="{{ $o->id }}" @selected($o->id === $orgaoId)>{{ $o->sigla }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="campo">
            <label for="f-busca">Cargo ou símbolo</label>
            <input id="f-busca" name="busca" value="{{ request('busca') }}" class="input" placeholder="Ex.: Assessor ou CNES-IV">
        </div>
        <div class="campo">
            <label for="f-natureza">Natureza</label>
            <select id="f-natureza" name="natureza" class="input">
                <option value="">Todas</option>
                @foreach (\App\Models\Cargo::NATUREZAS as $valor => $rotulo)
                    <option value="{{ $valor }}" @selected(request('natureza') === $valor)>{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>
        <label class="check"><input type="checkbox" name="com_vagas" value="1" @checked(request('com_vagas'))> Só com vagas livres</label>
        <label class="check"><input type="checkbox" name="inativos" value="1" @checked(request('inativos'))> Incluir inativos</label>
        <div class="btns">
            <button class="btn btn-s" type="submit"><x-icone nome="busca" />Filtrar</button>
        </div>
    </form>

    <div class="card card-flush">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead><tr><th>Cargo</th><th>Símbolo</th><th class="num">Remuneração</th><th class="num">Total</th><th class="num">Remanej.</th><th class="num">Ocupados</th><th class="num">Vagos</th><th style="min-width:160px">Ocupação</th></tr></thead>
                <tbody>
                    @forelse ($cargos as $cargo)
                        @php($t = $cargo->totais())
                        <tr>
                            <td>
                                <a href="{{ route('cargos.show', $cargo) }}" style="font-weight:700;text-decoration:none">{{ $cargo->denominacao }}</a>
                                <div class="muted small">{{ $cargo->rotuloNatureza() }}@unless ($cargo->ativo) · <span class="b b-mute">Inativo</span>@endunless</div>
                            </td>
                            <td class="sigla">{{ $cargo->simbolo }}</td>
                            <td class="num">{{ $cargo->remuneracao !== null ? 'R$ '.number_format((float) $cargo->remuneracao, 2, ',', '.') : '—' }}</td>
                            <td class="num">{{ $t['total'] }}</td>
                            <td class="num muted">{{ $t['remanejadas'] ?: '—' }}</td>
                            <td class="num">{{ $t['ocupadas'] }}</td>
                            <td class="num">@if ($t['vagas'])<span class="b b-info">{{ $t['vagas'] }}</span>@else<span class="muted">0</span>@endif</td>
                            <td>
                                <div class="barra" title="{{ $t['ocupadas'] }} de {{ $t['disponiveis'] }} disponíveis ocupados">
                                    <span style="width: {{ $pct($t['ocupadas'], $t['disponiveis']) }}%"></span>
                                </div>
                                <div class="muted small" style="margin-top:4px">{{ $pct($t['ocupadas'], $t['disponiveis']) }}% de {{ $t['disponiveis'] }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="vazio"><b>Nenhum cargo cadastrado</b>@can('cargos.gerenciar')Importe a planilha do quadro de cargos ou cadastre o primeiro cargo.@endcan</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
