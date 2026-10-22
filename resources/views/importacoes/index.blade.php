<x-layouts.app titulo="Histórico de importações">
    <div class="row">
        <div>
            @can('cargos.ver')<a href="{{ route('cargos.index') }}" class="small" style="font-weight:600;display:inline-flex;gap:4px;align-items:center"><x-icone nome="voltar" tamanho="16" />Cargos e vagas</a>@endcan
            <h1 class="h1" style="margin-top:8px">Histórico de importações</h1>
            <p class="sub">Todas as planilhas enviadas, com o relatório de divergências de cada uma.</p>
        </div>
        <a href="{{ route('importacoes.create') }}" class="btn btn-p"><x-icone nome="planilha" />Importar planilha</a>
    </div>

    <div class="card card-flush">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead><tr><th>Enviada em</th><th>Tipo</th><th>Arquivo</th><th>Por</th><th class="num">Divergências</th><th>Situação</th><th class="acoes"><span class="sr-only">Ações</span></th></tr></thead>
                <tbody>
                    @forelse ($importacoes as $imp)
                        @php($classe = ['analisada' => 'b-warn', 'aplicada' => 'b-ok', 'cancelada' => 'b-mute', 'recusada' => 'b-err'][$imp->situacao] ?? 'b-mute')
                        <tr>
                            <td class="small">{{ $imp->created_at->format('d/m/Y H:i') }}</td>
                            <td><span class="b b-info">{{ \App\Models\Importacao::TIPOS[$imp->tipo] ?? $imp->tipo }}</span></td>
                            <td>{{ $imp->arquivo_nome }}<div class="muted small">{{ \App\Models\Importacao::FORMATOS[$imp->formato] ?? 'Formato não reconhecido' }}</div></td>
                            <td class="small">{{ $imp->usuario?->name ?? '—' }}</td>
                            <td class="num">{{ count($imp->divergencias ?? []) }}</td>
                            <td><span class="b {{ $classe }}">{{ \App\Models\Importacao::SITUACOES[$imp->situacao] ?? $imp->situacao }}</span></td>
                            <td class="acoes"><a href="{{ route('importacoes.show', $imp) }}" class="btn btn-s btn-sm">Ver relatório</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="vazio"><b>Nenhuma planilha importada ainda</b></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="paginacao">{{ $importacoes->links() }}</div>
</x-layouts.app>
