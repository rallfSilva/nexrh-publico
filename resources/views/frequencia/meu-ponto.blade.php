@use('App\Frequencia\Marcador')
@php
    $marcasHoje = $hoje ? $hoje['registros']->filter->valido() : collect();
    $acao = Marcador::proximaAcao($marcasHoje->count());
    $navegar = fn ($m) => route('meu-ponto', ['mes' => $m->format('Y-m')]);
@endphp
<x-layouts.app titulo="Meu ponto">
    <div>
        <h1 class="h1">Meu ponto</h1>
        <p class="sub">Registre a entrada e a saída e confira o espelho do mês. Se esquecer uma marcação, avise a chefia ou o RH.</p>
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

    @include('frequencia.partes.espelho', ['corrigir' => false])
</x-layouts.app>
