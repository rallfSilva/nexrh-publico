<x-layouts.app titulo="Editar vaga">
    <div>
        <a href="{{ route('cargos.show', $vaga->cargo) }}" class="small" style="font-weight:600;display:inline-flex;gap:4px;align-items:center"><x-icone nome="voltar" tamanho="16" />{{ $vaga->cargo->denominacao }}</a>
        <h1 class="h1" style="margin-top:8px">Vaga nº {{ $vaga->numero }}</h1>
        <p class="sub">{{ $vaga->cargo->denominacao }} · {{ $vaga->cargo->simbolo }}. Na Entrega 3 o ocupante passa a vir do cadastro de servidores.</p>
    </div>

    <form method="POST" action="{{ route('vagas.update', $vaga) }}" class="card" novalidate>
        @csrf @method('PUT')
        <div class="form-grid">
            <x-campo nome="situacao" rotulo="Situação" obrigatorio>
                <select id="campo-situacao" name="situacao" class="input">
                    @foreach (\App\Models\Vaga::SITUACOES as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected(old('situacao', $vaga->situacao) === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </x-campo>
            <x-campo nome="orgao_remanejamento" rotulo="Órgão de destino" :valor="$vaga->orgao_remanejamento" ajuda="Só para vaga remanejada ou devolvida." />
            <x-campo nome="ocupante_nome" rotulo="Nome do ocupante" :valor="$vaga->ocupante_nome" ajuda="Só para vaga ocupada." autocomplete="off" />
            <x-campo nome="ocupante_cpf" rotulo="CPF do ocupante" :valor="$vaga->ocupante_cpf" inputmode="numeric" placeholder="000.000.000-00" autocomplete="off" ajuda="Guardado criptografado. Nas listas aparece com máscara." />
            <x-campo nome="ato_numero" rotulo="Ato (decreto ou portaria)" :valor="$vaga->ato_numero" placeholder="Ex.: 1388-P" />
            <x-campo nome="ato_data" rotulo="Data do ato" tipo="date" :valor="$vaga->ato_data?->format('Y-m-d')" />
            <x-campo nome="efeito_data" rotulo="Efeito a partir de" tipo="date" :valor="$vaga->efeito_data?->format('Y-m-d')" />
            <x-campo nome="base_legal" rotulo="Base legal da vaga" :valor="$vaga->base_legal" />
            <x-campo nome="observacao" rotulo="Observação" classe="full">
                <textarea id="campo-observacao" name="observacao" class="input" rows="2" style="padding-top:10px">{{ old('observacao', $vaga->observacao) }}</textarea>
            </x-campo>
        </div>
        <div class="form-acoes">
            <a href="{{ route('cargos.show', $vaga->cargo) }}" class="btn btn-s">Cancelar</a>
            <button type="submit" class="btn btn-p">Salvar</button>
        </div>
    </form>
</x-layouts.app>
