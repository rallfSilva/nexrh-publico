<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>{{ $relatorio->titulo() }}</title>
<style>
    @page { margin: 28px 30px 40px; }
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.4px; color: #0F2A3A; margin: 0; }
    .topo { border-bottom: 2px solid #1E5470; padding-bottom: 6px; margin-bottom: 10px; }
    .topo table { width: 100%; border-collapse: collapse; }
    .topo .marca { width: 34px; height: 34px; display: block; }
    .topo h1 { font-size: 14px; margin: 0; color: #1E5470; }
    .topo .org { font-size: 9px; color: #34729C; }
    .topo .dir { text-align: right; font-size: 8px; color: #4A6273; }
    table.lista { width: 100%; border-collapse: collapse; }
    table.lista th { background: #1E5470; color: #fff; font-size: 7.4px; text-transform: uppercase; letter-spacing: .03em; text-align: left; padding: 4px 5px; }
    table.lista td { border-bottom: 1px solid #DCE8EF; padding: 3px 5px; vertical-align: top; }
    table.lista tr:nth-child(even) td { background: #F5FAFD; }
    .num { text-align: right; }
    .vazio { color: #9AAFBE; }
    .rodape { position: fixed; bottom: -26px; left: 0; right: 0; font-size: 7px; color: #4A6273; border-top: 1px solid #CBEAEC; padding-top: 3px; }
    .rodape .pg:after { content: counter(page); }
    .rodape td { vertical-align: middle; }
    .rodape .mini { width: 11px; height: 11px; vertical-align: middle; }
    .rodape .nome { color: #1E5470; font-weight: bold; }
</style>
</head>
<body>
<div class="rodape">
    <table style="width:100%"><tr>
        <td style="width:14px"><img src="{{ $logo }}" class="mini" alt=""></td>
        <td>{{ $relatorio->titulo() }} · gerado pelo <span class="nome">NexRH Público</span> em {{ $geradoEm->format('d/m/Y H:i') }} por {{ $usuario->name }} · CPF mascarado</td>
        <td style="text-align:right">página <span class="pg"></span></td>
    </tr></table>
</div>

<div class="topo">
    <table><tr>
        <td style="width:44px"><img src="{{ $logo }}" class="marca" alt="NexRH Público"></td>
        <td>
            <h1>{{ $relatorio->titulo() }}</h1>
            <div class="org">{{ $usuario->orgao?->nome ?? 'Todos os órgãos' }}</div>
        </td>
        <td class="dir">{{ $filtros }}<br>{{ $linhas->count() }} {{ $linhas->count() === 1 ? 'linha' : 'linhas' }}</td>
    </tr></table>
</div>

@if ($linhas->isEmpty())
    <p>Nada encontrado com estes filtros.</p>
@else
    <table class="lista">
        <thead>
            <tr>
                @foreach ($colunas as $chave => $rotulo)
                    <th class="{{ in_array($chave, $numericas, true) ? 'num' : '' }}">{{ $rotulo }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($linhas as $linha)
                <tr>
                    @foreach ($colunas as $chave => $rotulo)
                        <td class="{{ in_array($chave, $numericas, true) ? 'num' : '' }}">@if (($linha[$chave] ?? null) === null)<span class="vazio">—</span>@else{{ $linha[$chave] }}@endif</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
</body>
</html>
