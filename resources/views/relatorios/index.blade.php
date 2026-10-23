<x-layouts.app titulo="Relatórios">
    <div class="row">
        <div>
            <h1 class="h1">Relatórios</h1>
            <p class="sub">Escolha o relatório, filtre e baixe em Excel ou PDF. O CPF sai sempre mascarado e cada exportação fica na trilha de auditoria.</p>
        </div>
    </div>

    <div class="relatorios">
        @foreach ($relatorios as $r)
            <a href="{{ route('relatorios.show', $r->chave()) }}" class="card relatorio-op">
                <span class="chip c-navy"><x-icone :nome="$r->icone()" /></span>
                <span class="txt"><b>{{ $r->titulo() }}</b><small>{{ $r->descricao() }}</small></span>
                <x-icone nome="seta" tamanho="18" />
            </a>
        @endforeach
    </div>
</x-layouts.app>
