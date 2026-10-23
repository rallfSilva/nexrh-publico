@php
    use App\Support\FichaSegad;
    $s = $servidor;
    $propria ??= false;
    $d = fn ($data) => $data?->format('d/m/Y');
    $sn = fn ($v) => $v === null ? null : ($v ? 'Sim' : 'Não');
    $cargo = $s->vaga?->cargo;
    $gerencia = ! $propria && auth()->user()->can('servidores.gerenciar');
    // Dossiê e dependentes: RH e administração (completo) ou o próprio servidor.
    $gerenciaDossie = ! $propria && auth()->user()->can('gerenciar-dossie');
    $podeEnviar = $s->ativo() && ($propria || $gerenciaDossie);
    // Férias: o RH marca, cancela e interrompe; a chefia (equipe) e o RH aprovam, nunca as próprias.
    $gerenciaFerias = ! $propria && auth()->user()->can('ferias.gerenciar');
    $aprovaFerias = ! $propria && auth()->user()->can('aprovar-ferias') && $s->user_id !== auth()->id();
    $declaracoes = $s->declaracoes->groupBy('tipo');
    $campo = function (string $rotulo, $valor) {
        $vazio = $valor === null || $valor === '';
        return '<div><dt>'.e($rotulo).'</dt><dd'.($vazio ? ' class="vazio-campo"' : '').'>'.($vazio ? 'Não informado' : e($valor)).'</dd></div>';
    };
