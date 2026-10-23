{{-- Aba Férias da ficha: períodos aquisitivos com saldo e etapas, e licenças ou afastamentos. --}}
@php
    use App\Models\RegraFerias;
    $regra = RegraFerias::doOrgao($s->orgao_id);
    $periodosLista = $s->periodosAquisitivos->sortByDesc('inicio');
    $saldoTotal = $s->periodosAquisitivos->sum(fn ($p) => $p->saldo());
    $proxima = $s->ferias->whereIn('situacao', ['programada', 'aprovada'])->where('fim', '>=', today())->sortBy('inicio')->first();
    $afastamentosValidos = $s->afastamentos->filter->valido();
    $afastamentosCancelados = $s->afastamentos->reject->valido();
@endphp

<div class="ferias-topo">
    <div class="fx">
        <span class="lbl">Saldo para marcar</span>
        <b>{{ $saldoTotal }} {{ $saldoTotal === 1 ? 'dia' : 'dias' }}</b>
        <small>{{ $s->periodosAquisitivos->filter(fn ($p) => $p->saldo() > 0)->count() }} período(s) com saldo</small>
    </div>
    <div class="fx">
        <span class="lbl">{{ $proxima && $proxima->inicio->lte(today()) ? 'Em férias até' : 'Próximas férias' }}</span>
        @if ($proxima)
            <b>{{ $proxima->inicio->lte(today()) ? $proxima->fim->format('d/m/Y') : $proxima->inicio->format('d/m/Y') }}</b>
            <small>{{ $proxima->dias }} dias · {{ $proxima->selo()[0] }}</small>
        @else
            <b class="muted" style="font-weight:600">Nenhuma marcada</b>
            <small>&nbsp;</small>
        @endif
    </div>
    <div class="btns">
        @if ($propria && $s->ativo() && $saldoTotal > 0)
            <a href="{{ route('minha-ficha.ferias.create') }}" class="btn btn-p"><x-icone nome="sol" />Pedir férias</a>
        @endif
        @if ($gerenciaFerias && $s->ativo())
            <a href="{{ route('afastamentos.create', $s) }}" class="btn btn-s"><x-icone nome="mais" />Afastamento</a>
            @if ($saldoTotal > 0)
                <a href="{{ route('ferias.create', $s) }}" class="btn btn-p"><x-icone nome="sol" />Marcar férias</a>
            @endif
        @endif
    </div>
</div>

@if ($periodosLista->isEmpty())
    <div class="vazio"><b>Nenhum período aquisitivo.</b>Os períodos são contados a partir da data de efeitos da nomeação.</div>
@endif

