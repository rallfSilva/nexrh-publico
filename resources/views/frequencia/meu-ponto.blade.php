@use('App\Frequencia\Espelho')
@use('App\Frequencia\Marcador')
@php
    $marcasHoje = $hoje ? $hoje['registros']->filter->valido() : collect();
    $acao = Marcador::proximaAcao($marcasHoje->count());
    $navegar = fn ($m) => route('meu-ponto', ['mes' => $m->format('Y-m')]);
@endphp
<x-layouts.app titulo="Meu ponto">
    <div>
        <h1 class="h1">Meu ponto</h1>
        <p class="sub">Registre a entrada e a saída, confira o espelho do mês e justifique faltas, atrasos e registros esquecidos.</p>
    </div>

    <section class="card ponto-agora">
        <div class="relogio-grande" data-relogio="{{ now()->timestamp }}" data-fuso="{{ config('app.timezone') }}" aria-live="off">
            <span class="hora">{{ now()->format('H:i') }}</span>
            <span class="data">{{ ucfirst(today()->translatedFormat('l, d \d\e F \d\e Y')) }}</span>
        </div>
        <div class="ponto-info">
            <div>
                <span class="rotulo-mini">Jornada de hoje</span>
                <b>{{ $jornadaHoje->resumo() }}</b>
            </div>
            <div>
                <span class="rotulo-mini">Marcações de hoje</span>
                @if ($hoje && $hoje['registros']->isNotEmpty())
                    <div class="marcas">
                        @foreach ($hoje['registros'] as $r)
                            <span class="marca origem-{{ $r->origem }} {{ $r->valido() ? '' : 'anulada' }}">{{ $r->momento->format('H:i') }}</span>
                        @endforeach
                    </div>
                @else
                    <span class="muted">Nenhuma ainda.</span>
                @endif
            </div>
        </div>
        <div class="ponto-acao">
            @if ($impedimento)
                <div class="flash flash-aviso"><x-icone nome="alerta" /><span>{{ $impedimento }}</span></div>
            @else
                <form method="POST" action="{{ route('meu-ponto.registrar') }}" data-um-envio>
                    @csrf
                    <button type="submit" class="btn btn-p btn-ponto"><x-icone nome="relogio" tamanho="20" />Registrar {{ $acao }}</button>
                </form>
                <span class="small muted">Vale a hora do sistema, não a do seu computador.</span>
            @endif
            @error('ponto')<div class="flash flash-erro" style="margin-top:10px"><x-icone nome="alerta" /><span>{{ $message }}</span></div>@enderror
        </div>
    </section>

    <div class="ponto-lado">
        <section class="card" id="ocorrencias">
            <h2>Ocorrências para justificar</h2>
            <p class="hint" style="margin:4px 0 8px">Deste mês e do anterior. Depois do prazo, fale com o RH.</p>
            @if ($ocorrencias->isEmpty())
                <div class="vazio" style="padding:18px 0 4px"><b>Nada para justificar.</b>Faltas, atrasos e registros incompletos aparecem aqui no dia seguinte.</div>
            @else
                <ul class="ocorrencias">
                    @foreach ($ocorrencias->take(8) as $d)
                        <li>
                            <span class="chip {{ $d['situacao'] === 'falta' ? 'c-err' : 'c-warn' }}" aria-hidden="true"><x-icone nome="alerta" tamanho="16" /></span>
                            <div class="txt">
                                <b>{{ ucfirst($d['data']->translatedFormat('D, d/m')) }} · {{ Espelho::SITUACOES[$d['situacao']][0] }}</b>
                                <small>{{ $d['alertas'] ? implode(' · ', $d['alertas']) : ($d['situacao'] === 'falta' ? 'Nenhuma marcação no dia.' : 'Falta uma entrada ou saída.') }}{{ $d['justificativa']?->situacao === 'recusada' ? ' · justificativa recusada' : '' }}</small>
                            </div>
                            <a class="btn btn-s btn-sm" href="{{ route('justificativas.create', ['data' => $d['data']->format('Y-m-d')]) }}">Justificar</a>
                        </li>
                    @endforeach
                </ul>
                @if ($ocorrencias->count() > 8)<p class="small muted" style="margin:10px 0 0">E mais {{ $ocorrencias->count() - 8 }}. Veja todas no espelho abaixo.</p>@endif
            @endif
        </section>

        <section class="card">
            <h2>Minhas justificativas</h2>
            <p class="hint" style="margin:4px 0 8px">A chefia do setor abona ou recusa. O motivo aparece aqui.</p>
            @if ($justificativas->isEmpty())
                <div class="vazio" style="padding:18px 0 4px"><b>Nenhuma enviada.</b>Use "Justificar" numa ocorrência.</div>
            @else
                <ul class="ocorrencias">
                    @foreach ($justificativas as $j)
                        <li>
                            <div class="txt">
                                <b>{{ $j->periodoTexto() }} · {{ $j->rotuloTipo() }}</b>
                                <small>
                                    @if ($j->situacao === 'pendente')
                                        Enviada em {{ $j->created_at->format('d/m/Y') }}
                                    @elseif ($j->analisadoPor)
                                        {{ $j->situacao === 'abonada' ? 'Abonada' : 'Recusada' }} por {{ $j->analisadoPor->name }} em {{ $j->analisado_em->format('d/m/Y') }}{{ $j->motivo_decisao ? ': '.$j->motivo_decisao : '' }}
                                    @endif
                                    @if ($j->temArquivo()) · <a href="{{ route('justificativas.arquivo', $j) }}" target="_blank" rel="noopener">anexo</a>@endif
                                </small>
                            </div>
                            <span class="b {{ $j->selo()[1] }}">{{ $j->selo()[0] }}</span>
                            @if ($j->situacao === 'pendente')
                                <form method="POST" action="{{ route('justificativas.retirar', $j) }}" onsubmit="return confirm('Retirar esta justificativa? Você pode enviar outra depois.')">@csrf
                                    <button type="submit" class="btn btn-s btn-sm btn-icone" title="Retirar" aria-label="Retirar justificativa de {{ $j->periodoTexto() }}"><x-icone nome="fechar" tamanho="16" /></button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    @include('frequencia.partes.espelho', ['corrigir' => false, 'justificar' => true, 'fechamento' => $fechamento])
</x-layouts.app>
