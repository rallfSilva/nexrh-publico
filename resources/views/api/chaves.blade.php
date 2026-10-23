<x-layouts.app titulo="Chaves de API">
    <div class="row">
        <div>
            <h1 class="h1">Chaves de API</h1>
            <p class="sub">Para outros sistemas consultarem o NexRH (só leitura). Cada chave vê um órgão só, apenas o que foi liberado, e nunca dados pessoais.</p>
        </div>
        <div class="btns">
            <a href="{{ route('ajuda.index') }}#api" class="btn btn-s"><x-icone nome="ajuda" />Como usar a API</a>
        </div>
    </div>

    @if ($criada)
        <section class="card chave-nova" aria-live="polite">
            <div class="row" style="align-items:flex-start">
                <div>
                    <h2>Chave criada: copie agora</h2>
                    <p class="hint">Ela aparece só esta vez. Guarde num cofre de senhas e entregue ao responsável pela integração por um canal seguro. Se perder, revogue e crie outra.</p>
                </div>
            </div>
            <div class="chave-texto">
                <code id="chave-texto">{{ $criada['texto'] }}</code>
                <button type="button" class="btn btn-p btn-sm" data-copiar="chave-texto"><x-icone nome="copiar" tamanho="16" />Copiar</button>
            </div>
            <p class="small muted" style="margin:10px 0 0">Use no cabeçalho de cada chamada: <code>Authorization: Bearer {{ \Illuminate\Support\Str::limit($criada['texto'], 14, '…') }}</code></p>
        </section>
    @endif

    <div class="g2 g2-chaves">
        <section class="card card-flush">
            <div class="tbl-cab"><b>{{ $chaves->count() }} {{ $chaves->count() === 1 ? 'chave' : 'chaves' }}</b></div>
            @if ($chaves->isEmpty())
                <div class="vazio"><b>Nenhuma chave criada.</b>Crie uma chave para cada sistema que vai consultar o NexRH.</div>
            @else
                <div class="list" style="margin:0;padding:0 20px">
                    @foreach ($chaves as $c)
                        <div class="li">
                            <span class="chip {{ $c->valida() ? 'c-navy' : 'c-mute' }}"><x-icone nome="chave" /></span>
                            <div style="min-width:0">
                                <b>{{ $c->nome }}</b> <span class="b {{ $c->situacao()[1] }}">{{ $c->situacao()[0] }}</span>
                                <div class="meta"><code>{{ $c->prefixo }}…</code>@if ($orgaos) · {{ $c->orgao->sigla }}@endif · {{ collect($c->escopos)->implode(', ') }}</div>
                                <div class="meta">Criada em {{ $c->created_at->format('d/m/Y') }}{{ $c->criadaPor ? ' por '.$c->criadaPor->name : '' }}{{ $c->expira_em ? ' · expira em '.$c->expira_em->format('d/m/Y') : ' · sem prazo' }} · {{ $c->ultimo_uso_em ? 'último uso '.$c->ultimo_uso_em->diffForHumans().' ('.$c->usos.' consultas)' : 'nunca usada' }}</div>
                            </div>
                            @if ($c->revogada_em === null)
                                <form method="POST" action="{{ route('api.chaves.revogar', $c) }}" class="r" onsubmit="return confirm('Revogar a chave &quot;{{ $c->nome }}&quot;? O sistema que a usa perde o acesso na hora.')">
                                    @csrf
                                    <button type="submit" class="btn btn-s btn-sm">Revogar</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <form method="POST" action="{{ route('api.chaves.store') }}" class="card" novalidate>
            @csrf
            <h2>Nova chave</h2>
            <p class="hint">Uma chave por sistema, com o mínimo de acesso que ele precisa.</p>
            <div class="form-grid" style="grid-template-columns:minmax(0,1fr);margin-top:14px">
                <x-campo nome="nome" rotulo="Para qual sistema" obrigatorio maxlength="120" placeholder="Ex.: Ponto eletrônico" />
                @if ($orgaos)
                    <x-lista nome="orgao_id" rotulo="Órgão" :opcoes="$orgaos" obrigatorio />
                @endif
                <fieldset class="campo">
                    <legend class="rotulo">O que pode consultar <span aria-hidden="true" style="color:#9F1D1D">*</span></legend>
                    @foreach (\App\Models\ChaveApi::ESCOPOS as $escopo => $rotulo)
                        <label class="check"><input type="checkbox" name="escopos[]" value="{{ $escopo }}" @checked(in_array($escopo, old('escopos', []), true))> {{ $rotulo }}</label>
                    @endforeach
                    @error('escopos')<span class="erro">{{ $message }}</span>@enderror
                </fieldset>
                <x-campo nome="expira_em" rotulo="Vale até" tipo="date" ajuda="Opcional. Sem data, vale até ser revogada." />
            </div>
            <div class="form-acoes">
                <button type="submit" class="btn btn-p"><x-icone nome="chave" tamanho="18" />Criar chave</button>
            </div>
        </form>
    </div>
</x-layouts.app>
