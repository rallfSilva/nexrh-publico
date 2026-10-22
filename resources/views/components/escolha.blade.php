@props(['nome', 'rotulo', 'opcoes' => ['1' => 'Sim', '0' => 'Não'], 'valor' => null, 'obrigatorio' => false, 'classe' => '', 'ajuda' => null])
@php
    $erro = $errors->first($nome);
    $atual = old($nome, is_bool($valor) ? (int) $valor : $valor);
@endphp
<div class="campo {{ $classe }}" role="radiogroup" aria-labelledby="rotulo-{{ $nome }}">
    <span class="rotulo" id="rotulo-{{ $nome }}">{{ $rotulo }}@if ($obrigatorio) <span aria-hidden="true" style="color:#9F1D1D">*</span>@endif</span>
    <div class="seg">
        @foreach ($opcoes as $v => $r)
            <label><input type="radio" name="{{ $nome }}" value="{{ $v }}" @checked((string) $atual === (string) $v) @if ($obrigatorio) required @endif>{{ $r }}</label>
        @endforeach
    </div>
    @if ($ajuda)<span class="ajuda">{{ $ajuda }}</span>@endif
    @if ($erro)<span class="erro">{{ $erro }}</span>@endif
</div>
