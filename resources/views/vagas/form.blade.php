<x-layouts.app titulo="Editar vaga">
    <div>
        <a href="{{ route('cargos.show', $vaga->cargo) }}" class="small" style="font-weight:600;display:inline-flex;gap:4px;align-items:center"><x-icone nome="voltar" tamanho="16" />{{ $vaga->cargo->denominacao }}</a>
        <h1 class="h1" style="margin-top:8px">Vaga nº {{ $vaga->numero }}</h1>
        <p class="sub">{{ $vaga->cargo->denominacao }} · {{ $vaga->cargo->simbolo }}.</p>
    </div>

    @if ($vaga->servidor)
        <div class="flash flash-aviso"><x-icone nome="servidor" style="flex:none" /><div>Esta vaga está com <a href="{{ route('servidores.show', $vaga->servidor) }}"><b>{{ $vaga->servidor->nome }}</b></a>, cadastrado em Servidores. Situação, ocupante e ato vêm da ficha dele: para mudar, edite o cadastro ou registre o desligamento. Aqui dá para alterar só a base legal e a observação.</div></div>
    @endif

    <form method="POST" action="{{ route('vagas.update', $vaga) }}" class="card" novalidate>
        @csrf @method('PUT')
        <div class="form-grid">
            <x-campo nome="situacao" rotulo="Situação" obrigatorio>
                <select id="campo-situacao" name="situacao" class="input" @disabled($vaga->servidor)>
                    @foreach (\App\Models\Vaga::SITUACOES as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected(old('situacao', $vaga->situacao) === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </x-campo>
            <x-campo nome="orgao_remanejamento" :disabled="(bool) $vaga->servidor" rotulo="Órgão de destino" :valor="$vaga->orgao_remanejamento" ajuda="Só para vaga remanejada ou devolvida." />
            <x-campo nome="ocupante_nome" :disabled="(bool) $vaga->servidor" rotulo="Nome do ocupante" :valor="$vaga->ocupante_nome" ajuda="Só para vaga ocupada." autocomplete="off" />
            <x-campo nome="ocupante_cpf" :disabled="(bool) $vaga->servidor" rotulo="CPF do ocupante" :valor="$vaga->ocupante_cpf" inputmode="numeric" placeholder="000.000.000-00" autocomplete="off" ajuda="Guardado criptografado. Nas listas aparece com máscara." />
            <x-campo nome="ato_numero" :disabled="(bool) $vaga->servidor" rotulo="Ato (decreto ou portaria)" :valor="$vaga->ato_numero" placeholder="Ex.: 1388-P" />
            <x-campo nome="ato_data" :disabled="(bool) $vaga->servidor" rotulo="Data do ato" tipo="date" :valor="$vaga->ato_data?->format('Y-m-d')" />
            <x-campo nome="efeito_data" :disabled="(bool) $vaga->servidor" rotulo="Efeito a partir de" tipo="date" :valor="$vaga->efeito_data?->format('Y-m-d')" />
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
