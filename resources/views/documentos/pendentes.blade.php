<x-layouts.app titulo="Documentos a validar">
    @php
        $reabrir = str_starts_with((string) old('acao_dialogo'), url('/servidores/')) ? old('acao_dialogo') : null;
    @endphp
    <div>
        <h1 class="h1">Documentos a validar</h1>
        <p class="sub">Enviados pelos próprios servidores na ficha deles. Abra, confira e valide ou recuse; na recusa, o servidor vê o motivo. Os mais antigos aparecem primeiro.</p>
    </div>

    <section class="card card-flush">
        @if ($documentos->isEmpty())
            <div class="vazio"><b>Nada para validar.</b>Quando um servidor enviar um documento, ele aparece aqui.</div>
        @else
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Servidor</th><th>Documento</th><th>Enviado em</th><th class="acoes"><span class="sr-only">Ações</span></th></tr></thead>
                    <tbody>
                        @foreach ($documentos as $doc)
                            @php($s = $doc->servidor)
                            <tr>
                                <td>
                                    <a href="{{ route('servidores.show', [$s, 'aba' => 'dossie']) }}" class="quem-cel">
                                        <span class="avatar" aria-hidden="true">{{ $s->iniciais() }}</span>
                                        <span><b>{{ $s->nome }}</b><small>{{ $s->unidade?->sigla ?? 'Sem lotação' }}</small></span>
                                    </a>
                                </td>
                                <td>
                                    <b>{{ $doc->titulo() }}</b>
                                    <div class="muted small">{{ \App\Models\Documento::CATEGORIAS[$doc->categoria] }}{{ $doc->descricao ? ' · '.$doc->rotuloTipo() : '' }} · {{ strtoupper(\App\Models\Documento::MIMES[$doc->arquivo_mime] ?? '') }} · {{ $doc->tamanhoLegivel() }}</div>
                                </td>
                                <td style="white-space:nowrap">{{ $doc->created_at->format('d/m/Y H:i') }}<div class="muted small">{{ $doc->created_at->diffForHumans() }}</div></td>
                                <td class="acoes">
                                    <div class="doc-acoes" style="justify-content:flex-end">
                                        <a href="{{ route('documentos.arquivo', [$s, $doc]) }}" target="_blank" rel="noopener" class="btn btn-s btn-sm"><x-icone nome="olho" tamanho="16" />Abrir</a>
                                        <form method="POST" action="{{ route('documentos.validar', [$s, $doc]) }}">@csrf
                                            <input type="hidden" name="voltar" value="fila">
                                            <button type="submit" class="btn btn-p btn-sm"><x-icone nome="ok" tamanho="16" />Validar</button>
                                        </form>
                                        <button type="button" class="btn btn-s btn-sm" data-abrir-dialogo="dialogo-motivo" data-acao="{{ route('documentos.recusar', [$s, $doc]) }}" data-titulo="Recusar “{{ $doc->titulo() }}” de {{ $s->nome }}">Recusar</button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
    {{ $documentos->links() }}

    <dialog class="modal" id="dialogo-motivo" aria-labelledby="t-motivo">
        <form method="POST" action="{{ $reabrir ?? '#' }}">
            @csrf
            <input type="hidden" name="voltar" value="fila">
            <input type="hidden" name="acao_dialogo" value="{{ $reabrir }}">
            <div><h2 id="t-motivo" style="margin:0;font-size:18px" data-titulo-dialogo>Recusar documento</h2><p class="small muted" style="margin:6px 0 0">O servidor vê o motivo na ficha dele e pode enviar de novo.</p></div>
            <x-campo nome="motivo" rotulo="Motivo da recusa" obrigatorio>
                <textarea id="campo-motivo" name="motivo" class="input" rows="3" maxlength="255" required placeholder="Ex.: imagem ilegível, documento vencido, falta o verso" style="padding-top:10px;min-height:90px">{{ old('motivo') }}</textarea>
            </x-campo>
            <div class="form-acoes">
                <button type="button" class="btn btn-s" data-fechar-dialogo>Cancelar</button>
                <button type="submit" class="btn btn-d">Recusar</button>
            </div>
        </form>
    </dialog>
    @if ($reabrir && $errors->has('motivo'))
        <script type="module">document.getElementById('dialogo-motivo').showModal();</script>
    @endif
</x-layouts.app>
