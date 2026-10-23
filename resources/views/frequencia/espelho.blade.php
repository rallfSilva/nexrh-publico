@use('App\Frequencia\Turnos')
@use('App\Models\Jornada')
@php
    $corrigir = auth()->user()->can('frequencia.gerenciar');
    $navegar = fn ($m) => route('frequencia.espelho', [$servidor, 'mes' => $m->format('Y-m')]);
    $voltar = route('frequencia.index', ['unidade' => $servidor->unidade_id, 'mes' => $mes->format('Y-m')]);
    $reabrir = old('dialogo');
@endphp
<x-layouts.app titulo="Frequência de {{ $servidor->nome }}">
    <div class="row">
        <div>
            <a href="{{ $voltar }}" class="small nao-imprimir" style="font-weight:600;display:inline-flex;gap:4px;align-items:center"><x-icone nome="voltar" tamanho="16" />Frequência {{ $servidor->unidade?->sigla }}</a>
            <h1 class="h1" style="margin-top:8px">{{ $servidor->nome }}</h1>
            <p class="sub">{{ $servidor->unidade?->sigla }}{{ $servidor->matricula() ? ' · matrícula '.$servidor->matricula() : '' }} · {{ $servidor->rotuloVinculo() }}@unless ($servidor->ativo()) · <b>desligado</b>@endunless</p>
        </div>
        @if ($corrigir)
            <div class="btns nao-imprimir">
                <button type="button" class="btn btn-s" data-abrir-dialogo="dialogo-incluir"><x-icone nome="mais" />Incluir marcação</button>
                <button type="button" class="btn btn-s" data-abrir-dialogo="dialogo-jornada"><x-icone nome="relogio" />Alterar jornada</button>
            </div>
        @endif
    </div>

    <section class="card jornada-card">
        <span class="chip c-navy"><x-icone nome="relogio" tamanho="18" /></span>
        <div>
            <span class="rotulo-mini">Jornada atual</span>
            <b>{{ $jornadaAtual->resumo() }}</b>
            <div class="small muted">
                @if ($jornadaAtual->exists)
                    Desde {{ $jornadaAtual->inicio->format('d/m/Y') }}{{ $jornadaAtual->observacao ? ' · '.$jornadaAtual->observacao : '' }}
                @else
                    Padrão do órgão pela carga da ficha ({{ $servidor->carga_horaria ?: 'sem carga informada' }}). Tolerância de {{ $regra->tolerancia_minutos }} minutos.
                @endif
            </div>
        </div>
        @if ($jornadas->count() > 1 || ($jornadas->count() === 1 && ! $jornadaAtual->is($jornadas->first())))
            <details class="jornadas-ant">
                <summary>Jornadas anteriores</summary>
                <ul>
                    @foreach ($jornadas as $j)
                        <li>{{ $j->inicio->format('d/m/Y') }} a {{ $j->fim?->format('d/m/Y') ?? 'hoje' }}: {{ $j->resumo() }}</li>
                    @endforeach
                </ul>
            </details>
        @endif
    </section>

    @include('frequencia.partes.espelho', ['corrigir' => $corrigir])

    @if ($corrigir)
        <dialog class="modal" id="dialogo-incluir" aria-labelledby="t-incluir">
            <form method="POST" action="{{ route('frequencia.incluir', $servidor) }}">
                @csrf
                <input type="hidden" name="dialogo" value="dialogo-incluir">
                <div><h2 id="t-incluir" style="margin:0;font-size:18px">Incluir marcação</h2><p class="small muted" style="margin:6px 0 0">Para uma entrada ou saída que o servidor esqueceu de registrar. A marcação aparece no espelho com a indicação "RH", e quem incluiu e o motivo ficam registrados.</p></div>
                <div class="form-grid">
                    <x-campo nome="data" rotulo="Dia" tipo="date" :valor="old('data', today()->min($mes->copy()->endOfMonth())->format('Y-m-d'))" max="{{ today()->format('Y-m-d') }}" obrigatorio />
                    <x-campo nome="hora" rotulo="Hora" tipo="time" obrigatorio />
                </div>
                <x-campo nome="motivo" rotulo="Motivo" obrigatorio maxlength="255" placeholder="Ex.: esqueceu de registrar a saída; confirmado pela chefia" />
                <div class="form-acoes">
                    <button type="button" class="btn btn-s" data-fechar-dialogo>Voltar</button>
                    <button type="submit" class="btn btn-p">Incluir</button>
                </div>
            </form>
        </dialog>

        <dialog class="modal" id="dialogo-anular" aria-labelledby="t-anular">
            <form method="POST" action="{{ $reabrir === 'dialogo-anular' ? old('acao_dialogo') : '#' }}">
                @csrf
                <input type="hidden" name="dialogo" value="dialogo-anular">
                <input type="hidden" name="acao_dialogo" value="{{ old('acao_dialogo') }}">
                <div><h2 id="t-anular" style="margin:0;font-size:18px">Anular marcação</h2><p class="small muted" style="margin:6px 0 0">Marcação de <b data-titulo-dialogo></b>. Ela não é apagada: continua no espelho, riscada, com o motivo.</p></div>
                <x-campo nome="motivo_anulacao" rotulo="Motivo" obrigatorio maxlength="255" placeholder="Ex.: marcação em duplicidade" />
                <div class="form-acoes">
                    <button type="button" class="btn btn-s" data-fechar-dialogo>Voltar</button>
                    <button type="submit" class="btn btn-d">Anular</button>
                </div>
            </form>
        </dialog>

        @php
            $base = $jornadaAtual;
            $diasMarcados = array_map('intval', old('dias_semana', $base->dias_semana ?? [1, 2, 3, 4, 5]));
        @endphp
        <dialog class="modal" id="dialogo-jornada" aria-labelledby="t-jornada">
            <form method="POST" action="{{ route('frequencia.jornada', $servidor) }}">
                @csrf
                <input type="hidden" name="dialogo" value="dialogo-jornada">
                <input type="hidden" name="mes" value="{{ $mes->format('Y-m') }}">
                <div><h2 id="t-jornada" style="margin:0;font-size:18px">Alterar jornada</h2><p class="small muted" style="margin:6px 0 0">Vale a partir da data escolhida. A jornada anterior termina na véspera, e os meses passados continuam com a jornada da época.</p></div>
                <x-lista nome="modalidade" rotulo="Modalidade" :opcoes="Jornada::MODALIDADES" :valor="$base->modalidade" vazio="Escolha" obrigatorio />
                <x-campo nome="turnos" rotulo="Horário" :valor="Turnos::campo($base->turnos)" maxlength="60" placeholder="07:30-13:30" ajuda="Um ou mais turnos: 07:30-13:30 ou 08:00-12:00, 14:00-18:00. Só para jornada presencial." />
                <fieldset class="campo">
                    <legend class="rotulo">Dias de trabalho <span aria-hidden="true" style="color:#9F1D1D">*</span></legend>
                    <div class="dias-semana">
                        @foreach (Jornada::DIAS as $n => $nome)
                            <label class="check"><input type="checkbox" name="dias_semana[]" value="{{ $n }}" @checked(in_array($n, $diasMarcados, true))> {{ $nome }}</label>
                        @endforeach
                    </div>
                    @error('dias_semana')<span class="erro">{{ $message }}</span>@enderror
                </fieldset>
                <div class="form-grid">
                    <x-campo nome="inicio" rotulo="A partir de" tipo="date" :valor="today()->format('Y-m-d')" obrigatorio />
                    <x-campo nome="observacao" rotulo="Observação" maxlength="255" placeholder="Ex.: portaria de teletrabalho nº" />
                </div>
                <div class="form-acoes">
                    <button type="button" class="btn btn-s" data-fechar-dialogo>Voltar</button>
                    <button type="submit" class="btn btn-p">Salvar jornada</button>
                </div>
            </form>
        </dialog>

        @if ($reabrir && $errors->any() && in_array($reabrir, ['dialogo-incluir', 'dialogo-anular', 'dialogo-jornada'], true))
            <script type="module">document.getElementById(@json($reabrir)).showModal();</script>
        @endif
    @endif
</x-layouts.app>
