<x-layouts.app :titulo="$orgao->exists ? 'Editar órgão' : 'Novo órgão'">
    <div>
        <a href="{{ route('orgaos.index') }}" class="small" style="font-weight:600;display:inline-flex;gap:4px;align-items:center"><x-icone nome="voltar" tamanho="16" />Órgãos</a>
        <h1 class="h1" style="margin-top:8px">{{ $orgao->exists ? 'Editar '.$orgao->sigla : 'Novo órgão' }}</h1>
    </div>

    <form method="POST" action="{{ $orgao->exists ? route('orgaos.update', $orgao) : route('orgaos.store') }}" class="card" novalidate>
        @csrf
        @if ($orgao->exists) @method('PUT') @endif
        <div class="form-grid">
            <x-campo nome="nome" rotulo="Nome do órgão" :valor="$orgao->nome" obrigatorio classe="full" />
            <x-campo nome="sigla" rotulo="Sigla" :valor="$orgao->sigla" obrigatorio maxlength="30" />
            <x-campo nome="cnpj" rotulo="CNPJ" :valor="$orgao->cnpj" placeholder="00.000.000/0000-00" />
            <x-campo nome="esfera" rotulo="Esfera" obrigatorio>
                <select id="campo-esfera" name="esfera" class="input">
                    @foreach (['estadual' => 'Estadual', 'municipal' => 'Municipal', 'federal' => 'Federal'] as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected(old('esfera', $orgao->esfera) === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </x-campo>
            <x-campo nome="uf" rotulo="UF" :valor="$orgao->uf" obrigatorio maxlength="2" />
            <x-campo nome="municipio" rotulo="Município" :valor="$orgao->municipio" obrigatorio />
            <div class="campo full">
                <label class="check"><input type="checkbox" name="ativo" value="1" @checked(old('ativo', $orgao->ativo))> Órgão ativo (desmarque para bloquear o acesso de todos os usuários dele)</label>
            </div>
        </div>
        <div class="form-acoes">
            <a href="{{ route('orgaos.index') }}" class="btn btn-s">Cancelar</a>
            <button type="submit" class="btn btn-p">Salvar</button>
        </div>
    </form>
</x-layouts.app>
