@php($servidores = $tipo === 'servidores')
<x-layouts.app :titulo="$servidores ? 'Importar servidores' : 'Importar quadro de cargos'">
    <div>
        <a href="{{ $servidores ? route('servidores.index') : route('cargos.index') }}" class="small" style="font-weight:600;display:inline-flex;gap:4px;align-items:center"><x-icone nome="voltar" tamanho="16" />{{ $servidores ? 'Servidores' : 'Cargos e vagas' }}</a>
        <h1 class="h1" style="margin-top:8px">{{ $servidores ? 'Importar servidores' : 'Importar quadro de cargos' }}</h1>
        <p class="sub">Envie a planilha. O sistema lê, confere cada linha e mostra uma prévia com as divergências. Nada muda até você confirmar.</p>
    </div>

    @if (count($tipos) > 1)
        <nav class="abas-lista card" style="padding:6px 16px 0;border-radius:14px" aria-label="O que importar">
            <a class="aba" href="{{ route('importacoes.create', ['tipo' => 'cargos']) }}" aria-selected="{{ $servidores ? 'false' : 'true' }}"><x-icone nome="cargos" tamanho="18" />Quadro de cargos</a>
            <a class="aba" href="{{ route('importacoes.create', ['tipo' => 'servidores']) }}" aria-selected="{{ $servidores ? 'true' : 'false' }}"><x-icone nome="servidor" tamanho="18" />Servidores</a>
        </nav>
    @endif

    <div class="g2">
        <form method="POST" action="{{ route('importacoes.store') }}" enctype="multipart/form-data" class="card" style="display:flex;flex-direction:column;gap:18px">
            @csrf
            <input type="hidden" name="tipo" value="{{ $tipo }}">
            <h2>Planilha</h2>
            @if ($orgaos->isNotEmpty())
                <x-campo nome="orgao_id" rotulo="Órgão" obrigatorio>
                    <select id="campo-orgao_id" name="orgao_id" class="input">
                        @foreach ($orgaos as $o)
                            <option value="{{ $o->id }}" @selected((int) old('orgao_id') === $o->id)>{{ $o->sigla }} · {{ $o->nome }}</option>
                        @endforeach
                    </select>
                </x-campo>
            @endif
            <x-campo nome="arquivo" rotulo="Arquivo .xlsx (até 10 MB)" obrigatorio>
                <input id="campo-arquivo" name="arquivo" type="file" accept=".xlsx" class="input" style="padding-top:9px" required>
            </x-campo>
            <div><button type="submit" class="btn btn-p"><x-icone nome="planilha" />Analisar planilha</button></div>
        </form>

        <div class="card">
            <h2>Formatos aceitos</h2>
            @if ($servidores)
                <div class="list">
                    <div class="li"><span class="chip c-navy"><x-icone nome="planilha" /></span><div><b>Modelo 03 · Servidores</b><div class="meta">Aba Servidores com os campos da ficha da SEGAD, mais as abas Parentesco, Acumulação e Bens. <a href="{{ route('modelos.baixar', '03_modelo_servidores.xlsx') }}">Baixar modelo</a></div></div></div>
                    <div class="li"><span class="chip c-teal"><x-icone nome="cargos" /></span><div><b>Ligação com o quadro</b><div class="meta">O cargo e o símbolo da planilha indicam o cargo. O servidor fica na vaga em que o quadro já mostra o CPF dele ou, se não houver, na primeira vaga livre do cargo.</div></div></div>
                    <div class="li"><span class="chip c-warn"><x-icone nome="alerta" /></span><div><b>Linhas com erro ficam de fora</b><div class="meta">CPF inválido, campo obrigatório vazio ou lotação desconhecida: a linha aparece no relatório e não é importada. As outras seguem.</div></div></div>
                </div>
                <p class="muted small" style="margin:14px 0 0">Para testar sem dados reais, use o <a href="{{ route('modelos.baixar', 'exemplo_servidores_ficticio.xlsx') }}">exemplo de servidores fictícios</a>.</p>
            @else
                <div class="list">
                    <div class="li"><span class="chip c-teal"><x-icone nome="planilha" /></span><div><b>Planilha de estrutura de cargos da SELC</b><div class="meta">A planilha de controle usada hoje, com um bloco por cargo e uma linha por vaga. O sistema confere o cabeçalho de cada cargo e o resumo do final.</div></div></div>
                    <div class="li"><span class="chip c-navy"><x-icone nome="planilha" /></span><div><b>Modelo padrão do NexRH</b><div class="meta">Abas Cargos e Remanejamentos. <a href="{{ route('modelos.baixar', '02_modelo_cargos_e_vagas.xlsx') }}">Baixar modelo</a></div></div></div>
                </div>
                <p class="muted small" style="margin:14px 0 0">Para testar sem dados reais, use a <a href="{{ route('modelos.baixar', 'exemplo_estrutura_cargos_ficticia.xlsx') }}">cópia fictícia da planilha de estrutura</a>: mesma estrutura de junho/2026, com nomes e CPFs inventados.</p>
            @endif
        </div>
    </div>
</x-layouts.app>
