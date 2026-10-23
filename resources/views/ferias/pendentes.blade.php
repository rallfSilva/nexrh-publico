<x-layouts.app titulo="Férias a aprovar">
    <div>
        <a href="{{ route('ferias.escala') }}" class="small" style="font-weight:600;display:inline-flex;gap:4px;align-items:center"><x-icone nome="voltar" tamanho="16" />Escala de férias</a>
        <h1 class="h1" style="margin-top:8px">Férias a aprovar</h1>
        <p class="sub">Pedidos feitos pelos servidores{{ auth()->user()->can('ferias.gerenciar') ? ' do órgão' : ' da sua equipe' }}, do mais próximo para o mais distante. Pedidos seus vão para a chefia superior ou para o RH.</p>
    </div>

    <section class="card">
        @if ($pedidos->isEmpty())
            <div class="vazio"><b>Nada para aprovar.</b>Quando alguém pedir férias, o pedido aparece aqui.</div>
        @else
            <div class="docs">
                @foreach ($pedidos as $f)
                    @php($conflito = $conflitos[$f->id] ?? [])
                    <div class="doc">
                        <span class="chip {{ $conflito ? 'c-warn' : 'c-navy' }}" style="width:38px;height:38px" aria-hidden="true"><x-icone nome="sol" tamanho="18" /></span>
                        <div class="txt">
                            <b><a href="{{ route('servidores.show', [$f->servidor, 'aba' => 'ferias']) }}">{{ $f->servidor->nome }}</a></b>
                            <small>{{ $f->servidor->unidade?->sigla ?? 'Sem lotação' }} · {{ $f->periodoTexto() }} · {{ $f->dias }} dias · período {{ $f->periodo->rotulo() }} · pedido em {{ $f->created_at->format('d/m/Y') }}</small>
                            @foreach ($conflito as $c)
                                <small class="motivo"><b>Conflito agora:</b> {{ $c }}</small>
                            @endforeach
                        </div>
                        <div class="doc-acoes">
                            <button type="button" class="btn btn-s btn-sm" data-abrir-dialogo="dialogo-recusar-ferias" data-acao="{{ route('ferias.recusar', [$f->servidor, $f]) }}" data-titulo="{{ $f->servidor->nome }} · {{ $f->periodoTexto() }}"><x-icone nome="fechar" tamanho="16" />Recusar</button>
                            <form method="POST" action="{{ route('ferias.aprovar', [$f->servidor, $f]) }}">@csrf
                                <input type="hidden" name="voltar" value="fila">
                                <button type="submit" class="btn btn-p btn-sm" @disabled($conflito)><x-icone nome="ok" tamanho="16" />Aprovar</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <dialog class="modal" id="dialogo-recusar-ferias" aria-labelledby="t-recusar-ferias">
        <form method="POST" action="{{ str_starts_with((string) old('acao_dialogo'), url('/servidores/')) ? old('acao_dialogo') : '#' }}">
            @csrf
            <input type="hidden" name="voltar" value="fila">
            <input type="hidden" name="acao_dialogo" value="{{ old('acao_dialogo') }}">
            <div><h2 id="t-recusar-ferias" style="margin:0;font-size:18px">Recusar pedido de férias</h2><p class="small muted" style="margin:6px 0 0"><b data-titulo-dialogo></b>. O servidor vê o motivo na ficha dele e pode pedir outra data.</p></div>
            <div class="campo">
                <label for="campo-motivo-recusa">Motivo <span aria-hidden="true" style="color:#9F1D1D">*</span></label>
                <textarea id="campo-motivo-recusa" name="motivo" class="input" rows="3" maxlength="255" required style="padding-top:10px;min-height:90px">{{ old('motivo') }}</textarea>
                @error('motivo')<span class="erro">{{ $message }}</span>@enderror
            </div>
            <div class="form-acoes">
                <button type="button" class="btn btn-s" data-fechar-dialogo>Voltar</button>
                <button type="submit" class="btn btn-d">Recusar pedido</button>
            </div>
        </form>
    </dialog>
    @if ($errors->has('motivo') && str_starts_with((string) old('acao_dialogo'), url('/servidores/')))
        <script type="module">document.getElementById('dialogo-recusar-ferias').showModal();</script>
    @endif
</x-layouts.app>
