{{-- Envio de documento para o dossiê: o RH inclui já validado; o servidor envia para validação. --}}
@php
    use App\Models\Documento;
    $categorias = $propria ? \App\Http\Controllers\MinhaFichaController::CATEGORIAS_DO_SERVIDOR : array_keys(Documento::CATEGORIAS);
@endphp
<dialog class="modal" id="dialogo-documento" aria-labelledby="t-documento">
    <form method="POST" action="{{ $propria ? route('minha-ficha.documentos.store') : route('documentos.store', $s) }}" enctype="multipart/form-data" novalidate>
        @csrf
        <div>
            <h2 id="t-documento" style="margin:0;font-size:18px">{{ $propria ? 'Enviar documento' : 'Incluir documento no dossiê' }}</h2>
            <p class="small muted" style="margin:6px 0 0">{{ $propria ? 'O RH confere e valida. Enquanto isso, o documento aparece como "aguardando validação".' : 'Documento incluído pelo RH já entra como validado.' }}</p>
        </div>
        <x-campo nome="tipo" rotulo="Tipo de documento" obrigatorio>
            <select id="campo-tipo" name="tipo" class="input" required @error('tipo') aria-invalid="true" @enderror>
                <option value="">Selecione</option>
                @foreach ($categorias as $categoria)
                    <optgroup label="{{ Documento::CATEGORIAS[$categoria] }}">
                        @foreach (Documento::TIPOS[$categoria] as $tipo => $rotulo)
                            <option value="{{ $tipo }}" @selected(old('tipo') === $tipo)>{{ $rotulo }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </x-campo>
        <x-campo nome="descricao" rotulo="Descrição" placeholder="Ex.: Curso de Licitações (40h) ou Portaria nº 123/2026" ajuda="Obrigatória para certificado de curso, portaria e outro documento." maxlength="160" />
        <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:16px">
            <x-campo nome="data_documento" rotulo="Data do documento" tipo="date" :max="now()->format('Y-m-d')" />
            <x-campo nome="validade" rotulo="Válido até" tipo="date" ajuda="CNH, ASO, comprovantes com prazo." />
        </div>
        <x-campo nome="arquivo" rotulo="Arquivo (PDF, JPG ou PNG, até 10 MB)" obrigatorio>
            <input id="campo-arquivo" name="arquivo" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="input" style="padding-top:9px" required @error('arquivo') aria-invalid="true" @enderror>
        </x-campo>
        <div class="form-acoes">
            <button type="button" class="btn btn-s" data-fechar-dialogo>Cancelar</button>
            <button type="submit" class="btn btn-p"><x-icone nome="enviar" />{{ $propria ? 'Enviar' : 'Incluir' }}</button>
        </div>
    </form>
</dialog>
@if ($errors->hasAny(['tipo', 'descricao', 'data_documento', 'validade', 'arquivo']))
    <script type="module">document.getElementById('dialogo-documento').showModal();</script>
@endif
