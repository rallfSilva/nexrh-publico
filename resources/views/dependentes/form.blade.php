@php
    use App\Models\Dependente;
    use App\Support\FichaSegad;
    $d = $dependente;
    $novo = ! $d->exists;
    $parentescos = array_combine(\App\Servidores\RegrasDependente::codigosParentesco(), FichaSegad::PARENTESCOS);
    $filhos = implode(',', Dependente::PARENTESCOS_FILHO);
@endphp
<x-layouts.app :titulo="$novo ? 'Novo dependente' : 'Editar dependente'">
    <div>
        <a href="{{ route('servidores.show', [$servidor, 'aba' => 'dependentes']) }}" class="small" style="font-weight:600;display:inline-flex;gap:4px;align-items:center"><x-icone nome="voltar" tamanho="16" />{{ $servidor->nome }}</a>
        <h1 class="h1" style="margin-top:8px">{{ $novo ? 'Novo dependente' : $d->nome }}</h1>
        <p class="sub">Dependente de {{ $servidor->nome }}. O parentesco segue os códigos da ficha cadastral (tabela do eSocial).</p>
    </div>

    <form method="POST" action="{{ $novo ? route('dependentes.store', $servidor) : route('dependentes.update', [$servidor, $d]) }}" class="card" novalidate>
        @csrf
        @unless ($novo) @method('PUT') @endunless

        <p class="grupo">Identificação</p>
        <div class="form-grid">
            <x-campo nome="nome" rotulo="Nome completo" :valor="$d->nome" obrigatorio classe="full" maxlength="160" autocomplete="off" />
            <x-campo nome="cpf" rotulo="CPF" :valor="$d->cpfFormatado()" obrigatorio data-mascara="cpf" inputmode="numeric" autocomplete="off" ajuda="Obrigatório para dependentes de qualquer idade." />
            <x-campo nome="data_nascimento" rotulo="Data de nascimento" tipo="date" :valor="$d->data_nascimento?->format('Y-m-d')" obrigatorio :max="now()->format('Y-m-d')" />
            <x-escolha nome="sexo" rotulo="Sexo" :opcoes="FichaSegad::SEXOS" :valor="$d->sexo" obrigatorio />
            <x-lista nome="parentesco" rotulo="Parentesco" :opcoes="$parentescos" :valor="$d->parentesco" obrigatorio codigos classe="full"
                ajuda="Filho ou equiparado que cursa faculdade ou escola técnica: use o código 04 (ou 07). O limite de idade do IR passa a ser 24 anos." />
        </div>

        <p class="grupo" style="margin-top:24px">Finalidades</p>
        <div class="form-grid">
            <x-escolha nome="declarar_ir" rotulo="Dependente para o imposto de renda?" :valor="$d->declarar_ir" obrigatorio ajuda="Filho ou equiparado: até 21 anos, ou 24 se universitário." />
            <x-escolha nome="declarar_previdencia" rotulo="Dependente para a previdência?" :valor="$d->declarar_previdencia" obrigatorio ajuda="Filho: até 21 anos, salvo deficiência." />
            <div data-mostrar-se="parentesco:{{ $filhos }}">
                <x-escolha nome="salario_familia" rotulo="Dependente para o salário-família?" :valor="$d->salario_familia" obrigatorio ajuda="Só filho ou equiparado, até 14 anos." />
            </div>
        </div>

        <p class="grupo" style="margin-top:24px">Deficiência</p>
        <div class="form-grid">
            <x-escolha nome="pcd" rotulo="Pessoa com deficiência?" :valor="$d->pcd" obrigatorio ajuda="Com deficiência, não há limite de idade nas finalidades." />
            <div data-mostrar-se="pcd:1">
                <x-lista nome="tipo_deficiencia" rotulo="Tipo de deficiência" :opcoes="array_diff_key(FichaSegad::DEFICIENCIAS, [99 => null])" :valor="$d->tipo_deficiencia === 99 ? null : $d->tipo_deficiencia" codigos />
            </div>
        </div>

        <div class="nota"><x-icone nome="cadeado" tamanho="18" /><span>O CPF do dependente fica criptografado. Deficiência é dado sensível (LGPD): na trilha de auditoria aparece só que mudou, sem o valor.</span></div>
        <div class="form-acoes">
            <a href="{{ route('servidores.show', [$servidor, 'aba' => 'dependentes']) }}" class="btn btn-s">Cancelar</a>
            <button type="submit" class="btn btn-p"><x-icone nome="ok" tamanho="18" />{{ $novo ? 'Cadastrar dependente' : 'Salvar alterações' }}</button>
        </div>
    </form>
</x-layouts.app>
