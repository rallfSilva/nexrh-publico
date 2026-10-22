@php
    use App\Support\FichaSegad;
    $v = fn (string $campo, $padrao = null) => old($campo, $valores[$campo] ?? $padrao);
    $titulo = \App\Models\Declaracao::TIPOS[$tipo];
    $parentes = old('parentes', $valores['parentes'] ?? []) ?: [[]];
    $bens = old('bens', $valores['bens'] ?? []) ?: [['titular' => 'servidor']];
@endphp
<x-layouts.app :titulo="$titulo">
    <div>
        <a href="{{ route('servidores.show', [$servidor, 'aba' => 'declaracoes']) }}" class="small" style="font-weight:600;display:inline-flex;gap:4px;align-items:center"><x-icone nome="voltar" tamanho="16" />{{ $servidor->nome }}</a>
        <h1 class="h1" style="margin-top:8px">{{ $titulo }}</h1>
        <p class="sub">
            @switch($tipo)
                @case('parentesco') Parente até o 3º grau, ou cônjuge, em cargo de direção, chefia ou comissão no Estado (Súmula Vinculante nº 13 do STF). @break
                @case('acumulacao') Se acumula cargo, emprego ou função pública, ou se é aposentado (Constituição, art. 37, XVI e XVII). @break
                @case('bens') Bens e valores do servidor e do cônjuge. Exigida na posse e renovada todo ano (Lei nº 8.429/92, art. 13). @break
            @endswitch
            @if ($anterior) O formulário já vem com a declaração anterior, de {{ $anterior->data_declaracao->format('d/m/Y') }}; ela continua guardada como histórico. @endif
        </p>
    </div>

    <form method="POST" action="{{ route('declaracoes.store', [$servidor, $tipo]) }}" class="card" novalidate>
        @csrf
        <div class="form-grid">
            <x-campo nome="data_declaracao" rotulo="Data em que foi assinada" tipo="date" :valor="old('data_declaracao', now()->format('Y-m-d'))" obrigatorio />
            @if ($tipo === 'bens')
                <x-campo nome="ano_referencia" rotulo="Ano de referência" tipo="number" :valor="old('ano_referencia', $ano)" obrigatorio min="2000" max="2100" />
            @endif
        </div>

        @if ($tipo === 'parentesco')
            <p class="grupo" style="margin-top:24px">Vínculo do declarante</p>
            <div class="form-grid">
                <x-lista nome="vinculo_funcional" rotulo="Vínculo funcional" :opcoes="array_combine(FichaSegad::VINCULOS, FichaSegad::VINCULOS)" :valor="$v('vinculo_funcional')" obrigatorio classe="full" />
                <x-campo nome="cargo_comissao" rotulo="Cargo em comissão" :valor="$v('cargo_comissao')" obrigatorio />
                <x-campo nome="cargo_efetivo" rotulo="Cargo efetivo (se houver)" :valor="$v('cargo_efetivo')" />
            </div>

            <p class="grupo" style="margin-top:24px">Parentes</p>
            <x-escolha nome="possui_parente" rotulo="Possui parente até o 3º grau em cargo de direção, chefia ou comissão?" :valor="$v('possui_parente')" obrigatorio />
            <div data-mostrar-se="possui_parente:1" style="margin-top:14px">
                <div id="lista-parentes">
                    @foreach ($parentes as $i => $p)
                        @include('declaracoes.linha-parente', ['i' => $i, 'p' => $p])
                    @endforeach
                </div>
                @error('parentes')<p class="erro small" style="color:#9F1D1D;font-weight:600">{{ $message }}</p>@enderror
                <button type="button" class="btn btn-s btn-sm" data-adicionar-linha="modelo-parente" data-destino="lista-parentes"><x-icone nome="mais" tamanho="16" />Adicionar parente</button>
                <template id="modelo-parente">@include('declaracoes.linha-parente', ['i' => '__i__', 'p' => []])</template>
            </div>

            <p class="grupo" style="margin-top:24px">Cônjuge ou companheiro(a)</p>
            <x-escolha nome="possui_conjuge" rotulo="Possui cônjuge ou companheiro(a) em cargo de direção, chefia ou comissão?" :valor="$v('possui_conjuge')" obrigatorio />
            <div class="form-grid" data-mostrar-se="possui_conjuge:1" style="margin-top:14px">
                <x-campo nome="conjuge_nome" rotulo="Nome" :valor="$v('conjuge_nome')" />
                <x-campo nome="conjuge_cargo" rotulo="Cargo" :valor="$v('conjuge_cargo')" />
                <x-campo nome="conjuge_orgao" rotulo="Órgão" :valor="$v('conjuge_orgao')" />
            </div>
        @endif

        @if ($tipo === 'acumulacao')
            <p class="grupo" style="margin-top:24px">Acumulação</p>
            <x-escolha nome="acumula" rotulo="Acumula cargo, emprego ou função pública?" :valor="$v('acumula')" obrigatorio />
            <div class="form-grid" data-mostrar-se="acumula:1" style="margin-top:14px">
                <x-campo nome="cargo_acumulado" rotulo="Cargo acumulado" :valor="$v('cargo_acumulado')" />
                <x-campo nome="instituicao" rotulo="Instituição" :valor="$v('instituicao')" />
                <x-campo nome="horario_trabalho" rotulo="Dias e horários do outro vínculo" :valor="$v('horario_trabalho')" placeholder="Ex.: seg. a sex., 19h às 22h" />
            </div>

            <p class="grupo" style="margin-top:24px">Aposentadoria</p>
            <x-escolha nome="aposentado" rotulo="É aposentado(a)?" :valor="$v('aposentado')" obrigatorio />
            <div class="form-grid" data-mostrar-se="aposentado:1" style="margin-top:14px">
                <x-campo nome="cargo_aposentadoria" rotulo="Cargo em que se aposentou" :valor="$v('cargo_aposentadoria')" />
                <x-campo nome="fonte_proventos" rotulo="Órgão que paga os proventos" :valor="$v('fonte_proventos')" />
            </div>
        @endif

        @if ($tipo === 'bens')
            <p class="grupo" style="margin-top:24px">Bens e valores</p>
            <x-escolha nome="possui_bens" rotulo="Possui bens a declarar?" :valor="$v('possui_bens')" obrigatorio />
            <div data-mostrar-se="possui_bens:1" style="margin-top:14px">
                <div id="lista-bens">
                    @foreach ($bens as $i => $b)
                        @include('declaracoes.linha-bem', ['i' => $i, 'b' => $b])
                    @endforeach
                </div>
                @error('bens')<p class="erro small" style="color:#9F1D1D;font-weight:600">{{ $message }}</p>@enderror
                <button type="button" class="btn btn-s btn-sm" data-adicionar-linha="modelo-bem" data-destino="lista-bens"><x-icone nome="mais" tamanho="16" />Adicionar bem</button>
                <template id="modelo-bem">@include('declaracoes.linha-bem', ['i' => '__i__', 'b' => ['titular' => 'servidor']])</template>
            </div>

            <p class="grupo" style="margin-top:24px">Cônjuge (quando houver bens em nome dele)</p>
            <div class="form-grid">
                <x-campo nome="conjuge_nome" rotulo="Nome do cônjuge" :valor="$v('conjuge_nome')" />
                <x-campo nome="conjuge_cpf" rotulo="CPF do cônjuge" :valor="$v('conjuge_cpf') ? \App\Support\Cpf::formatar($v('conjuge_cpf')) : null" data-mascara="cpf" inputmode="numeric" autocomplete="off" />
            </div>
        @endif

        <div class="nota"><x-icone nome="cadeado" tamanho="18" /><span>O conteúdo desta declaração fica criptografado. Na trilha de auditoria aparece só que ela foi registrada, sem os nomes e valores.</span></div>
        <div class="form-acoes">
            <a href="{{ route('servidores.show', [$servidor, 'aba' => 'declaracoes']) }}" class="btn btn-s">Cancelar</a>
            <button type="submit" class="btn btn-p"><x-icone nome="ok" tamanho="18" />Registrar declaração</button>
        </div>
    </form>
</x-layouts.app>
