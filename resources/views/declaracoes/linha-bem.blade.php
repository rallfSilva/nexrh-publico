<div class="linha-lista" style="grid-template-columns: 200px minmax(200px, 3fr) 160px 44px">
    <div class="campo">
        <label for="bem-{{ $i }}-titular">Titular</label>
        <select id="bem-{{ $i }}-titular" name="bens[{{ $i }}][titular]" class="input">
            @foreach (\App\Support\FichaSegad::TITULARES_BEM as $v => $r)
                <option value="{{ $v }}" @selected(($b['titular'] ?? 'servidor') === $v)>{{ $r }}</option>
            @endforeach
        </select>
    </div>
    <div class="campo">
        <label for="bem-{{ $i }}-discriminacao">Discriminação</label>
        <input id="bem-{{ $i }}-discriminacao" name="bens[{{ $i }}][discriminacao]" value="{{ $b['discriminacao'] ?? '' }}" class="input" placeholder="Imóvel, veículo, aplicação etc." @error("bens.$i.discriminacao") aria-invalid="true" @enderror>
        @error("bens.$i.discriminacao")<span class="erro">{{ $message }}</span>@enderror
    </div>
    <div class="campo">
        <label for="bem-{{ $i }}-valor">Valor (R$)</label>
        <input id="bem-{{ $i }}-valor" name="bens[{{ $i }}][valor]" value="{{ isset($b['valor']) && is_numeric($b['valor']) ? number_format((float) $b['valor'], 2, ',', '.') : ($b['valor'] ?? '') }}" class="input" inputmode="decimal" placeholder="0,00" @error("bens.$i.valor") aria-invalid="true" @enderror>
        @error("bens.$i.valor")<span class="erro">{{ $message }}</span>@enderror
    </div>
    <button type="button" class="icobtn" data-remover-linha title="Remover" aria-label="Remover bem"><x-icone nome="lixeira" tamanho="18" /></button>
</div>
