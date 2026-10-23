@use('App\Frequencia\Turnos')
@use('App\Models\Feriado')
@php
    $manter = fn (array $extra = []) => array_filter([...($orgaos->isNotEmpty() ? ['orgao' => $orgao?->id] : []), ...$extra]);
@endphp
<x-layouts.app titulo="Regras de frequência">
    <div>
        <a href="{{ route('frequencia.index') }}" class="small" style="font-weight:600;display:inline-flex;gap:4px;align-items:center"><x-icone nome="voltar" tamanho="16" />Frequência</a>
        <h1 class="h1" style="margin-top:8px">Regras de frequência</h1>
        <p class="sub">Horário padrão, tolerância, de onde o ponto pode ser registrado e o calendário de feriados{{ $orgao ? ' do '.$orgao->sigla : '' }}. A jornada de cada servidor pode ser diferente: altere no espelho dele.</p>
    </div>

    @if ($orgaos->isNotEmpty())
        <form method="GET" class="card filtros">
            <x-lista nome="orgao" rotulo="Órgão" :opcoes="$orgaos->pluck('sigla', 'id')->all()" :valor="$orgao?->id" />
            <button type="submit" class="btn btn-s">Abrir</button>
        </form>
    @endif

    @if ($regra)
        @unless ($regra->exists)
            <div class="flash flash-aviso"><x-icone nome="alerta" /><span><b>Regras provisórias.</b> A norma de frequência do órgão ainda não foi confirmada. Quando chegar, ajuste os valores abaixo e salve.</span></div>
        @endunless

        <div class="g2">
            <form method="POST" action="{{ route('frequencia.regras.update', $manter()) }}" class="card" novalidate>
                @csrf
                @method('PUT')
                <h2>Horário e tolerância</h2>
                <p class="hint">O horário padrão sai da carga horária da ficha de cada servidor.</p>
                <div class="form-grid" style="margin-top:14px">
                    <x-campo nome="turnos_6h" rotulo="Horário de quem faz 6h diárias" :valor="Turnos::campo($regra->turnos_6h)" maxlength="60" obrigatorio ajuda="Ex.: 07:30-13:30" />
                    <x-campo nome="turnos_8h" rotulo="Horário de quem faz 8h diárias" :valor="Turnos::campo($regra->turnos_8h)" maxlength="60" obrigatorio ajuda="Ex.: 08:00-12:00, 14:00-18:00" />
                    <x-campo nome="tolerancia_minutos" rotulo="Tolerância (minutos)" tipo="number" min="0" max="60" :valor="$regra->tolerancia_minutos" obrigatorio ajuda="Atraso ou saída antecipada até esse limite não aparece para verificar." />
                    <x-campo nome="dia_fechamento" rotulo="Prazo para fechar o mês (dia)" tipo="number" min="1" max="28" :valor="$regra->dia_fechamento" obrigatorio ajuda="Dia do mês seguinte. Se cair em fim de semana ou feriado, vale o dia útil seguinte." />
                    <x-campo nome="fundamento" rotulo="Fundamento" :valor="$regra->fundamento" maxlength="255" ajuda="Ex.: decreto ou portaria que define o expediente." />
                </div>
                <h2 style="margin-top:22px">De onde registrar</h2>
                <label class="check" style="margin-top:10px"><input type="hidden" name="exigir_rede" value="0"><input type="checkbox" name="exigir_rede" value="1" @checked(old('exigir_rede', $regra->exigir_rede))> Só aceitar o registro de dentro da rede do órgão</label>
                <x-campo nome="redes_permitidas" rotulo="Redes do órgão" :valor="$regra->redes_permitidas" maxlength="500" ajuda="Endereços ou faixas separados por vírgula, ex.: 10.20.0.0/16, 200.10.5.7. Peça à TI a faixa da rede da SELC." />
                <p class="small muted" style="margin:6px 0 0">Você está acessando de <b>{{ $ip }}</b>: {{ $regra->redePermite($ip) ? 'dentro das redes acima.' : 'fora das redes acima.' }}</p>
                <h2 style="margin-top:22px">Cargos dispensados do ponto</h2>
                <p class="hint">Quem ocupa estes cargos não registra o ponto. Para exigir de alguém em particular, altere a jornada no espelho da pessoa.</p>
                @php
                    $marcados = array_map('intval', old('cargos_dispensados', $dispensados));
                @endphp
                <div class="cargos-dispensa">
                    @forelse ($cargos->sortBy(fn ($c) => [! in_array($c->id, $marcados, true), $c->denominacao]) as $c)
                        <label class="check"><input type="checkbox" name="cargos_dispensados[]" value="{{ $c->id }}" @checked(in_array($c->id, $marcados, true))> {{ $c->denominacao }}</label>
                    @empty
                        <p class="small muted" style="margin:0">Nenhum cargo cadastrado no quadro.</p>
                    @endforelse
                </div>
                @error('cargos_dispensados.*')<span class="erro">{{ $message }}</span>@enderror
                <div class="nota"><x-icone nome="auditoria" tamanho="18" /><span>Cada alteração fica na trilha de auditoria, com o valor antigo e o novo.</span></div>
                <div class="form-acoes">
                    <button type="submit" class="btn btn-p"><x-icone nome="ok" tamanho="18" />Salvar regras</button>
                </div>
            </form>

            <section class="card">
                <div class="row">
                    <div>
                        <h2>Feriados e pontos facultativos de {{ $ano }}</h2>
                        <p class="hint">Não contam como dia de trabalho no espelho.</p>
                    </div>
                    <div class="btns">
                        <a class="btn btn-s btn-sm btn-icone" href="{{ route('frequencia.regras', $manter(['ano' => $ano - 1])) }}" aria-label="Ano anterior" title="Ano anterior"><x-icone nome="voltar" tamanho="16" /></a>
                        <a class="btn btn-s btn-sm btn-icone" href="{{ route('frequencia.regras', $manter(['ano' => $ano + 1])) }}" aria-label="Próximo ano" title="Próximo ano"><x-icone nome="seta" tamanho="16" /></a>
                    </div>
                </div>

                @if ($feriados->isEmpty())
                    <div class="vazio" style="padding:24px 8px"><b>Nenhum feriado em {{ $ano }}.</b>Inclua os nacionais e de Roraima de uma vez e depois ajuste.</div>
                @else
                    <div class="list">
                        @foreach ($feriados as $f)
                            <div class="li">
                                <span class="chip {{ $f->tipo === 'feriado' ? 'c-navy' : 'c-teal' }}"><x-icone nome="calendario" tamanho="16" /></span>
                                <div><b>{{ $f->data->format('d/m') }} · {{ $f->nome }}</b><div class="meta">{{ Feriado::TIPOS[$f->tipo] }} · {{ $f->data->translatedFormat('l') }}</div></div>
                                <form method="POST" action="{{ route('frequencia.feriados.destroy', [$f, ...$manter()]) }}" class="r">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-s btn-sm btn-icone" aria-label="Retirar {{ $f->nome }}" title="Retirar do calendário"><x-icone nome="lixeira" tamanho="16" /></button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('frequencia.feriados.sugeridos', $manter()) }}" style="margin-top:14px">
                    @csrf
                    <input type="hidden" name="ano" value="{{ $ano }}">
                    <button type="submit" class="btn btn-s" title="Inclui os feriados nacionais e de Roraima de {{ $ano }}; os que já existem ficam como estão."><x-icone nome="calendario" tamanho="18" />Incluir feriados nacionais e de RR</button>
                </form>

                <form method="POST" action="{{ route('frequencia.feriados.store', $manter()) }}" class="feriado-novo" novalidate>
                    @csrf
                    <h3>Outro feriado ou ponto facultativo</h3>
                    <div class="form-grid">
                        <x-campo nome="data" rotulo="Dia" tipo="date" obrigatorio />
                        <x-lista nome="tipo" rotulo="Tipo" :opcoes="Feriado::TIPOS" valor="ponto_facultativo" vazio="Escolha" obrigatorio />
                    </div>
                    <x-campo nome="nome" rotulo="Nome" maxlength="120" obrigatorio placeholder="Ex.: ponto facultativo (Decreto nº)" />
                    <div class="form-acoes"><button type="submit" class="btn btn-s"><x-icone nome="mais" tamanho="18" />Incluir</button></div>
                </form>
            </section>
        </div>
    @endif
</x-layouts.app>
