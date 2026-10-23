@php
    $query = array_filter($filtros, fn ($v) => $v !== null);
@endphp
<x-layouts.app :titulo="$relatorio->titulo()">
    <div class="row">
        <div>
            <a href="{{ route('relatorios.index') }}" class="small" style="font-weight:600;display:inline-flex;gap:4px;align-items:center"><x-icone nome="voltar" tamanho="16" />Relatórios</a>
            <h1 class="h1" style="margin-top:8px">{{ $relatorio->titulo() }}</h1>
            <p class="sub">{{ $relatorio->descricao() }}</p>
        </div>
        <div class="btns">
            <a href="{{ route('relatorios.exportar', [$relatorio->chave(), 'xlsx'] + $query) }}" class="btn btn-s"><x-icone nome="planilha" />Baixar Excel</a>
            <a href="{{ route('relatorios.exportar', [$relatorio->chave(), 'pdf'] + $query) }}" class="btn btn-p"><x-icone nome="download" />Baixar PDF</a>
        </div>
    </div>

    @if ($definicoes)
        <form method="GET" class="card filtros">
            @foreach ($definicoes as $nome => $f)
                <x-campo :nome="$nome" :rotulo="$f['rotulo']">
                    <select id="campo-{{ $nome }}" name="{{ $nome }}" class="input" onchange="this.form.submit()">
                        @isset($f['vazio'])<option value="">{{ $f['vazio'] }}</option>@endisset
                        @foreach ($f['opcoes'] as $v => $rotulo)
                            <option value="{{ $v }}" @selected((string) $filtros[$nome] === (string) $v)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </x-campo>
            @endforeach
            <button type="submit" class="btn btn-s">Filtrar</button>
        </form>
    @endif

    <section class="card card-flush">
        <div class="tbl-cab">
            <b>{{ $total }} {{ $total === 1 ? 'linha' : 'linhas' }}</b>
            @if ($total > $limite)
                <span class="hint">A tela mostra as primeiras {{ $limite }}. O Excel e o PDF levam todas.</span>
            @endif
        </div>
        @if ($total === 0)
            <div class="vazio"><b>Nada encontrado.</b>Mude os filtros para ver outros resultados.</div>
        @else
            <div class="tbl-wrap">
                <table class="tbl tbl-rel">
                    <thead>
                        <tr>
                            @foreach ($colunas as $chave => $rotulo)
                                <th scope="col" class="{{ in_array($chave, $numericas, true) ? 'num' : '' }}">{{ $rotulo }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($linhas as $linha)
                            <tr>
                                @foreach ($colunas as $chave => $rotulo)
                                    <td class="{{ in_array($chave, $numericas, true) ? 'num' : '' }}">{{ $linha[$chave] ?? '' }}@if (($linha[$chave] ?? null) === null)<span class="muted">—</span>@endif</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.app>
