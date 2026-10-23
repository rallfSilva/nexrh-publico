@use('App\Frequencia\Espelho')
@use('App\Frequencia\Turnos')
@php
    $user = auth()->user();
    $link = fn (array $extra) => route('frequencia.index', array_merge(['unidade' => $unidade?->id, 'mes' => $mes->format('Y-m')], $extra));
    $doMesAtual = $mes->isSameMonth(today());
    $soma = fn (string $k) => $linhas->sum(fn ($l) => $l['totais'][$k]);
@endphp
<x-layouts.app titulo="Frequência">
    <div class="row">
        <div>
            <h1 class="h1">Frequência</h1>
            <p class="sub">Resumo do mês de cada setor. Clique no nome para ver o espelho do ponto dia a dia.</p>
        </div>
        @can('frequencia.gerenciar')
            <div class="btns">
                <a href="{{ route('importacoes.create', ['tipo' => 'ponto']) }}" class="btn btn-s"><x-icone nome="arquivo" />Importar relógio de ponto</a>
                <a href="{{ route('frequencia.regras') }}" class="btn btn-s"><x-icone nome="editar" />Regras e feriados</a>
            </div>
        @endcan
    </div>

    @if ($unidades->isEmpty())
        <div class="card"><div class="vazio"><b>Nenhuma unidade para mostrar.</b>A frequência aparece para as unidades que você chefia.</div></div>
    @else
        <form method="GET" class="card filtros">
            <x-lista nome="unidade" rotulo="Setor" :opcoes="$unidades->mapWithKeys(fn ($u) => [$u->id => ($user->orgao_id === null ? $u->orgao->sigla.' · ' : '').$u->sigla.' · '.$u->nome])->all()" :valor="$unidade?->id" vazio="Escolha o setor" onchange="this.form.submit()" />
            <x-campo nome="mes" rotulo="Mês" tipo="month" :valor="$mes->format('Y-m')" />
            <button type="submit" class="btn btn-s">Mostrar</button>
        </form>

        <section class="card card-flush">
            <div class="escala-cab">
                <div>
                    <h2>{{ $unidade->sigla }} · {{ ucfirst($mes->translatedFormat('F \d\e Y')) }}</h2>
                    <p class="hint">{{ $linhas->count() }} {{ $linhas->count() === 1 ? 'pessoa' : 'pessoas' }} · {{ $soma('faltas') }} {{ $soma('faltas') === 1 ? 'dia' : 'dias' }} sem registro · {{ $soma('incompletos') }} incompleto(s) · {{ $soma('atencao') }} a verificar</p>
                </div>
                <div class="btns">
                    <a class="btn btn-s btn-sm btn-icone" href="{{ $link(['mes' => $mes->copy()->subMonth()->format('Y-m')]) }}" aria-label="Mês anterior" title="Mês anterior"><x-icone nome="voltar" tamanho="16" /></a>
                    <a class="btn btn-s btn-sm" href="{{ $link(['mes' => today()->format('Y-m')]) }}">Mês atual</a>
                    <a class="btn btn-s btn-sm btn-icone" href="{{ $link(['mes' => $mes->copy()->addMonth()->format('Y-m')]) }}" aria-label="Próximo mês" title="Próximo mês"><x-icone nome="seta" tamanho="16" /></a>
                </div>
            </div>

            @if ($linhas->isEmpty())
                <div class="vazio"><b>Ninguém lotado neste setor.</b></div>
            @else
                <div class="tbl-wrap">
                    <table class="tbl tbl-freq">
                        <thead>
                            <tr>
                                <th scope="col">Servidor</th>
                                @if ($doMesAtual)<th scope="col">Hoje</th>@endif
                                <th scope="col" class="num" title="Dias de trabalho com as marcações completas">Com registro</th>
                                <th scope="col" class="num">Sem registro</th>
                                <th scope="col" class="num">Incompletos</th>
                                <th scope="col" class="num" title="Atraso, saída antes do horário ou horas a menos">A verificar</th>
                                <th scope="col" class="num">Horas</th>
                                <th scope="col" class="num">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($linhas as $l)
                                @php
                                    $t = $l['totais'];
                                    $saldo = $t['trabalhado'] - $t['esperado'];
                                    $hoje = $l['hoje'];
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('frequencia.espelho', [$l['servidor'], 'mes' => $mes->format('Y-m')]) }}"><b>{{ $l['servidor']->nome }}</b></a>
                                        <div class="small muted">{{ $l['jornada']->resumo() }}</div>
                                    </td>
                                    @if ($doMesAtual)
                                        <td>
                                            @if ($hoje && $hoje['registros']->filter->valido()->isNotEmpty())
                                                <div class="marcas">@foreach ($hoje['registros']->filter->valido() as $r)<span class="marca">{{ $r->momento->format('H:i') }}</span>@endforeach</div>
                                            @elseif ($hoje && $hoje['situacao'] !== 'hoje')
                                                <span class="b {{ Espelho::SITUACOES[$hoje['situacao']][1] }}">{{ Espelho::SITUACOES[$hoje['situacao']][0] }}</span>
                                            @else
                                                <span class="muted small">Sem marcação</span>
                                            @endif
                                        </td>
                                    @endif
                                    <td class="num">{{ $t['trabalhados'] }}<span class="muted"> / {{ $t['dias_trabalho'] }}</span></td>
                                    <td class="num">@if ($t['faltas'])<span class="b b-err">{{ $t['faltas'] }}</span>@else<span class="muted">0</span>@endif</td>
                                    <td class="num">@if ($t['incompletos'])<span class="b b-warn">{{ $t['incompletos'] }}</span>@else<span class="muted">0</span>@endif</td>
                                    <td class="num">@if ($t['atencao'])<span class="b b-warn">{{ $t['atencao'] }}</span>@else<span class="muted">0</span>@endif</td>
                                    <td class="num">{{ Turnos::horas($t['trabalhado']) }}<span class="muted"> / {{ Turnos::horas($t['esperado']) }}</span></td>
                                    <td class="num" style="{{ $saldo < 0 ? 'color:#9F1D1D;font-weight:600' : '' }}">{{ $saldo > 0 ? '+' : '' }}{{ Turnos::horas($saldo) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="small muted" style="margin:0;padding:12px 20px 18px">Dias de férias, licença, feriado e ponto facultativo não contam. "Sem registro" e "Incompletos" ainda podem ser justificados: as justificativas e os abonos chegam na próxima entrega.</p>
            @endif
        </section>
    @endif
</x-layouts.app>
