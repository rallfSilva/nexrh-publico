@props(['tamanho' => 40, 'subtitulo' => null, 'texto' => true])
{{-- Símbolo do NexRH Público (duas pessoas formando o N) com o nome ao lado. --}}
<span {{ $attributes->class('logo-nexrh') }}>
    <img src="{{ asset('img/marca/simbolo.svg') }}" width="{{ $tamanho }}" height="{{ $tamanho }}" alt="" class="logo" @if (! $texto) aria-hidden="true" @endif>
    @if ($texto)
        <span class="logo-texto">
            <b>Nex<span>RH</span> <i>Público</i></b>
            @if ($subtitulo)<small>{{ $subtitulo }}</small>@endif
        </span>
    @endif
</span>
