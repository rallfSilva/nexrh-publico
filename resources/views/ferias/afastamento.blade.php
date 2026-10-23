<x-layouts.app titulo="Registrar afastamento">
    <div>
        <a href="{{ route('servidores.show', [$servidor, 'aba' => 'ferias']) }}" class="small" style="font-weight:600;display:inline-flex;gap:4px;align-items:center"><x-icone nome="voltar" tamanho="16" />{{ $servidor->nome }}</a>
        <h1 class="h1" style="margin-top:8px">Registrar licença ou afastamento</h1>
        <p class="sub">{{ $servidor->nome }}{{ $servidor->unidade ? ' · '.$servidor->unidade->sigla : '' }}. O afastamento tira a pessoa do setor e entra na conta do mínimo de presentes nas férias dos colegas.</p>
    </div>

    <form method="POST" action="{{ route('afastamentos.store', $servidor) }}" class="card" novalidate>
        @csrf
        <div class="form-grid">
            <x-lista nome="tipo" rotulo="Tipo" :opcoes="array_combine(\App\Models\Afastamento::TIPOS, \App\Models\Afastamento::TIPOS)" obrigatorio classe="full" />
            <x-campo nome="inicio" rotulo="Primeiro dia" tipo="date" obrigatorio />
            <x-campo nome="fim" rotulo="Último dia" tipo="date" obrigatorio />
            <x-campo nome="documento" rotulo="Ato ou documento" maxlength="120" placeholder="Ex.: Portaria nº 112/2026 ou atestado" />
            <x-campo nome="processo_sei" rotulo="Processo SEI" maxlength="40" placeholder="Opcional" />
            <x-campo nome="observacao" rotulo="Observação" maxlength="255" classe="full" placeholder="Opcional. Não escreva diagnóstico nem CID." />
        </div>

        <div class="nota"><x-icone nome="cadeado" tamanho="18" /><span>Não informe diagnóstico nem CID. O atestado ou laudo vai para o dossiê, na categoria Saúde, com acesso restrito. O tipo do afastamento aparece só para o RH e a administração; a chefia e o auditor veem "Licença ou afastamento".</span></div>
        <div class="form-acoes">
            <a href="{{ route('servidores.show', [$servidor, 'aba' => 'ferias']) }}" class="btn btn-s">Cancelar</a>
            <button type="submit" class="btn btn-p"><x-icone nome="ok" tamanho="18" />Registrar</button>
        </div>
    </form>
</x-layouts.app>
