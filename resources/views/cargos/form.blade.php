<x-layouts.app :titulo="$cargo->exists ? 'Editar cargo' : 'Novo cargo'">
    <div>
        <a href="{{ $cargo->exists ? route('cargos.show', $cargo) : route('cargos.index') }}" class="small" style="font-weight:600;display:inline-flex;gap:4px;align-items:center"><x-icone nome="voltar" tamanho="16" />{{ $cargo->exists ? $cargo->denominacao : 'Cargos e vagas' }}</a>
        <h1 class="h1" style="margin-top:8px">{{ $cargo->exists ? 'Editar cargo' : 'Novo cargo' }}</h1>
        @unless ($cargo->exists)<p class="sub">O sistema cria as vagas numeradas de 1 até a quantidade informada, todas livres.</p>@endunless
    </div>

    <form method="POST" action="{{ $cargo->exists ? route('cargos.update', $cargo) : route('cargos.store') }}" class="card" novalidate>
        @csrf
        @if ($cargo->exists) @method('PUT') @endif
        <div class="form-grid">
            @if ($orgaos->isNotEmpty())
                <x-campo nome="orgao_id" rotulo="Órgão" obrigatorio>
                    <select id="campo-orgao_id" name="orgao_id" class="input">
                        @foreach ($orgaos as $o)
                            <option value="{{ $o->id }}" @selected((int) old('orgao_id') === $o->id)>{{ $o->sigla }} · {{ $o->nome }}</option>
                        @endforeach
                    </select>
                </x-campo>
            @endif
            <x-campo nome="denominacao" rotulo="Denominação" :valor="$cargo->denominacao" obrigatorio classe="full" ajuda="Como está na lei ou decreto. Ex.: Assessor Especial" />
            <x-campo nome="simbolo" rotulo="Símbolo (código padrão)" :valor="$cargo->simbolo" obrigatorio maxlength="30" ajuda="Ex.: CNES-IV" />
            <x-campo nome="natureza" rotulo="Natureza" obrigatorio>
                <select id="campo-natureza" name="natureza" class="input">
                    @foreach (\App\Models\Cargo::NATUREZAS as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected(old('natureza', $cargo->natureza) === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </x-campo>
            <x-campo nome="remuneracao" rotulo="Remuneração de referência (R$)" :valor="$cargo->remuneracao !== null ? number_format((float) $cargo->remuneracao, 2, ',', '.') : null" inputmode="decimal" placeholder="0,00" />
            <x-campo nome="quantidade_vagas" rotulo="Quantidade de vagas" tipo="number" min="1" max="999" :valor="$cargo->quantidade_vagas" obrigatorio ajuda="Total criado, incluindo as remanejadas." />
            <x-campo nome="base_legal" rotulo="Base legal" :valor="$cargo->base_legal" classe="full" placeholder="Lei nº 0.000, de 00/00/0000" />
            <x-campo nome="observacao" rotulo="Observação" classe="full">
                <textarea id="campo-observacao" name="observacao" class="input" rows="3" style="padding-top:10px">{{ old('observacao', $cargo->observacao) }}</textarea>
            </x-campo>
            <div class="campo full">
                <label class="check"><input type="checkbox" name="ativo" value="1" @checked(old('ativo', $cargo->ativo))> Cargo ativo</label>
            </div>
        </div>
        <div class="form-acoes">
            <a href="{{ $cargo->exists ? route('cargos.show', $cargo) : route('cargos.index') }}" class="btn btn-s">Cancelar</a>
            <button type="submit" class="btn btn-p">Salvar</button>
        </div>
    </form>
</x-layouts.app>
