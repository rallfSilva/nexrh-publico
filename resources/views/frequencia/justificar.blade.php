@use('App\Frequencia\Espelho')
@use('App\Models\Justificativa')
@php
    $tipos = collect(Justificativa::TIPOS)->map(fn ($t) => $t[0])->all();
    $comAnexo = implode(',', array_keys(array_filter(Justificativa::TIPOS, fn ($t) => $t[1])));
@endphp
<x-layouts.app titulo="Justificar ocorrência">
    <div>
        <a href="{{ route('meu-ponto', ['mes' => $data->format('Y-m')]) }}" class="small" style="font-weight:600;display:inline-flex;gap:4px;align-items:center"><x-icone nome="voltar" tamanho="16" />Meu ponto</a>
        <h1 class="h1" style="margin-top:8px">Justificar ocorrência</h1>
        <p class="sub">A justificativa vai para a chefia do setor, que abona ou recusa. Dia abonado conta como trabalhado, com a jornada inteira.</p>
    </div>

    <section class="card" style="max-width:760px">
        @if ($ocorrencia)
            <div class="ocorrencia-resumo">
                <b>{{ ucfirst($data->translatedFormat('l, d/m/Y')) }}: {{ Espelho::SITUACOES[$ocorrencia['situacao']][0] }}.</b>
                {{ $ocorrencia['alertas'] ? implode(' · ', $ocorrencia['alertas']).'.' : '' }}
                @if ($ocorrencia['registros']->filter->valido()->isNotEmpty())
                    Marcações: {{ $ocorrencia['registros']->filter->valido()->map(fn ($r) => $r->momento->format('H:i'))->implode(', ') }}.
                @endif
            </div>
        @else
            <div class="flash flash-aviso" style="margin-bottom:16px"><x-icone nome="alerta" /><span>O dia {{ $data->format('d/m/Y') }} não tem ocorrência para justificar, ou já tem uma justificativa valendo. Escolha outro dia, se for o caso.</span></div>
        @endif

        <form method="POST" action="{{ route('justificativas.store') }}" enctype="multipart/form-data" class="form-col" novalidate data-um-envio>
            @csrf
            <div class="form-grid">
                <x-lista nome="tipo" rotulo="Motivo da ocorrência" :opcoes="$tipos" vazio="Escolha" obrigatorio />
                <x-campo nome="inicio" rotulo="Dia" tipo="date" :valor="$data->format('Y-m-d')" max="{{ today()->subDay()->format('Y-m-d') }}" obrigatorio />
                <div data-mostrar-se="tipo:atestado">
                    <x-campo nome="fim" rotulo="Último dia do atestado" tipo="date" ajuda="Só se o atestado for de mais de um dia (até {{ Justificativa::MAXIMO_DIAS }})." />
                </div>
                <div data-mostrar-se="tipo:esquecimento">
                    <x-campo nome="horarios" rotulo="Horário que faltou registrar" maxlength="60" placeholder="Ex.: saída às 13:30" ajuda="A chefia confere e, se abonar, o dia conta inteiro." />
                </div>
            </div>
            <div class="campo">
                <label for="campo-motivo">O que aconteceu <span aria-hidden="true" style="color:#9F1D1D">*</span></label>
                <textarea id="campo-motivo" name="motivo" class="input" rows="4" maxlength="1000" required style="padding-top:10px;min-height:110px" @error('motivo') aria-invalid="true" @enderror>{{ old('motivo') }}</textarea>
                @error('motivo')<span class="erro">{{ $message }}</span>@enderror
            </div>
            <div class="campo">
                <label for="campo-arquivo">Documento <span class="muted" style="font-weight:500">(obrigatório para atestado e declaração)</span></label>
                <input id="campo-arquivo" name="arquivo" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="input" style="padding-top:9px" @error('arquivo') aria-invalid="true" @enderror>
                <span class="ajuda">PDF, JPG ou PNG, até 10 MB. Se puder, envie o atestado sem o CID (código da doença): a chefia vê o anexo.</span>
                @error('arquivo')<span class="erro">{{ $message }}</span>@enderror
            </div>
            <div class="form-acoes">
                <a href="{{ route('meu-ponto', ['mes' => $data->format('Y-m')]) }}" class="btn btn-s">Voltar</a>
                <button type="submit" class="btn btn-p"><x-icone nome="enviar" />Enviar justificativa</button>
            </div>
        </form>
    </section>
</x-layouts.app>
