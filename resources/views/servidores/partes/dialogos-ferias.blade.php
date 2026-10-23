{{-- Diálogos de férias na ficha: motivo (recusar ou cancelar férias, cancelar afastamento) e interrupção.
     Um diálogo serve a todos os itens; o botão informa o destino (data-acao). --}}
@php
    $reabrirFerias = fn () => str_starts_with((string) old('acao_dialogo'), url('/servidores/'.$s->id.'/')) ? old('acao_dialogo') : null;
@endphp
<dialog class="modal" id="dialogo-motivo-ferias" aria-labelledby="t-motivo-ferias">
    <form method="POST" action="{{ $reabrirFerias() ?? '#' }}">
        @csrf
        <input type="hidden" name="acao_dialogo" value="{{ $reabrirFerias() }}">
        <div><h2 id="t-motivo-ferias" style="margin:0;font-size:18px" data-titulo-dialogo>Informe o motivo</h2><p class="small muted" style="margin:6px 0 0">O motivo fica registrado no histórico e o servidor vê na ficha dele. Em férias canceladas ou recusadas, os dias voltam para o saldo.</p></div>
        <div class="campo">
            <label for="campo-motivo-ferias">Motivo <span aria-hidden="true" style="color:#9F1D1D">*</span></label>
            <textarea id="campo-motivo-ferias" name="motivo" class="input" rows="3" maxlength="255" required style="padding-top:10px;min-height:90px">{{ old('motivo') }}</textarea>
            @error('motivo')<span class="erro">{{ $message }}</span>@enderror
        </div>
        <div class="form-acoes">
            <button type="button" class="btn btn-s" data-fechar-dialogo>Voltar</button>
            <button type="submit" class="btn btn-d">Confirmar</button>
        </div>
    </form>
</dialog>

@if ($gerenciaFerias)
    <dialog class="modal" id="dialogo-interromper" aria-labelledby="t-interromper">
        <form method="POST" action="{{ $reabrirFerias() ?? '#' }}">
            @csrf
            <input type="hidden" name="acao_dialogo" value="{{ $reabrirFerias() }}">
            <div><h2 id="t-interromper" style="margin:0;font-size:18px">Interromper férias</h2><p class="small muted" style="margin:6px 0 0">Férias de <b data-titulo-dialogo></b>. Os dias que faltam voltam para o saldo do período e podem ser marcados de novo.</p></div>
            <x-campo nome="interrompida_em" rotulo="Volta ao trabalho em" tipo="date" :valor="today()->format('Y-m-d')" obrigatorio />
            <x-lista nome="motivo_interrupcao" rotulo="Motivo" :opcoes="array_combine(\App\Models\Ferias::MOTIVOS_INTERRUPCAO, \App\Models\Ferias::MOTIVOS_INTERRUPCAO)" obrigatorio />
            <div class="form-acoes">
                <button type="button" class="btn btn-s" data-fechar-dialogo>Voltar</button>
                <button type="submit" class="btn btn-d">Interromper</button>
            </div>
        </form>
    </dialog>
@endif

@if ($reabrirFerias() && $errors->has('motivo') && ! str_contains($reabrirFerias(), '/documentos/') && ! str_contains($reabrirFerias(), '/dependentes/'))
    <script type="module">document.getElementById('dialogo-motivo-ferias').showModal();</script>
@elseif ($reabrirFerias() && $errors->hasAny(['interrompida_em', 'motivo_interrupcao']))
    <script type="module">document.getElementById('dialogo-interromper').showModal();</script>
@endif
