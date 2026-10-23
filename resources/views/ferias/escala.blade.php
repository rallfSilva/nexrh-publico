@php
    $user = auth()->user();
    $minimo = (int) $unidade?->minimo_presentes_ferias;
    $total = $equipe->count();
    $meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    $iniciais = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];
    $listaDias = collect($dias)->map(fn ($d) => $d->copy());
    $foraNoDia = fn (string $chave) => collect($grade)->filter(fn ($dias) => isset($dias[$chave]))->count();
    $link = fn (array $extra) => route('ferias.escala', array_merge(['unidade' => $unidade?->id, 'visao' => $visao, 'mes' => $mes->format('Y-m'), 'ano' => $ano], $extra));
@endphp
<x-layouts.app titulo="Escala de férias">
    <div class="row">
        <div>
            <h1 class="h1">Escala de férias</h1>
            <p class="sub">Quem está fora de cada setor, dia a dia. O sistema não deixa marcar férias que deixem o setor abaixo do mínimo de presentes.</p>
        </div>
        <div class="btns">
            @can('aprovar-ferias')
                <a href="{{ route('ferias.pendentes') }}" class="btn btn-s"><x-icone nome="ok" />A aprovar @if ($aAprovar)<span class="b b-warn">{{ $aAprovar }}</span>@endif</a>
            @endcan
            @can('ferias.gerenciar')
                <a href="{{ route('ferias.regras') }}" class="btn btn-s"><x-icone nome="editar" />Regras de férias</a>
            @endcan
        </div>
    </div>

    @if ($unidades->isEmpty())
        <div class="card"><div class="vazio"><b>Nenhuma unidade para mostrar.</b>A escala aparece para as unidades que você chefia.</div></div>
    @else
        <form method="GET" class="card filtros escala-filtros">
            <x-lista nome="unidade" rotulo="Setor" :opcoes="$unidades->mapWithKeys(fn ($u) => [$u->id => ($user->orgao_id === null ? $u->orgao->sigla.' · ' : '').$u->sigla.' · '.$u->nome])->all()" :valor="$unidade?->id" vazio="Escolha o setor" onchange="this.form.submit()" />
            <x-escolha nome="visao" rotulo="Ver" :opcoes="['mes' => 'Mês', 'ano' => 'Ano']" :valor="$visao" />
            @if ($visao === 'mes')
                <x-campo nome="mes" rotulo="Mês" tipo="month" :valor="$mes->format('Y-m')" />
            @else
                <x-campo nome="ano" rotulo="Ano" tipo="number" min="2000" max="2100" :valor="$ano" />
            @endif
            <button type="submit" class="btn btn-s">Mostrar</button>
        </form>

        <section class="card card-flush">
            <div class="escala-cab">
                <div>
                    <h2>{{ $unidade->sigla }} · {{ $visao === 'mes' ? ucfirst($mes->translatedFormat('F \d\e Y')) : $ano }}</h2>
                    <p class="hint">{{ $total }} {{ $total === 1 ? 'pessoa' : 'pessoas' }} no setor ·
                        @if ($minimo) mínimo de {{ $minimo }} {{ $minimo === 1 ? 'presente' : 'presentes' }} @else <span style="color:#9A3412">sem mínimo definido: o bloqueio pelo setor não se aplica</span> @endif
                    </p>
                </div>
                <div class="btns">
                    @if ($visao === 'mes')
                        <a class="btn btn-s btn-sm btn-icone" href="{{ $link(['mes' => $mes->copy()->subMonth()->format('Y-m')]) }}" aria-label="Mês anterior" title="Mês anterior"><x-icone nome="voltar" tamanho="16" /></a>
                        <a class="btn btn-s btn-sm" href="{{ $link(['mes' => today()->format('Y-m')]) }}">Hoje</a>
                        <a class="btn btn-s btn-sm btn-icone" href="{{ $link(['mes' => $mes->copy()->addMonth()->format('Y-m')]) }}" aria-label="Próximo mês" title="Próximo mês"><x-icone nome="seta" tamanho="16" /></a>
                    @else
                        <a class="btn btn-s btn-sm btn-icone" href="{{ $link(['ano' => $ano - 1]) }}" aria-label="Ano anterior" title="Ano anterior"><x-icone nome="voltar" tamanho="16" /></a>
                        <a class="btn btn-s btn-sm btn-icone" href="{{ $link(['ano' => $ano + 1]) }}" aria-label="Próximo ano" title="Próximo ano"><x-icone nome="seta" tamanho="16" /></a>
                    @endif
                </div>
            </div>
            <div class="legenda">
                <span><i class="lg ferias"></i>Férias aprovadas</span>
                <span><i class="lg pedido"></i>Aguardando aprovação</span>
                <span><i class="lg afastamento"></i>Licença ou afastamento</span>
                @if ($visao === 'mes')<span><i class="lg baixo"></i>Abaixo do mínimo</span>@endif
            </div>

            @if ($equipe->isEmpty())
                <div class="vazio"><b>Ninguém lotado neste setor.</b></div>
            @elseif ($visao === 'mes')
                <div class="tbl-wrap">
                    <table class="grade-ferias">
                        <thead>
                            <tr>
                                <th class="nome" scope="col">Servidor</th>
                                @foreach ($listaDias as $dia)
                                    <th scope="col" class="{{ $dia->isWeekend() ? 'fds' : '' }} {{ $dia->isToday() ? 'hoje' : '' }}"><span>{{ $iniciais[$dia->dayOfWeek] }}</span>{{ $dia->day }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($equipe as $s)
                                <tr>
                                    <th class="nome" scope="row"><a href="{{ route('servidores.show', [$s, 'aba' => 'ferias']) }}">{{ $s->nome }}</a></th>
                                    @foreach ($listaDias as $dia)
                                        @php
                                            $c = $grade[$s->id][$dia->format('Y-m-d')] ?? null;
                                        @endphp
                                        <td class="{{ $dia->isWeekend() ? 'fds' : '' }} {{ $c['tipo'] ?? '' }}" @if ($c) title="{{ $c['titulo'] }}" @endif>@if ($c)<span class="sr-only">{{ $c['titulo'] }}</span>@endif</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th class="nome" scope="row">Presentes</th>
                                @foreach ($listaDias as $dia)
                                    @php
                                        $presentes = $total - $foraNoDia($dia->format('Y-m-d'));
                                    @endphp
                                    <td class="{{ $minimo && $presentes < $minimo ? 'baixo' : '' }} {{ $dia->isWeekend() ? 'fds' : '' }}" title="{{ $presentes }} de {{ $total }} presentes em {{ $dia->format('d/m') }}">{{ $presentes }}</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                @php
                    $porMes = [];
                    foreach ($grade as $sid => $diasFora) {
                        foreach ($diasFora as $chave => $c) {
                            $m = (int) substr($chave, 5, 2);
                            $porMes[$sid][$m][$c['tipo']] = ($porMes[$sid][$m][$c['tipo']] ?? 0) + 1;
                        }
                    }
                @endphp
                <div class="tbl-wrap">
                    <table class="grade-ferias ano">
                        <thead>
                            <tr>
                                <th class="nome" scope="col">Servidor</th>
                                @foreach ($meses as $i => $m)
                                    <th scope="col" class="{{ $ano === today()->year && $i + 1 === today()->month ? 'hoje' : '' }}"><a href="{{ $link(['visao' => 'mes', 'mes' => sprintf('%04d-%02d', $ano, $i + 1)]) }}">{{ $m }}</a></th>
                                @endforeach
                                <th scope="col" class="num">Dias</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($equipe as $s)
                                @php
                                    $soma = 0;
                                @endphp
                                <tr>
                                    <th class="nome" scope="row"><a href="{{ route('servidores.show', [$s, 'aba' => 'ferias']) }}">{{ $s->nome }}</a></th>
                                    @for ($m = 1; $m <= 12; $m++)
                                        @php
                                            $q = $porMes[$s->id][$m] ?? [];
                                            $tipo = array_key_first(array_filter(['ferias' => $q['ferias'] ?? 0, 'pedido' => $q['pedido'] ?? 0, 'afastamento' => $q['afastamento'] ?? 0]));
                                            $n = array_sum($q);
                                            $soma += ($q['ferias'] ?? 0) + ($q['pedido'] ?? 0);
                                        @endphp
                                        <td class="{{ $tipo }}" @if ($n) title="{{ collect(['férias' => $q['ferias'] ?? 0, 'aguardando aprovação' => $q['pedido'] ?? 0, 'afastamento' => $q['afastamento'] ?? 0])->filter()->map(fn ($v, $k) => $v.' dia(s) de '.$k)->implode(', ') }}" @endif>{{ $n ?: '' }}</td>
                                    @endfor
                                    <td class="num"><b>{{ $soma ?: '—' }}</b></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="small muted" style="margin:0;padding:12px 20px 18px">Cada célula mostra quantos dias a pessoa fica fora no mês. A coluna "Dias" soma só as férias. Clique no mês para ver dia a dia.</p>
            @endif
        </section>

        @if ($proximas->isNotEmpty())
            <section class="card">
                <h2>Próximos 60 dias em {{ $unidade->sigla }}</h2>
                <div class="list">
                    @foreach ($proximas as $f)
                        <div class="li">
                            <span class="chip {{ $f->situacao === 'programada' ? 'c-warn' : 'c-navy' }}"><x-icone nome="sol" tamanho="16" /></span>
                            <div><b>{{ $f->servidor->nome }}</b><div class="meta">{{ $f->periodoTexto() }} · {{ $f->dias }} dias</div></div>
                            <span class="r"><span class="b {{ $f->selo()[1] }}">{{ $f->selo()[0] }}</span></span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    @endif
</x-layouts.app>
