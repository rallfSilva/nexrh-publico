{{-- Espelho do ponto de um mês. Recebe $calculo (Espelho::calcular), $mes, $navegar (fn (Carbon) => url)
     e $corrigir (o RH pode anular marcações; o diálogo de anulação fica na página). --}}
@use('App\Frequencia\Espelho')
@use('App\Frequencia\Turnos')
@use('App\Models\RegistroPonto')
@php
    $t = $calculo['totais'];
    $semana = ['', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
    $corrigir ??= false;
    $saldo = $t['trabalhado'] - $t['esperado'];
@endphp
<section class="card card-flush espelho">
    <div class="escala-cab">
        <div>
            <h2>Espelho de {{ $mes->translatedFormat('F \d\e Y') }}</h2>
            <p class="hint">Hora do servidor do sistema. Férias, licenças e feriados entram sozinhos.</p>
        </div>
        <div class="btns nao-imprimir">
            <a class="btn btn-s btn-sm btn-icone" href="{{ $navegar($mes->copy()->subMonth()) }}" aria-label="Mês anterior" title="Mês anterior"><x-icone nome="voltar" tamanho="16" /></a>
            <a class="btn btn-s btn-sm" href="{{ $navegar(today()) }}">Mês atual</a>
            <a class="btn btn-s btn-sm btn-icone" href="{{ $navegar($mes->copy()->addMonth()) }}" aria-label="Próximo mês" title="Próximo mês"><x-icone nome="seta" tamanho="16" /></a>
            <button type="button" class="btn btn-s btn-sm" onclick="window.print()"><x-icone nome="download" tamanho="16" />Imprimir</button>
        </div>
    </div>

    <dl class="esp-totais">
        <div><dt>Dias de trabalho</dt><dd>{{ $t['dias_trabalho'] }}</dd></div>
        <div><dt>Com registro</dt><dd>{{ $t['trabalhados'] }}</dd></div>
        <div class="{{ $t['faltas'] ? 'ruim' : '' }}"><dt>Sem registro</dt><dd>{{ $t['faltas'] }}</dd></div>
        <div class="{{ $t['incompletos'] ? 'atencao' : '' }}"><dt>Incompletos</dt><dd>{{ $t['incompletos'] }}</dd></div>
        <div class="{{ $t['atencao'] ? 'atencao' : '' }}"><dt>A verificar</dt><dd>{{ $t['atencao'] }}</dd></div>
        <div><dt>Horas no mês</dt><dd>{{ Turnos::horas($t['trabalhado']) }} <small>de {{ Turnos::horas($t['esperado']) }}</small></dd></div>
        <div class="{{ $saldo < 0 ? 'ruim' : '' }}"><dt>Saldo</dt><dd>{{ $saldo > 0 ? '+' : '' }}{{ Turnos::horas($saldo) }}</dd></div>
    </dl>

    <div class="tbl-wrap">
        <table class="tbl tbl-esp">
            <thead>
                <tr><th scope="col">Dia</th><th scope="col">Marcações</th><th scope="col" class="num">Horas</th><th scope="col">Situação</th></tr>
            </thead>
            <tbody>
                @foreach ($calculo['dias'] as $d)
                    @php
                        [$rotulo, $selo] = Espelho::SITUACOES[$d['situacao']];
                        $apagado = in_array($d['situacao'], ['folga', 'feriado', 'facultativo', 'fora', 'futuro'], true) && $d['registros']->isEmpty();
                    @endphp
                    <tr class="{{ $apagado ? 'apagado' : '' }} {{ $d['data']->isToday() ? 'hoje' : '' }} sit-{{ $d['situacao'] }}">
                        <th scope="row" class="dia"><b>{{ $d['data']->format('d/m') }}</b> <span>{{ $semana[$d['data']->dayOfWeekIso] }}</span></th>
                        <td>
                            @if ($d['registros']->isEmpty())
                                <span class="muted">{{ $d['situacao'] === 'futuro' && $d['jornada']->registraPonto() && $d['esperado'] ? Turnos::texto($d['jornada']->turnos) : '' }}</span>
                            @else
                                <div class="marcas">
                                    @foreach ($d['registros'] as $r)
                                        @php
                                            $dica = RegistroPonto::ORIGENS[$r->origem].($r->registradoPor && $r->origem === 'rh' ? ' por '.$r->registradoPor->name : '').($r->motivo ? ': '.$r->motivo : '');
                                            if (! $r->valido()) {
                                                $dica = 'Anulada'.($r->anuladoPor ? ' por '.$r->anuladoPor->name : '').': '.$r->motivo_anulacao;
                                            }
                                        @endphp
                                        @if ($corrigir && $r->valido())
                                            <button type="button" class="marca origem-{{ $r->origem }}" title="{{ $dica }} · clique para anular" data-abrir-dialogo="dialogo-anular" data-acao="{{ route('frequencia.anular', [$r->servidor_id, $r->id]) }}" data-titulo="{{ $r->momento->format('d/m/Y \à\s H:i') }}">{{ $r->momento->format('H:i') }}@if ($r->origem !== 'sistema')<i aria-hidden="true">{{ $r->origem === 'rh' ? 'RH' : 'R' }}</i>@endif<span class="sr-only"> ({{ $dica }})</span></button>
                                        @else
                                            <span class="marca origem-{{ $r->origem }} {{ $r->valido() ? '' : 'anulada' }}" title="{{ $dica }}">{{ $r->momento->format('H:i') }}@if ($r->origem !== 'sistema')<i aria-hidden="true">{{ $r->origem === 'rh' ? 'RH' : 'R' }}</i>@endif<span class="sr-only"> ({{ $dica }})</span></span>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="num">
                            @if ($d['trabalhado'] || $d['esperado'] && ! in_array($d['situacao'], ['futuro', 'hoje'], true))
                                {{ Turnos::horas($d['trabalhado']) }}@if ($d['esperado'])<small class="muted"> / {{ Turnos::horas($d['esperado']) }}</small>@endif
                            @endif
                        </td>
                        <td>
                            @if ($rotulo !== '')<span class="b {{ $selo }}">{{ in_array($d['situacao'], ['feriado', 'facultativo'], true) ? $rotulo.': '.$d['titulo'] : (in_array($d['situacao'], ['ferias', 'afastamento'], true) ? $d['titulo'] : $rotulo) }}</span>@endif
                            @foreach ($d['alertas'] as $alerta)<div class="small" style="color:#9A3412;margin-top:3px">{{ $alerta }}</div>@endforeach
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="small muted esp-nota">Marcações com <b>RH</b> foram incluídas pelo RH; com <b>R</b>, vieram do relógio de ponto. Marcações riscadas foram anuladas, com motivo. Passe o mouse sobre a marcação para ver os detalhes.</p>
</section>
