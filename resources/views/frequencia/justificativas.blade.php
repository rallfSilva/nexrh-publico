@php
    $rh = auth()->user()->can('frequencia.gerenciar');
@endphp
<x-layouts.app titulo="Justificativas de ponto">
    <div>
        <a href="{{ route('frequencia.index') }}" class="small" style="font-weight:600;display:inline-flex;gap:4px;align-items:center"><x-icone nome="voltar" tamanho="16" />Frequência</a>
        <h1 class="h1" style="margin-top:8px">Justificativas de ponto</h1>
        <p class="sub">Faltas, atrasos e registros esquecidos justificados pelos servidores{{ $rh ? ' do órgão' : ' da sua equipe' }}. Abonar faz o dia contar como trabalhado; recusar pede um motivo, que o servidor vê. As suas próprias justificativas vão para a chefia superior ou para o RH.</p>
    </div>

    <nav class="abas-lista card" style="padding:6px 16px 0;border-radius:14px" aria-label="Justificativas">
            <a class="aba" href="{{ route('justificativas.index') }}" aria-selected="{{ $aba === 'analise' ? 'true' : 'false' }}"><x-icone nome="relogio" tamanho="18" />Em análise @if ($emAnalise)<em class="cnt">{{ $emAnalise }}</em>@endif</a>
            <a class="aba" href="{{ route('justificativas.index', ['aba' => 'decididas']) }}" aria-selected="{{ $aba === 'decididas' ? 'true' : 'false' }}"><x-icone nome="ok" tamanho="18" />Decididas nos últimos 60 dias</a>
    </nav>

    <section class="card">
        @if ($lista->isEmpty())
            <div class="vazio">
                @if ($aba === 'analise')
                    <b>Nada para analisar.</b>Quando alguém da equipe justificar uma ocorrência, ela aparece aqui.
                @else
                    <b>Nenhuma decisão nos últimos 60 dias.</b>
                @endif
            </div>
        @else
            <div class="docs">
                @foreach ($lista as $j)
                    <div class="doc">
                        <span class="chip {{ $j->tipo === 'atestado' || $j->tipo === 'declaracao' ? 'c-teal' : 'c-navy' }}" style="width:38px;height:38px" aria-hidden="true"><x-icone nome="{{ $j->temArquivo() ? 'arquivo' : 'editar' }}" tamanho="18" /></span>
                        <div class="txt">
                            <b><a href="{{ route('frequencia.espelho', [$j->servidor, 'mes' => $j->inicio->format('Y-m')]) }}">{{ $j->servidor->nome }}</a> · {{ $j->periodoTexto() }} · {{ $j->rotuloTipo() }}</b>
                            <small>{{ $j->servidor->unidade?->sigla ?? 'Sem lotação' }} · {{ $j->enviado_pelo_servidor ? 'enviada' : 'lançada pelo RH' }} em {{ $j->created_at->format('d/m/Y H:i') }}@if ($j->ocorrencia) · <strong style="font-weight:600;color:#9A3412">{{ $j->ocorrencia }}</strong>@endif</small>
                            <small style="color:var(--color-tinta)">"{{ $j->motivo }}"@if ($j->horarios) · horário informado: {{ $j->horarios }}@endif</small>
                            @if ($j->temArquivo())
                                <small><a href="{{ route('justificativas.arquivo', $j) }}" target="_blank" rel="noopener">{{ $j->arquivo_nome }}</a> ({{ number_format($j->arquivo_tamanho / 1024, 0, ',', '.') }} KB)</small>
                            @endif
                            @if ($aba === 'decididas')
                                <small class="{{ $j->situacao === 'recusada' ? 'motivo' : '' }}">{{ $j->situacao === 'abonada' ? 'Abonada' : 'Recusada' }} por {{ $j->analisadoPor?->name ?? 'usuário removido' }} em {{ $j->analisado_em->format('d/m/Y') }}{{ $j->motivo_decisao ? ': '.$j->motivo_decisao : '' }}</small>
                            @endif
                        </div>
                        @if ($aba === 'analise')
                            <div class="doc-acoes">
                                <button type="button" class="btn btn-s btn-sm" data-abrir-dialogo="dialogo-recusar" data-acao="{{ route('justificativas.recusar', $j) }}" data-titulo="{{ $j->servidor->nome }} · {{ $j->periodoTexto() }}"><x-icone nome="fechar" tamanho="16" />Recusar</button>
                                <form method="POST" action="{{ route('justificativas.abonar', $j) }}" data-um-envio>@csrf
                                    <button type="submit" class="btn btn-p btn-sm"><x-icone nome="ok" tamanho="16" />Abonar</button>
                                </form>
                            </div>
                        @else
                            <span class="b {{ $j->selo()[1] }}">{{ $j->selo()[0] }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <dialog class="modal" id="dialogo-recusar" aria-labelledby="t-recusar">
        <form method="POST" action="{{ str_contains((string) old('acao_dialogo'), '/frequencia/justificativas/') ? old('acao_dialogo') : '#' }}">
            @csrf
            <input type="hidden" name="acao_dialogo" value="{{ old('acao_dialogo') }}">
            <div><h2 id="t-recusar" style="margin:0;font-size:18px">Recusar justificativa</h2><p class="small muted" style="margin:6px 0 0"><b data-titulo-dialogo></b>. O servidor vê o motivo em Meu ponto e pode enviar outra justificativa.</p></div>
            <div class="campo">
                <label for="campo-motivo-decisao">Motivo <span aria-hidden="true" style="color:#9F1D1D">*</span></label>
                <textarea id="campo-motivo-decisao" name="motivo_decisao" class="input" rows="3" maxlength="255" required style="padding-top:10px;min-height:90px">{{ old('motivo_decisao') }}</textarea>
                @error('motivo_decisao')<span class="erro">{{ $message }}</span>@enderror
            </div>
            <div class="form-acoes">
                <button type="button" class="btn btn-s" data-fechar-dialogo>Voltar</button>
                <button type="submit" class="btn btn-d">Recusar</button>
            </div>
        </form>
    </dialog>
    @if ($errors->has('motivo_decisao') && old('acao_dialogo'))
        <script type="module">document.getElementById('dialogo-recusar').showModal();</script>
    @endif
</x-layouts.app>
