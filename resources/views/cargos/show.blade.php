<x-layouts.app :titulo="$cargo->denominacao">
    <div class="row">
        <div>
            <a href="{{ route('cargos.index', ['orgao' => $cargo->orgao_id]) }}" class="small" style="font-weight:600;display:inline-flex;gap:4px;align-items:center"><x-icone nome="voltar" tamanho="16" />Cargos e vagas</a>
            <h1 class="h1" style="margin-top:8px">{{ $cargo->denominacao }}</h1>
            <p class="sub">
                <span class="sigla">{{ $cargo->simbolo }}</span> · {{ $cargo->rotuloNatureza() }}
                @if ($cargo->remuneracao !== null) · R$ {{ number_format((float) $cargo->remuneracao, 2, ',', '.') }}@endif
                @if ($cargo->base_legal) · {{ $cargo->base_legal }}@endif
            </p>
        </div>
        @can('cargos.gerenciar')
            <a href="{{ route('cargos.edit', $cargo) }}" class="btn btn-s"><x-icone nome="editar" />Editar cargo</a>
        @endcan
    </div>

    <div class="kpis">
        <div class="card kpi"><span class="lbl">Total</span><span class="val">{{ $totais['total'] }}</span><span class="foot">criados por lei ou decreto</span></div>
        <div class="card kpi"><span class="lbl">Remanejados</span><span class="val">{{ $totais['remanejadas'] }}</span><span class="foot">fora do órgão</span></div>
        <div class="card kpi"><span class="lbl">Ocupados</span><span class="val">{{ $totais['ocupadas'] }}</span><span class="foot">de {{ $totais['disponiveis'] }} disponíveis</span></div>
        <div class="card kpi"><span class="lbl">Vagos</span><span class="val">{{ $totais['vagas'] }}</span><span class="foot">prontos para nomeação</span></div>
    </div>

    <div class="card card-flush">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead><tr><th class="num">Nº</th><th>Situação</th><th>Ocupante / destino</th><th>Ato</th><th>Base legal</th><th class="acoes"><span class="sr-only">Ações</span></th></tr></thead>
                <tbody>
                    @foreach ($cargo->vagas as $vaga)
                        @php($classe = ['ocupada' => 'b-ok', 'vaga' => 'b-info', 'remanejada' => 'b-warn', 'devolvida' => 'b-mute'][$vaga->situacao] ?? 'b-mute')
                        <tr>
                            <td class="num">{{ $vaga->numero }}</td>
                            <td><span class="b {{ $classe }}">{{ $vaga->rotuloSituacao() }}</span></td>
                            <td>
                                @if ($vaga->situacao === 'ocupada')
                                    <b>{{ $vaga->ocupante_nome }}</b>
                                    @if ($vaga->ocupante_cpf)<div class="muted small">CPF {{ $vaga->cpfMascarado() }}</div>@endif
                                @elseif ($vaga->orgao_remanejamento)
                                    para {{ $vaga->orgao_remanejamento }}
                                @else
                                    <span class="muted">{{ $vaga->observacao ?? '—' }}</span>
                                @endif
                            </td>
                            <td class="small">
                                @if ($vaga->ato_numero){{ $vaga->ato_numero }}@endif
                                @if ($vaga->ato_data)<div class="muted">de {{ $vaga->ato_data->format('d/m/Y') }}@if ($vaga->efeito_data), efeito {{ $vaga->efeito_data->format('d/m/Y') }}@endif</div>@endif
                                @unless ($vaga->ato_numero || $vaga->ato_data)<span class="muted">—</span>@endunless
                            </td>
                            <td class="muted small">{{ $vaga->base_legal ?? '—' }}</td>
                            <td class="acoes">
                                @can('cargos.gerenciar')
                                    <a href="{{ route('vagas.edit', $vaga) }}" class="btn btn-s btn-sm"><x-icone nome="editar" tamanho="16" />Editar</a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @if ($cargo->observacao)
        <p class="muted small">{{ $cargo->observacao }}</p>
    @endif
</x-layouts.app>
