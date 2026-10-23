{{-- Diálogos do RH na ficha: motivo (recusar ou arquivar documento) e encerramento de dependente.
     Um diálogo serve a todos os itens; o botão informa o destino (data-acao). --}}
@php
    // Depois de um erro de validação, reabre o diálogo com o mesmo destino (só se for desta ficha).
    $reabrir = fn () => str_starts_with((string) old('acao_dialogo'), url('/servidores/'.$s->id.'/')) ? old('acao_dialogo') : null;
@endphp
<dialog class="modal" id="dialogo-motivo" aria-labelledby="t-motivo">
    <form method="POST" action="{{ $reabrir() ?? '#' }}">
        @csrf
        <input type="hidden" name="acao_dialogo" value="{{ $reabrir() }}">
        <div><h2 id="t-motivo" style="margin:0;font-size:18px" data-titulo-dialogo>Informe o motivo</h2><p class="small muted" style="margin:6px 0 0">O motivo fica registrado no documento e na trilha de auditoria. Na recusa, o servidor vê o motivo na ficha dele.</p></div>
        <x-campo nome="motivo" rotulo="Motivo" obrigatorio>
            <textarea id="campo-motivo" name="motivo" class="input" rows="3" maxlength="255" required style="padding-top:10px;min-height:90px">{{ old('motivo') }}</textarea>
        </x-campo>
        <div class="form-acoes">
            <button type="button" class="btn btn-s" data-fechar-dialogo>Cancelar</button>
            <button type="submit" class="btn btn-p">Confirmar</button>
        </div>
    </form>
</dialog>

<dialog class="modal" id="dialogo-encerrar" aria-labelledby="t-encerrar">
    <form method="POST" action="{{ $reabrir() ?? '#' }}">
        @csrf
        <input type="hidden" name="acao_dialogo" value="{{ $reabrir() }}">
        <div><h2 id="t-encerrar" style="margin:0;font-size:18px">Encerrar dependente</h2><p class="small muted" style="margin:6px 0 0"><b data-titulo-dialogo></b> deixa de ser dependente a partir da data informada. O cadastro continua no histórico e pode ser reativado.</p></div>
        <x-campo nome="encerrado_em" rotulo="Deixa de ser dependente em" tipo="date" :valor="now()->format('Y-m-d')" obrigatorio />
        <x-lista nome="motivo_encerramento" rotulo="Motivo" :opcoes="array_combine(\App\Models\Dependente::MOTIVOS_ENCERRAMENTO, \App\Models\Dependente::MOTIVOS_ENCERRAMENTO)" obrigatorio />
        <div class="form-acoes">
            <button type="button" class="btn btn-s" data-fechar-dialogo>Cancelar</button>
            <button type="submit" class="btn btn-d">Encerrar</button>
        </div>
    </form>
</dialog>

@if ($reabrir() && $errors->has('motivo') && str_contains($reabrir(), '/documentos/'))
    <script type="module">document.getElementById('dialogo-motivo').showModal();</script>
@elseif ($reabrir() && $errors->hasAny(['encerrado_em', 'motivo_encerramento']))
    <script type="module">document.getElementById('dialogo-encerrar').showModal();</script>
@endif
