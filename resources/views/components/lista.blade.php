{{-- Select com as listas da ficha. $opcoes: [valor => rótulo]; $codigos mostra "2 - Solteiro(a)". --}}
@props(['nome', 'rotulo', 'opcoes', 'valor' => null, 'obrigatorio' => false, 'classe' => '', 'ajuda' => null, 'codigos' => false, 'vazio' => 'Selecione'])
<x-campo :nome="$nome" :rotulo="$rotulo" :obrigatorio="$obrigatorio" :classe="$classe" :ajuda="$ajuda">
    <select id="campo-{{ $nome }}" name="{{ $nome }}" class="input" @if ($obrigatorio) required @endif @error($nome) aria-invalid="true" @enderror {{ $attributes }}>
        <option value="">{{ $vazio }}</option>
        @foreach ($opcoes as $v => $r)
            <option value="{{ $v }}" @selected((string) old($nome, $valor) === (string) $v)>{{ $codigos ? $v.' - '.$r : $r }}</option>
        @endforeach
    </select>
</x-campo>
