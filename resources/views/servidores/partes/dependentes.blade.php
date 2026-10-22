{{-- Aba Dependentes da ficha (RH e o próprio servidor). --}}
@php
    $ativos = $s->dependentes->filter->ativo();
    $encerrados = $s->dependentes->reject->ativo();
@endphp

<div class="row" style="margin-bottom:14px">
    <p class="small muted" style="margin:0;max-width:560px">Códigos de parentesco da ficha (tabela do eSocial). A idade de cada dependente é conferida com o limite de cada finalidade, e o que passar do limite aparece nas pendências.</p>
    @if ($gerenciaDossie && $s->ativo())
        <a href="{{ route('dependentes.create', $s) }}" class="btn btn-p"><x-icone nome="mais" />Novo dependente</a>
    @endif
</div>

@if ($ativos->isEmpty())
    <div class="vazio"><b>Nenhum dependente ativo.</b>{{ $propria ? 'Para incluir um dependente, envie a certidão ou o documento dele na aba Dossiê e avise o RH.' : '' }}</div>
@else
    <div class="tbl-wrap">
        <table class="tbl">
            <thead><tr><th>Dependente</th><th>Parentesco</th><th>CPF</th><th>Finalidades</th>@if ($gerenciaDossie)<th class="acoes"><span class="sr-only">Ações</span></th>@endif</tr></thead>
            <tbody>
                @foreach ($ativos as $dep)
                    <tr>
                        <td style="min-width:220px">
                            <b>{{ $dep->nome }}</b>
                            <div class="muted small">{{ $dep->data_nascimento->format('d/m/Y') }} · {{ $dep->idade() }} {{ $dep->idade() === 1 ? 'ano' : 'anos' }}{{ $dep->pcd ? ' · pessoa com deficiência' : '' }}</div>
                            @foreach ($dep->alertas() as $alerta)
                                <div class="alerta-linha"><x-icone nome="alerta" tamanho="14" />{{ $alerta }}</div>
                            @endforeach
                        </td>
                        <td style="min-width:150px"><span class="sigla">{{ $dep->parentesco }}</span> {{ $dep->parentescoCurto() }}</td>
                        <td style="white-space:nowrap">{{ $dep->cpfFormatado() }}</td>
                        <td>
                            <div class="dep-tags">
                                @forelse ($dep->finalidades() as $f)
                                    <span class="b b-info">{{ $f }}</span>
                                @empty
                                    <span class="muted small">Só cadastro</span>
                                @endforelse
                            </div>
                        </td>
                        @if ($gerenciaDossie)
                            <td class="acoes">
                                <a href="{{ route('dependentes.edit', [$s, $dep]) }}" class="btn btn-s btn-sm btn-icone" title="Editar" aria-label="Editar {{ $dep->nome }}"><x-icone nome="editar" tamanho="16" /></a>
                                <button type="button" class="btn btn-s btn-sm" data-abrir-dialogo="dialogo-encerrar" data-acao="{{ route('dependentes.encerrar', [$s, $dep]) }}" data-titulo="{{ $dep->nome }}">Encerrar</button>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($encerrados->isNotEmpty())
    <details class="arquivados">
        <summary>Não são mais dependentes ({{ $encerrados->count() }})</summary>
        <div class="tbl-wrap" style="margin-top:10px">
            <table class="tbl">
                <tbody>
                    @foreach ($encerrados as $dep)
                        <tr class="encerrado">
                            <td><b>{{ $dep->nome }}</b><div class="small">{{ $dep->parentescoCurto() }}</div></td>
                            <td>Até {{ $dep->encerrado_em->format('d/m/Y') }} · {{ $dep->motivo_encerramento }}</td>
                            @if ($gerenciaDossie && $s->ativo())
                                <td class="acoes">
                                    <form method="POST" action="{{ route('dependentes.reativar', [$s, $dep]) }}" data-confirmar="Reativar {{ $dep->nome }} como dependente?" onsubmit="return confirm(this.dataset.confirmar)">@csrf
                                        <button type="submit" class="btn btn-s btn-sm">Reativar</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
@endif
