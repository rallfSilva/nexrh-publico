<x-layouts.app titulo="Relatório de importação">
    @php
        $calc = $importacao->resumo['calculado'] ?? [];
        $decl = $importacao->resumo['declarado'] ?? null;
        $grupos = [
            'bloqueia' => ['Impedem a importação', 'flash-erro', 'Corrija na planilha e envie de novo.'],
            'erro' => ['Inconsistências da planilha', 'flash-aviso', 'Não impedem a importação: o sistema usa as linhas de cada vaga como verdade. Vale corrigir na origem.'],
            'aviso' => ['Avisos', 'flash-ok', 'Pontos para conferir.'],
        ];
        $rotulos = ['total' => 'Cargos na estrutura', 'remanejadas' => 'Remanejados para outros órgãos', 'disponiveis' => 'Disponíveis', 'ocupadas' => 'Ocupados', 'vagas' => 'Vagos'];
    @endphp

    <div class="row">
        <div>
            <a href="{{ route('importacoes.index') }}" class="small nao-imprimir" style="font-weight:600;display:inline-flex;gap:4px;align-items:center"><x-icone nome="voltar" tamanho="16" />Histórico de importações</a>
            <h1 class="h1" style="margin-top:8px">Relatório de divergências</h1>
            <p class="sub">{{ $importacao->arquivo_nome }} · enviada em {{ $importacao->created_at->format('d/m/Y H:i') }} por {{ $importacao->usuario?->name ?? '—' }} · {{ \App\Models\Importacao::FORMATOS[$importacao->formato] ?? 'Formato não reconhecido' }}</p>
        </div>
        <div class="btns nao-imprimir">
            <button type="button" class="btn btn-s" onclick="window.print()"><x-icone nome="download" />Imprimir ou salvar PDF</button>
            @if ($importacao->podeAplicar())
                <form method="POST" action="{{ route('importacoes.cancelar', $importacao) }}">@csrf
                    <button type="submit" class="btn btn-s">Cancelar</button>
                </form>
                <form method="POST" action="{{ route('importacoes.aplicar', $importacao) }}" data-confirmar="Aplicar esta planilha ao quadro de cargos? Os cargos e vagas da planilha substituem os atuais." onsubmit="return confirm(this.dataset.confirmar)">@csrf
                    <button type="submit" class="btn btn-p"><x-icone nome="ok" />Confirmar importação</button>
                </form>
            @endif
        </div>
    </div>

    @php($classeSituacao = ['analisada' => 'flash-aviso', 'aplicada' => 'flash-ok', 'cancelada' => 'flash-aviso', 'recusada' => 'flash-erro'][$importacao->situacao] ?? 'flash-aviso')
    <div class="flash {{ $classeSituacao }}">
        <x-icone nome="{{ $importacao->situacao === 'aplicada' ? 'ok' : 'alerta' }}" style="flex:none" />
        <div>
            <b>{{ \App\Models\Importacao::SITUACOES[$importacao->situacao] }}</b>
            @if ($importacao->situacao === 'aplicada') em {{ $importacao->aplicada_em->format('d/m/Y H:i') }}.
            @elseif ($importacao->situacao === 'analisada') Confira a prévia abaixo e confirme para gravar no quadro de cargos.
            @endif
        </div>
    </div>

    <section class="card">
        <h2>Totais</h2>
        <p class="hint">Contagem feita pelo sistema a partir de cada vaga{{ $decl ? ', comparada com o resumo escrito na planilha' : '' }}.</p>
        <div class="tbl-wrap" style="margin-top:12px">
            <table class="tbl">
                <thead><tr><th>Item</th><th class="num">Contado pelo sistema</th>@if ($decl)<th class="num">Escrito na planilha</th><th>Confere?</th>@endif</tr></thead>
                <tbody>
                    @foreach ($rotulos as $campo => $rotulo)
                        <tr>
                            <td>{{ $rotulo }}</td>
                            <td class="num"><b>{{ $calc[$campo] ?? 0 }}</b></td>
                            @if ($decl)
                                <td class="num">{{ $decl[$campo] ?? '—' }}</td>
                                <td>
                                    @if (! isset($decl[$campo]))<span class="muted">—</span>
                                    @elseif ($decl[$campo] === ($calc[$campo] ?? null))<span class="b b-ok">Confere</span>
                                    @else<span class="b b-err">Diferença de {{ abs($decl[$campo] - ($calc[$campo] ?? 0)) }}</span>@endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @foreach ($grupos as $nivel => [$titulo, $classe, $explicacao])
        @php($itens = $importacao->divergenciasDoNivel($nivel))
        @if ($itens !== [])
            <section class="card">
                <h2>{{ $titulo }} ({{ count($itens) }})</h2>
                <p class="hint">{{ $explicacao }}</p>
                <div class="list">
                    @foreach ($itens as $d)
                        <div class="li">
                            <span class="chip {{ $nivel === 'aviso' ? 'c-navy' : 'c-warn' }}"><x-icone nome="alerta" /></span>
                            <div>@if ($d['cargo'])<b>{{ $d['cargo'] }}</b><br>@endif{{ $d['mensagem'] }}</div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach

    @if ($importacao->divergencias === [])
        <div class="flash flash-ok"><x-icone nome="ok" style="flex:none" /><div>Nenhuma divergência encontrada. A planilha está consistente.</div></div>
    @endif

    @if ($previa !== [])
        <section class="card card-flush">
            <div style="padding:22px 24px 8px"><h2>Prévia: {{ count($previa) }} cargos</h2><p class="hint">É assim que o quadro vai ficar depois de confirmar.</p></div>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Cargo</th><th>Símbolo</th><th>Natureza</th><th class="num">Remuneração</th><th class="num">Total</th><th class="num">Remanej.</th><th class="num">Ocupados</th><th class="num">Vagos</th></tr></thead>
                    <tbody>
                        @foreach ($previa as $c)
                            @php($s = array_count_values(array_column($c['vagas'], 'situacao')))
                            <tr>
                                <td><b>{{ $c['denominacao'] }}</b></td>
                                <td class="sigla">{{ $c['simbolo'] }}</td>
                                <td class="muted small">{{ \App\Models\Cargo::NATUREZAS[$c['natureza']] ?? $c['natureza'] }}</td>
                                <td class="num">{{ $c['remuneracao'] !== null ? 'R$ '.number_format($c['remuneracao'], 2, ',', '.') : '—' }}</td>
                                <td class="num">{{ $c['quantidade_vagas'] }}</td>
                                <td class="num">{{ ($s['remanejada'] ?? 0) + ($s['devolvida'] ?? 0) }}</td>
                                <td class="num">{{ $s['ocupada'] ?? 0 }}</td>
                                <td class="num">{{ $s['vaga'] ?? 0 }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</x-layouts.app>
