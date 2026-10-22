<x-layouts.app titulo="Modelos de planilha">
    <div>
        <h1 class="h1">Modelos de planilha</h1>
        <p class="sub">Planilhas padrão para preencher os dados fora do sistema e importar depois. Cada uma tem uma aba de instruções, a lista dos campos obrigatórios e um exemplo com dados fictícios.</p>
    </div>

    <div class="g2">
        @foreach ($modelos as $arquivo => [$titulo, $descricao])
            <div class="card" style="display:flex;gap:16px;align-items:flex-start">
                <span class="chip c-teal" style="width:44px;height:44px;border-radius:12px"><x-icone nome="planilha" tamanho="22" /></span>
                <div style="flex:1;min-width:0">
                    <h2>{{ $titulo }}</h2>
                    <p class="hint">{{ $descricao }}</p>
                    <p class="muted small" style="margin:8px 0 0">{{ $arquivo }}</p>
                </div>
                <a href="{{ route('modelos.baixar', $arquivo) }}" class="btn btn-s btn-sm"><x-icone nome="download" tamanho="16" />Baixar</a>
            </div>
        @endforeach
    </div>

    <div class="flash flash-aviso"><x-icone nome="alerta" style="flex:none" /><div>A importação das planilhas entra junto com cada módulo: cargos na Entrega 2, servidores na 3, dependentes na 4 e férias na 5.</div></div>
</x-layouts.app>
