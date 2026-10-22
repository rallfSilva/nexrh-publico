{{-- Aba Dossiê da ficha. Usada pelo RH (servidores.show) e pelo próprio servidor (Minha ficha). --}}
@php
    use App\Models\Documento;
    $emUso = $s->documentos->where('situacao', '!=', 'arquivado');
    $arquivados = $s->documentos->where('situacao', 'arquivado');
    $porCategoria = $emUso->groupBy('categoria');
    $faltam = $s->documentosDaPosseFaltando();
    $exigidos = count(Documento::EXIGIDOS_POSSE);
    $feitos = $exigidos - count($faltam);
    $abrir = fn ($doc, $baixar = false) => $propria
        ? route('minha-ficha.documentos.arquivo', ['documento' => $doc, 'baixar' => $baixar ? 1 : null])
        : route('documentos.arquivo', [$s, $doc, 'baixar' => $baixar ? 1 : null]);
@endphp

<div class="dossie-topo">
    <div class="posse">
        <div class="row" style="align-items:baseline;gap:8px">
            <b>Documentos da posse</b>
            <span class="small muted">{{ $feitos }} de {{ $exigidos }} validados</span>
        </div>
        <div class="barra" style="margin-top:8px" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $exigidos }}" aria-valuenow="{{ $feitos }}" aria-label="Documentos da posse validados"><span style="width:{{ round($feitos / $exigidos * 100) }}%"></span></div>
        @if ($faltam !== [])
            <p class="small muted" style="margin:8px 0 0">Faltam: {{ implode(', ', $faltam) }}.</p>
        @endif
    </div>
    @if ($podeEnviar)
        <button type="button" class="btn btn-p" data-abrir-dialogo="dialogo-documento"><x-icone nome="enviar" />{{ $propria ? 'Enviar documento' : 'Incluir documento' }}</button>
    @endif
</div>

@if ($emUso->isEmpty())
    <div class="vazio"><b>Nenhum documento no dossiê.</b>{{ $propria ? 'Envie seus documentos pelo botão acima. O RH confere e valida.' : 'Inclua os documentos do servidor ou peça que ele envie pela ficha dele.' }}</div>
@endif

@foreach (Documento::CATEGORIAS as $categoria => $rotuloCategoria)
    @continue(! $porCategoria->has($categoria))
    <p class="secao">{{ $rotuloCategoria }} <span class="muted" style="font-weight:500">· {{ $porCategoria[$categoria]->count() }}</span></p>
    <div class="docs">
        @foreach ($porCategoria[$categoria] as $doc)
            @php([$selo, $classe] = $doc->selo())
            <div class="doc">
                <span class="chip {{ $doc->ehImagem() ? 'c-teal' : 'c-navy' }}" style="width:38px;height:38px"><x-icone :nome="$doc->ehImagem() ? 'imagem' : 'arquivo'" tamanho="18" /></span>
                <div class="txt">
                    <b>{{ $doc->titulo() }}</b>
                    <small>
                        {{ collect([
                            $doc->descricao ? $doc->rotuloTipo() : null,
                            $doc->data_documento ? 'de '.$doc->data_documento->format('d/m/Y') : null,
                            $doc->validade && ! $doc->vencido() && ! $doc->vencendo() ? 'válido até '.$doc->validade->format('d/m/Y') : null,
                            strtoupper(Documento::MIMES[$doc->arquivo_mime] ?? '').' · '.$doc->tamanhoLegivel(),
                        ])->filter()->implode(' · ') }}
                    </small>
                    <small>
                        {{ $doc->enviado_pelo_servidor ? 'Enviado pelo servidor' : 'Incluído por '.($doc->enviadoPor?->name ?? 'RH') }} em {{ $doc->created_at->format('d/m/Y') }}
                        @if ($doc->situacao === 'validado' && $doc->enviado_pelo_servidor && $doc->analisadoPor) · validado por {{ $doc->analisadoPor->name }}@endif
                    </small>
                    @if ($doc->situacao === 'recusado' && $doc->motivo)
                        <small class="motivo"><b>Motivo da recusa:</b> {{ $doc->motivo }}</small>
                    @endif
                </div>
                <span class="b {{ $classe }}">{{ $selo }}</span>
                <div class="doc-acoes">
                    <a href="{{ $abrir($doc) }}" target="_blank" rel="noopener" class="btn btn-s btn-sm" title="Abrir em outra aba"><x-icone nome="olho" tamanho="16" />Abrir</a>
                    <a href="{{ $abrir($doc, true) }}" class="btn btn-s btn-sm btn-icone" title="Baixar" aria-label="Baixar {{ $doc->titulo() }}"><x-icone nome="download" tamanho="16" /></a>
                    @if ($gerenciaDossie && $doc->situacao !== 'pendente')
                        <button type="button" class="btn btn-s btn-sm btn-icone" title="Arquivar" aria-label="Arquivar {{ $doc->titulo() }}" data-abrir-dialogo="dialogo-motivo" data-acao="{{ route('documentos.arquivar', [$s, $doc]) }}" data-titulo="Arquivar “{{ $doc->titulo() }}”"><x-icone nome="arquivar" tamanho="16" /></button>
                    @endif
                </div>
                @if ($gerenciaDossie && $doc->situacao === 'pendente')
                    <div class="doc-decisao">
                        <span>Enviado pelo servidor. Abra, confira e decida.</span>
                        <form method="POST" action="{{ route('documentos.validar', [$s, $doc]) }}">@csrf
                            <button type="submit" class="btn btn-p btn-sm"><x-icone nome="ok" tamanho="16" />Validar</button>
                        </form>
                        <button type="button" class="btn btn-s btn-sm" data-abrir-dialogo="dialogo-motivo" data-acao="{{ route('documentos.recusar', [$s, $doc]) }}" data-titulo="Recusar “{{ $doc->titulo() }}”">Recusar</button>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endforeach

@if ($arquivados->isNotEmpty() && ! $propria)
    <details class="arquivados">
        <summary>Arquivados ({{ $arquivados->count() }})</summary>
        <div class="docs" style="margin-top:10px">
            @foreach ($arquivados as $doc)
                <div class="doc arquivado">
                    <span class="chip c-mute" style="width:38px;height:38px"><x-icone nome="arquivar" tamanho="18" /></span>
                    <div class="txt">
                        <b>{{ $doc->titulo() }}</b>
                        <small>{{ Documento::CATEGORIAS[$doc->categoria] }} · arquivado em {{ $doc->analisado_em?->format('d/m/Y') }}{{ $doc->analisadoPor ? ' por '.$doc->analisadoPor->name : '' }}</small>
                        @if ($doc->motivo)<small class="motivo"><b>Motivo:</b> {{ $doc->motivo }}</small>@endif
                    </div>
                    <div class="doc-acoes">
                        <a href="{{ $abrir($doc) }}" target="_blank" rel="noopener" class="btn btn-s btn-sm"><x-icone nome="olho" tamanho="16" />Abrir</a>
                    </div>
                </div>
            @endforeach
        </div>
    </details>
@endif

<div class="nota"><x-icone nome="cadeado" tamanho="18" /><span>Os arquivos ficam criptografados e só RH, administração do órgão e o próprio servidor abrem. Cada abertura e download fica na trilha de auditoria. Nenhum documento é apagado: o RH arquiva, com motivo.</span></div>
