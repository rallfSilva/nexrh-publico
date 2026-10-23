@php
    $voltar = $propria ? route('minha-ficha', ['aba' => 'ferias']) : route('servidores.show', [$servidor, 'aba' => 'ferias']);
    $titulo = $propria ? 'Pedir férias' : 'Marcar férias';
    $periodoAtual = (int) old('periodo_aquisitivo_id', $escolhido?->id);
@endphp
<x-layouts.app :titulo="$titulo">
    <div>
        <a href="{{ $voltar }}" class="small" style="font-weight:600;display:inline-flex;gap:4px;align-items:center"><x-icone nome="voltar" tamanho="16" />{{ $propria ? 'Minha ficha' : $servidor->nome }}</a>
        <h1 class="h1" style="margin-top:8px">{{ $titulo }}</h1>
        <p class="sub">
            @if ($propria)
                O pedido vai para {{ $servidor->unidade?->chefe ? $servidor->unidade->chefe->name.' (chefia de '.$servidor->unidade->sigla.')' : 'o RH' }}. Até ser aprovado, você pode retirá-lo.
            @else
                {{ $servidor->nome }}{{ $servidor->unidade ? ' · '.$servidor->unidade->sigla : '' }}. Marcadas pelo RH, as férias já entram aprovadas.
            @endif
        </p>
    </div>

    @if ($periodos->isEmpty())
        <div class="card"><div class="vazio"><b>Sem saldo de férias.</b>Todos os períodos aquisitivos já estão marcados.</div></div>
    @else
        <form method="POST" action="{{ $propria ? route('minha-ficha.ferias.store') : route('ferias.store', $servidor) }}" class="card" novalidate
            data-ferias-form data-sugestoes-url="{{ $propria ? route('minha-ficha.ferias.sugestoes') : route('ferias.sugestoes', $servidor) }}">
            @csrf

            <p class="grupo">Período aquisitivo</p>
            <div class="periodos-escolha" role="radiogroup" aria-label="Período aquisitivo">
                @foreach ($periodos as $p)
                    @php($liberado = $p->liberadoEm())
                    <label class="periodo-op">
                        <input type="radio" name="periodo_aquisitivo_id" value="{{ $p->id }}" data-saldo="{{ $p->saldo() }}" @checked($periodoAtual === $p->id) required>
                        <span>
                            <b>{{ $p->rotulo() }}</b>
                            <small>{{ $p->inicio->format('d/m/Y') }} a {{ $p->fim->format('d/m/Y') }}</small>
                            <small><em>{{ $p->saldo() }} dias de saldo</em>{{ $liberado->gt(today()) ? ' · a partir de '.$liberado->format('d/m/Y') : '' }}{{ $p->etapasMarcadas() ? ' · '.$p->etapasMarcadas().' etapa(s) marcada(s)' : '' }}</small>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('periodo_aquisitivo_id')<p class="erro small" style="margin:8px 0 0;color:#9F1D1D;font-weight:600">{{ $message }}</p>@enderror

            <p class="grupo" style="margin-top:24px">Datas</p>
            <div class="form-grid">
                <x-campo nome="dias" rotulo="Quantos dias" tipo="number" :valor="$dias" obrigatorio min="1" max="90" inputmode="numeric"
                    :ajuda="'Até '.$regra->max_etapas.' etapas por período, cada uma com pelo menos '.$regra->min_dias_etapa.' dias (ou o saldo que resta).'" />
                <x-campo nome="inicio" rotulo="Primeiro dia de férias" tipo="date" :valor="old('inicio')" obrigatorio
                    :min="$propria ? today()->addDays($regra->antecedencia_dias)->format('Y-m-d') : null"
                    :ajuda="$propria ? 'Com pelo menos '.$regra->antecedencia_dias.' dias de antecedência.' : null" />
            </div>
            <p class="small muted" data-fim-ferias style="margin:10px 0 0" aria-live="polite"></p>

            <div class="sugestoes" data-sugestoes-caixa>
                <div class="cab"><x-icone nome="calendario" tamanho="18" /><b>Datas livres no setor{{ $servidor->unidade ? ' '.$servidor->unidade->sigla : '' }}</b></div>
                <p class="small muted" style="margin:4px 0 10px">Começam de preferência numa segunda-feira, não coincidem com outros afastamentos e mantêm o mínimo de {{ $servidor->unidade?->minimo_presentes_ferias ?? 0 }} pessoa(s) no setor. Clique para usar.</p>
                <div class="chips" data-sugestoes>
                    @forelse ($sugestoes as $sug)
                        <button type="button" class="chip-data" data-inicio="{{ $sug['inicio'] }}">{{ $sug['texto'] }}</button>
                    @empty
                        <span class="small muted">Nenhuma data livre encontrada para esse número de dias.</span>
                    @endforelse
                </div>
            </div>

            @unless ($propria)
                <p class="grupo" style="margin-top:24px">Registro</p>
                <div class="form-grid">
                    <x-campo nome="processo_sei" rotulo="Processo SEI" :valor="old('processo_sei')" maxlength="40" placeholder="Opcional" />
                    <x-campo nome="observacao" rotulo="Observação" :valor="old('observacao')" maxlength="255" placeholder="Opcional" />
                </div>
            @endunless

            <div class="nota"><x-icone nome="escudo" tamanho="18" /><span>O sistema confere o saldo e as regras do órgão, e não aceita datas que coincidam com outras férias ou afastamentos{{ $propria ? '' : ' do servidor' }} nem que deixem o setor abaixo do mínimo de pessoas.@unless ($regra->exists) As regras atuais são provisórias, a confirmar com a norma do órgão.@endunless</span></div>
            <div class="form-acoes">
                <a href="{{ $voltar }}" class="btn btn-s">Cancelar</a>
                <button type="submit" class="btn btn-p"><x-icone nome="ok" tamanho="18" />{{ $propria ? 'Enviar pedido' : 'Marcar férias' }}</button>
            </div>
        </form>
    @endif
</x-layouts.app>
