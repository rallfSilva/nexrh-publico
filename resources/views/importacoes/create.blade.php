<x-layouts.app titulo="Importar quadro de cargos">
    <div>
        <a href="{{ route('cargos.index') }}" class="small" style="font-weight:600;display:inline-flex;gap:4px;align-items:center"><x-icone nome="voltar" tamanho="16" />Cargos e vagas</a>
        <h1 class="h1" style="margin-top:8px">Importar quadro de cargos</h1>
        <p class="sub">Envie a planilha. O sistema lê, confere os números e mostra uma prévia com as divergências. Nada muda no quadro até você confirmar.</p>
    </div>

    <div class="g2">
        <form method="POST" action="{{ route('importacoes.store') }}" enctype="multipart/form-data" class="card" style="display:flex;flex-direction:column;gap:18px">
            @csrf
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
            <div class="list">
                <div class="li"><span class="chip c-teal"><x-icone nome="planilha" /></span><div><b>Planilha de estrutura de cargos da SELC</b><div class="meta">A planilha de controle usada hoje, com um bloco por cargo e uma linha por vaga. O sistema confere o cabeçalho de cada cargo e o resumo do final.</div></div></div>
                <div class="li"><span class="chip c-navy"><x-icone nome="planilha" /></span><div><b>Modelo padrão do NexRH</b><div class="meta">Abas Cargos e Remanejamentos. <a href="{{ route('modelos.baixar', '02_modelo_cargos_e_vagas.xlsx') }}">Baixar modelo</a></div></div></div>
            </div>
            <p class="muted small" style="margin:14px 0 0">Para testar sem dados reais, use a <a href="{{ route('modelos.baixar', 'exemplo_estrutura_cargos_ficticia.xlsx') }}">cópia fictícia da planilha de estrutura</a>: mesma estrutura de junho/2026, com nomes e CPFs inventados.</p>
        </div>
    </div>
</x-layouts.app>