<div class="periodos">
    @foreach ($periodosLista as $p)
        @php
            $etapas = $p->ferias->sortBy('inicio');
            $marcadas = $etapas->whereIn('situacao', \App\Models\Ferias::OCUPAM)->values();
            $usado = $p->diasMarcados();
            $saldo = $p->saldo();
            $liberado = $p->liberadoEm();
        @endphp
        <section class="periodo">
            <header>
                <div>
                    <b>Período {{ $p->rotulo() }}</b>
                    <small>{{ $p->inicio->format('d/m/Y') }} a {{ $p->fim->format('d/m/Y') }}
                        · @if ($p->completo()) completo @else em curso @endif
                        @if ($liberado->gt(today())) · férias a partir de {{ $liberado->format('d/m/Y') }} @endif
                    </small>
                </div>
                <div class="saldo">
                    <span class="b {{ $saldo === 0 ? 'b-ok' : ($p->completo() ? 'b-warn' : 'b-info') }}">{{ $saldo === 0 ? 'Todo marcado' : 'Saldo de '.$saldo.' dias' }}</span>
                    <div class="barra" title="{{ $usado }} de {{ $p->dias_direito }} dias marcados" aria-hidden="true"><span style="width:{{ $p->dias_direito ? round($usado / $p->dias_direito * 100) : 0 }}%"></span></div>
                </div>
            </header>

            @if ($etapas->isEmpty())
                <p class="small muted" style="margin:10px 0 0">Nenhuma etapa marcada.</p>
            @else
                <div class="etapas-ferias">
                    @foreach ($etapas as $f)
                        @php
                            [$rotuloSelo, $classeSelo] = $f->selo();
                            $ordem = $marcadas->search(fn ($m) => $m->is($f));
                        @endphp
                        <div class="etapa-f {{ $f->ocupa() ? '' : 'inativa' }}">
                            <span class="chip {{ $f->situacao === 'programada' ? 'c-warn' : ($f->ocupa() ? 'c-navy' : 'c-mute') }}" aria-hidden="true"><x-icone nome="ferias" tamanho="16" /></span>
                            <div class="txt">
                                <b>{{ $f->periodoTexto() }}</b>
                                <small>
                                    {{ $f->dias }} {{ $f->dias === 1 ? 'dia' : 'dias' }}{{ $ordem !== false ? ' · '.($ordem + 1).'ª etapa' : '' }}
                                    @if ($f->situacao === 'interrompida') · previstas até {{ $f->fim_previsto?->format('d/m/Y') }}, volta em {{ $f->interrompida_em?->format('d/m/Y') }} @endif
                                    @if ($f->solicitada_pelo_servidor) · pedida pelo servidor @endif
                                    @if ($f->analisadoPor && $f->situacao !== 'programada') · {{ ['aprovada' => 'aprovada', 'recusada' => 'recusada', 'cancelada' => 'cancelada', 'interrompida' => 'interrompida'][$f->situacao] ?? '' }} por {{ $f->analisadoPor->name }} @endif
                                    @if ($f->processo_sei && ! $propria) · SEI {{ $f->processo_sei }} @endif
                                </small>
                                @if ($f->motivo && in_array($f->situacao, ['recusada', 'cancelada', 'interrompida'], true))
                                    <small class="motivo"><b>Motivo:</b> {{ $f->motivo }}</small>
                                @endif
                            </div>
                            <span class="b {{ $classeSelo }}">{{ $rotuloSelo }}</span>
                            <div class="doc-acoes">
                                @if ($propria && $f->situacao === 'programada')
                                    <form method="POST" action="{{ route('minha-ficha.ferias.desistir', $f) }}" onsubmit="return confirm('Retirar o pedido de férias de {{ $f->periodoTexto() }}?')">@csrf
                                        <button type="submit" class="btn btn-s btn-sm">Retirar pedido</button>
                                    </form>
                                @endif
                                @if ($gerenciaFerias && $f->podeInterromper())
                                    <button type="button" class="btn btn-s btn-sm" data-abrir-dialogo="dialogo-interromper" data-acao="{{ route('ferias.interromper', [$s, $f]) }}" data-titulo="{{ $f->periodoTexto() }}">Interromper</button>
                                @endif
                                @if ($gerenciaFerias && $f->podeCancelar() && $f->situacao === 'aprovada')
                                    <button type="button" class="btn btn-s btn-sm" data-abrir-dialogo="dialogo-motivo-ferias" data-acao="{{ route('ferias.cancelar', [$s, $f]) }}" data-titulo="Cancelar as férias de {{ $f->periodoTexto() }}">Cancelar</button>
                                @endif
                            </div>
                            @if ($aprovaFerias && $f->situacao === 'programada')
                                <div class="doc-decisao">
                                    <span>Pedido de {{ $f->solicitadoPor?->name ?? 'servidor' }} em {{ $f->created_at->format('d/m/Y') }}. Aprovar?</span>
                                    <button type="button" class="btn btn-s btn-sm" data-abrir-dialogo="dialogo-motivo-ferias" data-acao="{{ route('ferias.recusar', [$s, $f]) }}" data-titulo="Recusar o pedido de {{ $f->periodoTexto() }}"><x-icone nome="fechar" tamanho="16" />Recusar</button>
                                    <form method="POST" action="{{ route('ferias.aprovar', [$s, $f]) }}">@csrf
                                        <button type="submit" class="btn btn-p btn-sm"><x-icone nome="ok" tamanho="16" />Aprovar</button>
                                    </form>
                                    @if ($gerenciaFerias)
                                        <button type="button" class="btn btn-s btn-sm" data-abrir-dialogo="dialogo-motivo-ferias" data-acao="{{ route('ferias.cancelar', [$s, $f]) }}" data-titulo="Cancelar o pedido de {{ $f->periodoTexto() }}">Cancelar</button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach
