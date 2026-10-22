<div class="linha-lista">
    @foreach (['nome' => 'Nome do parente', 'cargo' => 'Cargo', 'relacao' => 'Relação (ex.: irmão)', 'orgao' => 'Órgão'] as $campo => $rotulo)
        <div class="campo">
            <label for="parente-{{ $i }}-{{ $campo }}">{{ $rotulo }}</label>
            <input id="parente-{{ $i }}-{{ $campo }}" name="parentes[{{ $i }}][{{ $campo }}]" value="{{ $p[$campo] ?? '' }}" class="input" @error("parentes.$i.$campo") aria-invalid="true" @enderror>
            @error("parentes.$i.$campo")<span class="erro">{{ $message }}</span>@enderror
        </div>
    @endforeach
    <button type="button" class="icobtn" data-remover-linha title="Remover" aria-label="Remover parente"><x-icone nome="lixeira" tamanho="18" /></button>
</div>
