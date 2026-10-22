<x-layouts.app titulo="Cadastrar servidor">
    <div>
        <a href="{{ route('servidores.index') }}" class="small" style="font-weight:600;display:inline-flex;gap:4px;align-items:center"><x-icone nome="voltar" tamanho="16" />Servidores</a>
        <h1 class="h1" style="margin-top:8px">Em qual órgão?</h1>
        <p class="sub">Vagas e lotações dependem do órgão. Escolha onde o servidor vai ser cadastrado.</p>
    </div>
    <div class="card card-flush">
        <div class="list" style="margin:0;padding:0 24px">
            @foreach ($orgaos as $o)
                <a class="li" href="{{ route('servidores.create', ['orgao' => $o->id]) }}" style="text-decoration:none;color:inherit">
                    <span class="chip c-navy"><x-icone nome="predio" /></span>
                    <div><b>{{ $o->sigla }}</b><div class="meta">{{ $o->nome }}</div></div>
                    <span class="r"><x-icone nome="seta" /></span>
                </a>
            @endforeach
        </div>
    </div>
</x-layouts.app>