</div>

@if ($gerenciaFerias && $s->ferias->isEmpty() && $s->periodosAquisitivos->isNotEmpty())
    <details class="arquivados">
        <summary>Os períodos deveriam começar em outra data?</summary>
        <form method="POST" action="{{ route('ferias.data-base', $s) }}" class="filtros" style="margin-top:12px">
            @csrf
            <x-campo nome="data_base" rotulo="Início do primeiro período aquisitivo" tipo="date" :valor="$s->periodosAquisitivos->first()->inicio->format('Y-m-d')" :max="today()->format('Y-m-d')" obrigatorio
                ajuda="Para quem traz tempo de outro vínculo com o Estado. Só enquanto não há férias registradas." />
            <button type="submit" class="btn btn-s">Refazer os períodos</button>
        </form>
    </details>
@endif

<p class="secao" style="margin-top:26px">Licenças e afastamentos</p>
@if ($afastamentosValidos->isEmpty())
    <p class="small muted" style="margin:0">Nenhum afastamento registrado.</p>
@else
    <div class="tbl-wrap">
        <table class="tbl">
            <thead><tr><th>Tipo</th><th>Período</th><th class="num">Dias</th><th>Documento</th>@if ($gerenciaFerias)<th class="acoes"><span class="sr-only">Ações</span></th>@endif</tr></thead>
            <tbody>
                @foreach ($afastamentosValidos as $a)
                    <tr>
                        <td style="min-width:200px"><b>{{ $a->tipoPara(auth()->user()) }}</b>@if ($completo && $a->observacao)<div class="muted small">{{ $a->observacao }}</div>@endif</td>
                        <td style="white-space:nowrap">{{ $a->periodoTexto() }}@if ($a->inicio->lte(today()) && $a->fim->gte(today())) <span class="b b-ok">Agora</span>@endif</td>
                        <td class="num">{{ $a->dias() }}</td>
                        <td class="small">{{ collect([$a->documento, $a->processo_sei ? 'SEI '.$a->processo_sei : null])->filter()->implode(' · ') ?: '—' }}</td>
                        @if ($gerenciaFerias)
                            <td class="acoes"><button type="button" class="btn btn-s btn-sm" data-abrir-dialogo="dialogo-motivo-ferias" data-acao="{{ route('afastamentos.cancelar', [$s, $a]) }}" data-titulo="Cancelar o afastamento de {{ $a->periodoTexto() }}">Cancelar</button></td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@if ($afastamentosCancelados->isNotEmpty() && ! $propria)
    <details class="arquivados">
        <summary>Afastamentos cancelados ({{ $afastamentosCancelados->count() }})</summary>
        <ul class="small muted" style="margin:10px 0 0;padding-left:18px">
            @foreach ($afastamentosCancelados as $a)
                <li>{{ $a->tipoPara(auth()->user()) }} · {{ $a->periodoTexto() }} · cancelado em {{ $a->cancelado_em->format('d/m/Y') }}: {{ $a->motivo_cancelamento }}</li>
            @endforeach
        </ul>
    </details>
@endif

<div class="nota"><x-icone nome="calendario" tamanho="18" /><span>
    Regras do órgão: {{ $regra->dias_por_periodo }} dias por período de 12 meses, em até {{ $regra->max_etapas }} etapas de pelo menos {{ $regra->min_dias_etapa }} dias, no máximo {{ $regra->max_periodos_acumulados }} períodos acumulados{{ $propria ? ', pedido com '.$regra->antecedencia_dias.' dias de antecedência' : '' }}.
    O sistema não deixa marcar férias que coincidam com outro afastamento nem que deixem o setor abaixo do mínimo de pessoas.
    @if (! $regra->exists) <b>Regras provisórias, a confirmar com a norma do órgão.</b> @endif
</span></div>
