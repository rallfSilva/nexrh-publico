<x-layouts.app titulo="Regras de férias">
    <div>
        <a href="{{ route('ferias.escala') }}" class="small" style="font-weight:600;display:inline-flex;gap:4px;align-items:center"><x-icone nome="voltar" tamanho="16" />Escala de férias</a>
        <h1 class="h1" style="margin-top:8px">Regras de férias</h1>
        <p class="sub">Valem para todos os pedidos e marcações do órgão{{ $orgao ? ' '.$orgao->sigla : '' }}. O mínimo de pessoas presentes é definido em cada unidade, no organograma.</p>
    </div>

    @if ($orgaos->isNotEmpty())
        <form method="GET" class="card filtros">
            <x-lista nome="orgao" rotulo="Órgão" :opcoes="$orgaos->pluck('sigla', 'id')->all()" :valor="$orgao?->id" />
            <button type="submit" class="btn btn-s">Abrir</button>
        </form>
    @endif

    @if ($regra)
        @unless ($regra->exists)
            <div class="flash flash-aviso"><x-icone nome="alerta" /><span><b>Regras provisórias.</b> A norma de férias do órgão ainda não foi confirmada. Quando chegar, ajuste os valores abaixo e salve.</span></div>
        @endunless

        <form method="POST" action="{{ route('ferias.regras.update', $orgaos->isNotEmpty() ? ['orgao' => $orgao->id] : []) }}" class="card" novalidate>
            @csrf
            @method('PUT')
            <div class="form-grid">
                <x-campo nome="dias_por_periodo" rotulo="Dias de férias por período de 12 meses" tipo="number" min="1" max="60" :valor="$regra->dias_por_periodo" obrigatorio ajuda="Vale para os períodos que começarem depois de salvar." />
                <x-campo nome="max_etapas" rotulo="Em até quantas etapas" tipo="number" min="1" max="6" :valor="$regra->max_etapas" obrigatorio ajuda="Parcelamento das férias de um mesmo período." />
                <x-campo nome="min_dias_etapa" rotulo="Mínimo de dias por etapa" tipo="number" min="1" :valor="$regra->min_dias_etapa" obrigatorio ajuda="A última etapa pode ter menos, se for o saldo que resta." />
                <x-campo nome="max_periodos_acumulados" rotulo="Máximo de períodos acumulados" tipo="number" min="1" max="5" :valor="$regra->max_periodos_acumulados" obrigatorio ajuda="Acima disso, a ficha mostra a pendência." />
                <x-campo nome="antecedencia_dias" rotulo="Antecedência do pedido (dias)" tipo="number" min="0" max="180" :valor="$regra->antecedencia_dias" obrigatorio ajuda="Para o pedido feito pelo servidor. O RH pode marcar datas mais próximas." />
                <x-campo nome="fundamento" rotulo="Fundamento" :valor="$regra->fundamento" maxlength="255" ajuda="Ex.: lei, decreto ou portaria que define as regras." />
            </div>
            <div class="nota"><x-icone nome="auditoria" tamanho="18" /><span>Cada alteração fica na trilha de auditoria, com o valor antigo e o novo. As férias já marcadas não mudam.</span></div>
            <div class="form-acoes">
                <a href="{{ route('ferias.escala') }}" class="btn btn-s">Cancelar</a>
                <button type="submit" class="btn btn-p"><x-icone nome="ok" tamanho="18" />Salvar regras</button>
            </div>
        </form>
    @endif
</x-layouts.app>
