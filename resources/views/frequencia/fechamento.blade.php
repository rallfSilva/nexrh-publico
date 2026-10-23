@use('App\Models\FechamentoFrequencia')
@php
    $user = auth()->user();
    $rh = $user->can('frequencia.gerenciar');
    $m = $mes->format('Y-m');
    $total = $linhas->count();
    $porSituacao = $linhas->countBy('situacao');
    $vencido = $prazo && today()->gt($prazo);
    $boletim = fn (string $formato, ?int $unidade = null) => route('relatorios.exportar', array_filter(['chave' => 'frequencia', 'formato' => $formato, 'mes' => $m, 'unidade' => $unidade]));
    $reabrirDialogo = old('dialogo');
@endphp
<x-layouts.app titulo="Fechamento do mês">
    <div class="row">
        <div>
            <a href="{{ route('frequencia.index') }}" class="small" style="font-weight:600;display:inline-flex;gap:4px;align-items:center"><x-icone nome="voltar" tamanho="16" />Frequência</a>
            <h1 class="h1" style="margin-top:8px">Fechamento do mês</h1>
            <p class="sub">A chefia confere o espelho da equipe e o RH fecha o mês de cada setor. Mês fechado não muda mais e o boletim fica guardado; para corrigir, o RH reabre com motivo.</p>
        </div>
        <div class="btns">
            <a href="{{ $boletim('pdf') }}" class="btn btn-s"><x-icone nome="download" />Boletim em PDF</a>
            <a href="{{ $boletim('xlsx') }}" class="btn btn-s"><x-icone nome="planilha" />Excel</a>
        </div>
    </div>

    <section class="kpis fech-kpis">
        <div class="card kpi">
            <span class="lbl"><x-icone nome="calendario" tamanho="18" />Prazo para fechar</span>
            <span class="val" style="font-size:26px">{{ $prazo ? ucfirst($prazo->translatedFormat('D, d/m')) : '—' }}</span>
            <span class="foot">{{ $vencido && ($porSituacao['fechado'] ?? 0) < $total ? 'Prazo vencido. ' : '' }}Dia {{ $diaFechamento }} de {{ $mes->copy()->addMonthNoOverflow()->translatedFormat('F') }}, ou o dia útil seguinte.</span>
        </div>
        <div class="card kpi">
            <span class="lbl"><x-icone nome="cadeado" tamanho="18" />Setores fechados</span>
            <span class="val">{{ $porSituacao['fechado'] ?? 0 }}<span style="font-size:16px;color:var(--color-texto2);font-weight:600;margin-left:6px;letter-spacing:0">de {{ $total }}</span></span>
            <span class="foot">{{ $porSituacao['conferido'] ?? 0 }} conferido(s) pela chefia, {{ $porSituacao['aberto'] ?? 0 }} aberto(s).</span>
        </div>
        <div class="card kpi">
            <span class="lbl"><x-icone nome="alerta" tamanho="18" />Justificativas em análise</span>
            <span class="val">{{ $linhas->sum(fn ($l) => $l['soma']['em_analise']) }}</span>
            <span class="foot">Setor com justificativa em análise não pode ser conferido nem fechado.</span>
        </div>
    </section>

    <section class="card">
        <div class="row fech-cab">
            <form method="GET" class="fech-mes">
                <label for="campo-mes" class="sr-only">Mês</label>
                <select id="campo-mes" name="mes" class="input" onchange="this.form.submit()">
                    @foreach ($meses as $v => $r)
                        <option value="{{ $v }}" @selected($v === $m)>{{ $r }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="btn btn-s">Mostrar</button></noscript>
            </form>
            @if ($rh && $conferidos > 0)
                <form method="POST" action="{{ route('frequencia.fechamento.conferidos') }}" data-um-envio onsubmit="return confirm('Fechar o mês dos {{ $conferidos }} setor(es) conferido(s)? Depois de fechado, nada muda no mês sem reabrir.')">@csrf
                    <input type="hidden" name="mes" value="{{ $m }}">
                    <button type="submit" class="btn btn-p"><x-icone nome="cadeado" />Fechar os {{ $conferidos }} conferidos</button>
                </form>
            @endif
        </div>

        <p class="hint" style="margin:0 0 14px">Os números são dos dias não abonados. O servidor ainda pode justificar até o mês ser fechado.</p>

        @if ($linhas->isEmpty())
            <div class="vazio"><b>Nenhum setor para mostrar.</b>{{ $rh ? 'Nenhum setor ativo com servidores neste mês.' : 'O fechamento aparece para os setores que você chefia.' }}</div>
        @else
            <div class="docs">
                @foreach ($linhas as $l)
                    @php
                        $u = $l['unidade'];
                        $f = $l['fechamento'];
                        $s = $l['soma'];
                        $situacao = $l['situacao'];
                        $pendentes = $s['faltas'] + $s['incompletos'] + $s['atencao'];
                    @endphp
                    <div class="doc fech-setor">
                        <span class="chip {{ $situacao === 'fechado' ? 'c-teal' : ($situacao === 'conferido' ? 'c-navy' : 'c-mute') }}" style="width:38px;height:38px" aria-hidden="true"><x-icone nome="{{ $situacao === 'fechado' ? 'cadeado' : ($situacao === 'conferido' ? 'ok' : 'relogio') }}" tamanho="18" /></span>
                        <div class="txt">
                            <b><a href="{{ route('frequencia.index', ['unidade' => $u->id, 'mes' => $m]) }}">{{ $u->sigla }}</a> · {{ $u->nome }}</b>
                            <small>
                                {{ $s['pessoas'] }} {{ $s['pessoas'] === 1 ? 'pessoa' : 'pessoas' }}
                                · <span @class(['fech-num', 'ruim' => $s['faltas']])>{{ $s['faltas'] }} sem registro</span>
                                · <span @class(['fech-num', 'atencao' => $s['incompletos'] + $s['atencao']])>{{ $s['incompletos'] + $s['atencao'] }} incompleto(s) ou a verificar</span>
                                · {{ $s['abonados'] }} abonado(s)
                                @if ($s['em_analise']) · <strong style="font-weight:600;color:#9A3412">{{ $s['em_analise'] }} em análise</strong>@endif
                            </small>
                            @if ($f?->conferido_em)
                                <small>Conferido por {{ $f->conferidoPor?->name ?? 'usuário removido' }} em {{ $f->conferido_em->format('d/m/Y H:i') }}{{ $f->observacao_chefia ? ': "'.$f->observacao_chefia.'"' : '' }}</small>
                            @endif
                            @if ($f?->fechado_em)
                                <small>Fechado por {{ $f->fechadoPor?->name ?? 'usuário removido' }} em {{ $f->fechado_em->format('d/m/Y H:i') }}</small>
                            @elseif ($f?->reaberto_em)
                                <small class="motivo">Reaberto por {{ $f->reabertoPor?->name ?? 'usuário removido' }} em {{ $f->reaberto_em->format('d/m/Y') }}: {{ $f->motivo_reabertura }}</small>
                            @endif
                        </div>
                        <span class="b {{ FechamentoFrequencia::SITUACOES[$situacao][1] }}">{{ FechamentoFrequencia::SITUACOES[$situacao][0] }}</span>
                        <div class="doc-acoes">
                            <a class="btn btn-s btn-sm btn-icone" href="{{ $boletim('pdf', $u->id) }}" title="Boletim de {{ $u->sigla }} em PDF" aria-label="Boletim de {{ $u->sigla }} em PDF"><x-icone nome="download" tamanho="16" /></a>
                            @if ($situacao === 'aberto' && $l['pode_conferir'])
                                <button type="button" class="btn {{ $rh ? 'btn-s' : 'btn-p' }} btn-sm" data-abrir-dialogo="dialogo-conferir" data-acao="{{ route('frequencia.fechamento.conferir', $u) }}" data-titulo="{{ $u->sigla }} · {{ $mes->format('m/Y') }}{{ $pendentes ? ' · '.$pendentes.' dia(s) sem justificativa' : '' }}" @disabled($s['em_analise'])@if ($s['em_analise']) title="Abone ou recuse as justificativas em análise antes" @endif><x-icone nome="ok" tamanho="16" />Conferir</button>
                            @endif
                            @if ($rh && $situacao !== 'fechado')
                                <form method="POST" action="{{ route('frequencia.fechamento.fechar', $u) }}" data-um-envio onsubmit="return confirm('Fechar o mês de {{ $u->sigla }}{{ $situacao === 'aberto' ? ' sem a conferência da chefia' : '' }}? Depois de fechado, nada muda no mês sem reabrir.')">@csrf
                                    <input type="hidden" name="mes" value="{{ $m }}">
                                    <button type="submit" class="btn {{ $situacao === 'conferido' ? 'btn-p' : 'btn-s' }} btn-sm" @disabled($s['em_analise'])@if ($s['em_analise']) title="Abone ou recuse as justificativas em análise antes" @endif><x-icone nome="cadeado" tamanho="16" />Fechar</button>
                                </form>
                            @endif
                            @if ($rh && $situacao === 'fechado')
                                <button type="button" class="btn btn-s btn-sm" data-abrir-dialogo="dialogo-reabrir" data-acao="{{ route('frequencia.fechamento.reabrir', $u) }}" data-titulo="{{ $u->sigla }} · {{ $mes->format('m/Y') }}">Reabrir</button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            @if ($linhas->sum(fn ($l) => $l['soma']['em_analise']) > 0)
                <p class="small muted" style="margin:14px 0 0">Há justificativas em análise: decida em <a href="{{ route('justificativas.index') }}">Justificativas</a>.</p>
            @endif
        @endif
    </section>

    <dialog class="modal" id="dialogo-conferir" aria-labelledby="t-conferir">
        <form method="POST" action="{{ $reabrirDialogo === 'dialogo-conferir' ? old('acao_dialogo') : '#' }}" data-um-envio>
            @csrf
            <input type="hidden" name="mes" value="{{ $m }}">
            <input type="hidden" name="dialogo" value="dialogo-conferir">
            <input type="hidden" name="acao_dialogo" value="{{ old('acao_dialogo') }}">
            <div><h2 id="t-conferir" style="margin:0;font-size:18px">Conferir a frequência do mês</h2><p class="small muted" style="margin:6px 0 0"><b data-titulo-dialogo></b>. Você confirma que revisou o espelho da equipe. Dias sem justificativa contam como falta no boletim. Depois, o RH fecha o mês.</p></div>
            <div class="campo">
                <label for="campo-observacao-chefia">Observação para o RH <span class="muted" style="font-weight:400">(opcional)</span></label>
                <textarea id="campo-observacao-chefia" name="observacao_chefia" class="input" rows="3" maxlength="500" style="padding-top:10px;min-height:80px" placeholder="Ex.: João em missão externa nos dias 12 e 13, ofício a caminho.">{{ $reabrirDialogo === 'dialogo-conferir' ? old('observacao_chefia') : '' }}</textarea>
                @if ($reabrirDialogo === 'dialogo-conferir' && $errors->has('observacao_chefia'))<span class="erro">{{ $errors->first('observacao_chefia') }}</span>@endif
            </div>
            <div class="form-acoes">
                <button type="button" class="btn btn-s" data-fechar-dialogo>Voltar</button>
                <button type="submit" class="btn btn-p">Conferido</button>
            </div>
        </form>
    </dialog>

    @if ($rh)
        <dialog class="modal" id="dialogo-reabrir" aria-labelledby="t-reabrir">
            <form method="POST" action="{{ $reabrirDialogo === 'dialogo-reabrir' ? old('acao_dialogo') : '#' }}" data-um-envio>
                @csrf
                <input type="hidden" name="mes" value="{{ $m }}">
                <input type="hidden" name="dialogo" value="dialogo-reabrir">
                <input type="hidden" name="acao_dialogo" value="{{ old('acao_dialogo') }}">
                <div><h2 id="t-reabrir" style="margin:0;font-size:18px">Reabrir o mês</h2><p class="small muted" style="margin:6px 0 0"><b data-titulo-dialogo></b>. O boletim guardado é descartado e o mês volta a aceitar marcações, justificativas e abonos. A conferência da chefia continua valendo. Feche de novo depois de corrigir.</p></div>
                <div class="campo">
                    <label for="campo-motivo-reabertura">Motivo <span aria-hidden="true" style="color:#9F1D1D">*</span></label>
                    <textarea id="campo-motivo-reabertura" name="motivo_reabertura" class="input" rows="3" maxlength="255" required style="padding-top:10px;min-height:80px" placeholder="Ex.: atestado entregue com atraso, aceito pela chefia.">{{ $reabrirDialogo === 'dialogo-reabrir' ? old('motivo_reabertura') : '' }}</textarea>
                    @if ($reabrirDialogo === 'dialogo-reabrir' && $errors->has('motivo_reabertura'))<span class="erro">{{ $errors->first('motivo_reabertura') }}</span>@endif
                </div>
                <div class="form-acoes">
                    <button type="button" class="btn btn-s" data-fechar-dialogo>Voltar</button>
                    <button type="submit" class="btn btn-d">Reabrir o mês</button>
                </div>
            </form>
        </dialog>
    @endif
    @if ($reabrirDialogo && $errors->hasAny(['observacao_chefia', 'motivo_reabertura']) && str_contains((string) old('acao_dialogo'), '/frequencia/fechamento/'))
        <script type="module">document.getElementById(@json($reabrirDialogo))?.showModal();</script>
    @endif
</x-layouts.app>