@endphp
<x-layouts.app :titulo="$propria ? 'Minha ficha' : $s->nome">
    @unless ($propria)
        <a href="{{ route('servidores.index') }}" class="small" style="font-weight:600;display:inline-flex;gap:4px;align-items:center;margin-bottom:-8px"><x-icone nome="voltar" tamanho="16" />Servidores</a>
    @endunless

    <section class="card ficha-hero">
        <div class="faixa" aria-hidden="true"></div>
        <div class="corpo">
            <div class="grande" aria-hidden="true">{{ $s->iniciais() }}</div>
            <div class="quem">
                <h1>{{ $s->nome }}</h1>
                <p>
                    @if ($cargo){{ $cargo->denominacao }} · <span class="sigla">{{ $cargo->simbolo }}</span>@else Sem cargo vinculado @endif
                    @if ($s->unidade) · {{ $s->unidade->nome }} ({{ $s->unidade->sigla }})@endif
                </p>
                <div class="tags">
                    <span class="b {{ $s->ativo() ? 'b-ok' : 'b-mute' }}">{{ $s->ativo() ? 'Ativo' : 'Desligado em '.$d($s->data_desligamento) }}</span>
                    <span class="b b-info">{{ $s->rotuloVinculo() }}</span>
                    @if ($s->matricula())<span class="b b-mute">Matrícula {{ $s->matricula() }}</span>@endif
                    @if ($s->ingresso())<span class="b b-mute">No órgão desde {{ $d($s->ingresso()) }}</span>@endif
                    @if ($s->usuario)<span class="b b-mute"><x-icone nome="cadeado" tamanho="12" />Tem acesso ao sistema</span>@endif
                </div>
            </div>
            <div class="btns nao-imprimir">
                @if ($propria)
                    <a href="{{ route('minha-ficha.pdf') }}" class="btn btn-p"><x-icone nome="download" />Baixar minha ficha em PDF</a>
                @else
                    @can('servidores.dados_pessoais')
                        <a href="{{ route('servidores.pdf', $s) }}" class="btn btn-s"><x-icone nome="download" />Ficha em PDF</a>
                    @endcan
                    @if ($gerencia)
                        <a href="{{ route('servidores.edit', $s) }}" class="btn btn-p"><x-icone nome="editar" />{{ $s->ativo() ? 'Editar cadastro' : 'Editar ou readmitir' }}</a>
                    @endif
                @endif
            </div>
        </div>
    </section>

    <div class="ficha-lay">
        <section class="card">
            <div class="abas" role="tablist" data-abas>
                <a role="tab" class="aba" href="?aba=funcionais" data-aba="funcionais" aria-controls="funcionais">Funcionais</a>
                <a role="tab" class="aba" href="?aba=pessoais" data-aba="pessoais" aria-controls="pessoais">Pessoais</a>
                <a role="tab" class="aba" href="?aba=declaracoes" data-aba="declaracoes" aria-controls="declaracoes">Declarações<span class="n">{{ $declaracoes->count() }}/3</span></a>
                @if ($completo)
                    <a role="tab" class="aba" href="?aba=dossie" data-aba="dossie" aria-controls="dossie">Dossiê<span class="n">{{ $s->documentos->where('situacao', '!=', 'arquivado')->count() }}</span></a>
                    <a role="tab" class="aba" href="?aba=dependentes" data-aba="dependentes" aria-controls="dependentes">Dependentes<span class="n">{{ $s->dependentes->filter->ativo()->count() }}</span></a>
                @endif
                <a role="tab" class="aba" href="?aba=ferias" data-aba="ferias" aria-controls="ferias">Férias
                    @if ($s->ferias->where('situacao', 'programada')->isNotEmpty())<span class="n n-alerta">{{ $s->ferias->where('situacao', 'programada')->count() }}</span>@endif
                </a>
                <a role="tab" class="aba" href="?aba=historico" data-aba="historico" aria-controls="historico">Histórico</a>
            </div>

            {{-- Dados funcionais --}}
            <div class="painel-aba" id="funcionais" role="tabpanel">
                <dl class="dl">
                    {!! $campo('Cargo', $cargo?->denominacao) !!}
                    {!! $campo('Símbolo e remuneração', $cargo ? $cargo->simbolo.($cargo->remuneracao ? ' · R$ '.number_format((float) $cargo->remuneracao, 2, ',', '.') : '') : null) !!}
                    {!! $campo('Vaga do quadro', $s->vaga ? 'Nº '.$s->vaga->numero.($cargo?->base_legal ? ' · '.$cargo->base_legal : '') : null) !!}
                    {!! $campo('Lotação', $s->unidade ? $s->unidade->sigla.' · '.$s->unidade->nome : null) !!}
                    {!! $campo('Chefia imediata', $s->unidade?->chefe?->name) !!}
                    {!! $campo('Carga horária', $s->carga_horaria) !!}
                    {!! $campo('Ato de nomeação', $s->decreto_nomeacao ? 'Decreto nº '.$s->decreto_nomeacao.' de '.$d($s->data_decreto) : null) !!}
                    {!! $campo('Publicação no DOE', $s->doe_numero || $s->data_doe ? trim('nº '.($s->doe_numero ?? '—').' de '.($d($s->data_doe) ?? '—')) : null) !!}
                    {!! $campo('Efeitos a partir de', $d($s->efeitos_a_partir_de)) !!}
                    {!! $campo('Processo SEI', $s->processo_sei) !!}
                    {!! $campo('Matrícula do comissionado', $s->matricula_comissionado) !!}
                    {!! $campo('Vínculo efetivo', $s->servidor_efetivo ? trim(($s->cargo_efetivo ?? 'Sim').($s->matricula_efetiva ? ' · matrícula '.$s->matricula_efetiva : '')) : 'Não') !!}
                    {!! $campo('Servidor federal', $s->servidor_federal ? 'Sim'.($s->matricula_siape ? ' · SIAPE '.$s->matricula_siape : '') : 'Não') !!}
                    {!! $campo('Vaga para deficiente ou reabilitação', $sn($s->vaga_pcd_ou_reabilitacao)) !!}
                    @unless ($s->ativo())
                        {!! $campo('Desligamento', $d($s->data_desligamento).' · '.$s->motivo_desligamento.($s->ato_desligamento ? ' · ato '.$s->ato_desligamento : '')) !!}
                    @endunless
                </dl>
            </div>

            {{-- Dados pessoais --}}
            <div class="painel-aba" id="pessoais" role="tabpanel">
                <p class="secao">Identificação</p>
                <dl class="dl">
                    {!! $campo('CPF', $completo ? $s->cpfFormatado() : $s->cpfMascarado()) !!}
                    {!! $campo('Data de nascimento', $d($s->data_nascimento)) !!}
                    {!! $campo('Sexo', FichaSegad::SEXOS[$s->sexo] ?? null) !!}
                    {!! $campo('Estado civil', FichaSegad::ESTADOS_CIVIS[$s->estado_civil] ?? null) !!}
                    {!! $campo('Nacionalidade', $s->nacionalidade) !!}
                    {!! $campo('Naturalidade', $s->naturalidade) !!}
                    {!! $campo('Nome da mãe', $s->nome_mae) !!}
                    {!! $campo('Nome do pai', $s->nome_pai) !!}
                    {!! $campo('Escolaridade', FichaSegad::ESCOLARIDADES[$s->grau_instrucao] ?? null) !!}
                    {!! $campo('Curso', trim(($s->curso ?? '').($s->instituicao_curso ? ' · '.$s->instituicao_curso : ''), ' ·') ?: null) !!}
                    {!! $campo('Celular', $s->celular) !!}
                    {!! $campo('E-mail', $s->email) !!}
                </dl>

                @if ($completo)
                    <p class="secao">Documentos</p>
                    <dl class="dl">
                        {!! $campo('PIS/PASEP', $s->pis_pasep) !!}
                        {!! $campo('Identidade', $s->rg_numero ? $s->rg_numero.' · '.$s->rg_orgao_uf.' · '.$d($s->rg_data_expedicao) : null) !!}
                        {!! $campo('Título de eleitor', $s->titulo_numero ? $s->titulo_numero.' · zona '.$s->titulo_zona.' · seção '.$s->titulo_secao : null) !!}
                        {!! $campo('Votação', $s->titulo_cidade ? $s->titulo_cidade.'/'.$s->titulo_uf : null) !!}
                        @if ($s->sexo === 'M' || $s->reservista_numero)
                            {!! $campo('Reservista', $s->reservista_numero ? $s->reservista_numero.($s->reservista_categoria ? ' · '.FichaSegad::CATEGORIAS_RESERVA[$s->reservista_categoria] : '') : null) !!}
                        @endif
                        @if ($s->ctps_numero){!! $campo('CTPS', $s->ctps_numero.' · série '.$s->ctps_serie.' · '.$s->ctps_uf) !!}@endif
                        @if ($s->cnh_numero){!! $campo('CNH', $s->cnh_numero.' · categoria '.$s->cnh_categoria.' · validade '.$d($s->cnh_data_validade)) !!}@endif
                        @if ($s->estrangeiro_rne){!! $campo('RNE', $s->estrangeiro_rne.' · permanência até '.($d($s->estrangeiro_data_limite) ?? '—')) !!}@endif
                    </dl>

                    <p class="secao">Endereço e banco</p>
                    <dl class="dl">
                        {!! $campo('Endereço', trim($s->logradouro.', '.$s->numero.($s->complemento ? ' · '.$s->complemento : ''))) !!}
                        {!! $campo('Bairro', $s->bairro) !!}
                        {!! $campo('Município', $s->municipio.'/'.$s->uf.' · CEP '.$s->cep) !!}
                        {!! $campo('Banco', $s->banco_codigo.($s->banco_nome ? ' · '.$s->banco_nome : '')) !!}
                        {!! $campo('Agência e conta', $s->agencia.' · '.$s->conta_corrente) !!}
                    </dl>

                    <p class="secao">Dados sensíveis (LGPD)</p>
                    <dl class="dl">
                        {!! $campo('Raça/cor', FichaSegad::RACAS[$s->raca_cor] ?? null) !!}
                        {!! $campo('Pessoa com deficiência', $s->pcd ? 'Sim · '.(FichaSegad::DEFICIENCIAS[$s->tipo_deficiencia] ?? '') : 'Não') !!}
                        {!! $campo('Moléstia grave', $sn($s->molestia_grave)) !!}
                        {!! $campo('Tipo sanguíneo', $s->tipo_sanguineo) !!}
                    </dl>
                @else
                    <p class="secao">Documentos, endereço, banco e dados sensíveis</p>
                    <div class="restrito"><x-icone nome="cadeado" />Visíveis só para o RH e a administração do órgão.</div>
                @endif
            </div>

            {{-- Declarações --}}
            <div class="painel-aba" id="declaracoes" role="tabpanel">
                @foreach (\App\Models\Declaracao::TIPOS as $tipo => $rotulo)
                    @php($ultima = $declaracoes->get($tipo)?->first())
                    <div class="decl">
                        <span class="chip {{ $ultima ? 'c-teal' : 'c-warn' }}" style="width:36px;height:36px"><x-icone :nome="$ultima ? 'ok' : 'alerta'" tamanho="18" /></span>
                        <div class="txt">
                            <b>{{ $rotulo }}</b>
                            @if ($ultima)
                                <small>{{ $completo ? $ultima->resumo() : 'Registrada' }} · assinada em {{ $d($ultima->data_declaracao) }}</small>
                                @if ($declaracoes->get($tipo)->count() > 1)
                                    <small>{{ $declaracoes->get($tipo)->count() - 1 }} declaração(ões) anterior(es) guardada(s) como histórico.</small>
                                @endif
                            @else
                                <small>Ainda não registrada.</small>
                            @endif
                        </div>
                        @if ($gerencia)
                            <a href="{{ route('declaracoes.create', [$s, $tipo]) }}" class="btn btn-s btn-sm">{{ $ultima ? 'Nova versão' : 'Registrar' }}</a>
                        @endif
                    </div>
                @endforeach
                <div class="nota"><x-icone nome="cadeado" tamanho="18" /><span>Nomes de parentes, bens e valores ficam criptografados. A ficha em PDF traz as três declarações prontas para assinatura.</span></div>
            </div>

            @if ($completo)
                <div class="painel-aba" id="dossie" role="tabpanel">@include('servidores.partes.dossie')</div>
                <div class="painel-aba" id="dependentes" role="tabpanel">@include('servidores.partes.dependentes')</div>
            @endif

            <div class="painel-aba" id="ferias" role="tabpanel">@include('servidores.partes.ferias')</div>

            {{-- Linha do tempo --}}
            <div class="painel-aba" id="historico" role="tabpanel">
                @if ($s->eventos->isEmpty())
                    <div class="vazio"><b>Nenhum evento ainda.</b></div>
                @else
                    <ul class="tl">
                        @foreach ($s->eventos as $e)
                            <li>
                                <span class="dt">{{ $d($e->data) }}</span><span class="pt" aria-hidden="true"></span>
                                <div><b>{{ $e->descricao }}</b><small>{{ collect([$e->detalhe, $e->usuario ? 'registrado por '.$e->usuario->name : null])->filter()->implode(' · ') }}</small></div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        <aside>
            <section class="card">
                <h2>Pendências</h2>
                <div class="pend">
                    @forelse ($pendencias as $p)
                        <div class="pd"><x-icone nome="alerta" tamanho="18" /><span><b>{{ $p['titulo'] }}</b><small>{{ $p['detalhe'] }}</small></span></div>
                    @empty
                        <div class="pd ok"><x-icone nome="ok" tamanho="18" /><span><b>Ficha em dia</b><small>Vaga, lotação e as três declarações registradas.</small></span></div>
                    @endforelse
                </div>
            </section>

            @if ($gerencia && $s->ativo())
                <section class="card nao-imprimir">
                    <h2>Ações</h2>
                    <div class="acoes-lista">
                        @foreach (\App\Models\Declaracao::TIPOS as $tipo => $rotulo)
                            <a href="{{ route('declaracoes.create', [$s, $tipo]) }}" class="btn btn-s"><x-icone nome="pasta" tamanho="18" />{{ str_replace('Declaração de ', 'Declarar ', $rotulo) }}</a>
                        @endforeach
                        @if ($gerenciaDossie)
                            <button type="button" class="btn btn-s" data-abrir-dialogo="dialogo-documento"><x-icone nome="enviar" tamanho="18" />Incluir documento no dossiê</button>
                            <a href="{{ route('dependentes.create', $s) }}" class="btn btn-s"><x-icone nome="familia" tamanho="18" />Cadastrar dependente</a>
                        @endif
                        @if ($gerenciaFerias)
                            <a href="{{ route('ferias.create', $s) }}" class="btn btn-s"><x-icone nome="sol" tamanho="18" />Marcar férias</a>
                            <a href="{{ route('afastamentos.create', $s) }}" class="btn btn-s"><x-icone nome="calendario" tamanho="18" />Registrar afastamento</a>
                        @endif
                        <button type="button" class="btn btn-d" data-abrir-dialogo="dialogo-desligar"><x-icone nome="sair" tamanho="18" />Registrar desligamento</button>
                    </div>
                </section>
            @endif

            <p class="small muted" style="display:flex;gap:8px;margin:0"><x-icone nome="escudo" tamanho="16" style="flex:none" />
                @if ($propria)
                    Estes são os seus dados como estão no RH. Se algo estiver errado, avise o RH.
                @else
                    Cada acesso a esta ficha fica registrado na trilha de auditoria. CPF, documentos, dossiê e dependentes aparecem só para o RH e a administração do órgão.
                @endif
            </p>
        </aside>
    </div>

    @includeWhen($podeEnviar, 'servidores.partes.dialogos-dossie')
    @includeWhen($gerenciaDossie, 'servidores.partes.dialogos-rh')
    @includeWhen($gerenciaFerias || $aprovaFerias, 'servidores.partes.dialogos-ferias')

    @if ($gerencia && $s->ativo())
        <dialog class="modal" id="dialogo-desligar" aria-labelledby="t-desligar">
            <form method="POST" action="{{ route('servidores.desligar', $s) }}">
                @csrf
                <div><h2 id="t-desligar" style="margin:0;font-size:18px">Registrar desligamento</h2><p class="small muted" style="margin:6px 0 0">{{ $s->nome }} sai do órgão e a vaga {{ $s->vaga ? 'nº '.$s->vaga->numero.' de '.$cargo->denominacao : '' }} volta a ficar livre. O cadastro e o histórico continuam guardados.</p></div>
                <x-campo nome="data_desligamento" rotulo="Data do desligamento" tipo="date" :valor="now()->format('Y-m-d')" obrigatorio />
                <x-lista nome="motivo_desligamento" rotulo="Motivo" :opcoes="array_combine(\App\Models\Servidor::MOTIVOS_DESLIGAMENTO, \App\Models\Servidor::MOTIVOS_DESLIGAMENTO)" obrigatorio />
                <x-campo nome="ato_desligamento" rotulo="Ato (decreto ou portaria)" placeholder="Ex.: 1.420-P" />
                <div class="form-acoes">
                    <button type="button" class="btn btn-s" data-fechar-dialogo>Cancelar</button>
                    <button type="submit" class="btn btn-d">Confirmar desligamento</button>
                </div>
            </form>
        </dialog>
        @if ($errors->has('data_desligamento') || $errors->has('motivo_desligamento'))
            <script type="module">document.getElementById('dialogo-desligar').showModal();</script>
        @endif
    @endif
</x-layouts.app>
