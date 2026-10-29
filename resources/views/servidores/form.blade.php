@php
    use App\Support\FichaSegad;
    $s = $servidor;
    $dt = fn (string $campo) => $s->{$campo}?->format('Y-m-d');
    $ufs = array_combine(FichaSegad::UFS, FichaSegad::UFS);
    $novo = ! $s->exists;
    $desligado = $s->exists && ! $s->ativo();
    $vagaAtual = old('vaga_id', $s->vaga_id ?? $vagaSugerida);
    // Mesma regra do controlador: a vaga só é exigida no cadastro novo e de quem já está numa vaga.
    $exigeVaga = $novo || ($s->ativo() && $s->vaga_id !== null);
    $etapas = [
        ['nomeacao', 'Nomeação e lotação', 'Ato, cargo e setor', 'Cabeçalho da ficha: o ato de nomeação, a vaga do quadro que o servidor ocupa e onde ele trabalha.'],
        ['pessoais', 'Dados pessoais', 'Identificação e saúde', 'Como estão na certidão e nos documentos. Sem abreviar nomes.'],
        ['documentos', 'Documentos', 'CPF, RG, título e outros', 'Os números ficam criptografados no banco e só o RH vê completos.'],
        ['formacao', 'Formação e vínculos', 'Escolaridade e outros vínculos', 'Maior grau concluído e vínculos com o serviço público.'],
        ['endereco', 'Endereço e banco', 'Contato e conta para pagamento', 'Onde o servidor mora, como falar com ele e a conta para o pagamento.'],
    ];
@endphp
<x-layouts.app :titulo="$novo ? 'Cadastrar servidor' : 'Editar '.$s->nome">
    <div class="row">
        <div>
            <a href="{{ $novo ? route('servidores.index') : route('servidores.show', $s) }}" class="small" style="font-weight:600;display:inline-flex;gap:4px;align-items:center"><x-icone nome="voltar" tamanho="16" />{{ $novo ? 'Servidores' : $s->nome }}</a>
            <h1 class="h1" style="margin-top:8px">{{ $novo ? 'Cadastro do servidor' : 'Editar cadastro' }}</h1>
            <p class="sub">Mesmos campos da Ficha de Cadastro enviada à SEGAD/RR. Campos com <span style="color:#9F1D1D">*</span> são obrigatórios na ficha. Dá para salvar com campos em branco e completar depois: a ficha fica com a pendência "Ficha incompleta".</p>
        </div>
        @if ($novo)
            <a href="{{ route('importacoes.create', ['tipo' => 'servidores']) }}" class="btn btn-s"><x-icone nome="planilha" />Importar da planilha</a>
        @endif
    </div>

    @if ($errors->any())
        <div class="flash flash-erro" role="alert"><x-icone nome="alerta" style="flex:none" /><div>Confira os campos marcados. {{ $errors->count() === 1 ? 'Há 1 campo' : 'Há '.$errors->count().' campos' }} para corrigir; a etapa com problema está em vermelho.</div></div>
    @endif

    <form method="POST" action="{{ $novo ? route('servidores.store', ['orgao' => request()->user()->orgao_id === null ? $s->orgao_id : null]) : route('servidores.update', $s) }}" data-etapas novalidate>
        @csrf
        @unless ($novo) @method('PUT') @endunless

        <div class="etapas-lay">
            <nav class="card etapas" aria-label="Etapas do cadastro">
                <div class="prog" aria-hidden="true"><span style="width:20%"></span></div>
                @foreach ($etapas as $i => [$id, $titulo, $resumo])
                    <button type="button" class="etapa-btn" data-ir-etapa="{{ $i }}"><span class="n">{{ $i + 1 }}</span><span><b>{{ $titulo }}</b><small>{{ $resumo }}</small></span></button>
                @endforeach
            </nav>

            <div class="card">
                {{-- 1. Nomeação e lotação --}}
                <section class="etapa" id="etapa-nomeacao" aria-labelledby="t-nomeacao">
                    <div class="etapa-cab"><div><h2 id="t-nomeacao" tabindex="-1">{{ $etapas[0][1] }}</h2><p>{{ $etapas[0][3] }}</p></div><span class="small muted" style="font-weight:600" data-contador-etapa>Etapa 1 de 5</span></div>

                    @if ($desligado)
                        <div class="flash flash-aviso" style="margin-bottom:18px"><x-icone nome="alerta" style="flex:none" /><div>Servidor desligado em {{ $s->data_desligamento?->format('d/m/Y') }}. Para readmitir, escolha uma vaga e informe o novo ato de nomeação.</div></div>
                    @endif

                    <p class="grupo">Cargo e lotação</p>
                    <div class="form-grid">
                        <x-campo nome="vaga_id" rotulo="Cargo e vaga do quadro" :obrigatorio="$exigeVaga" classe="full" ajuda="Aparecem as vagas livres e as ocupadas só na planilha, ainda sem servidor cadastrado.">
                            <select id="campo-vaga_id" name="vaga_id" class="input" @if ($exigeVaga) required data-sempre-exigido @endif @error('vaga_id') aria-invalid="true" @enderror>
                                <option value="">{{ $vagas->isEmpty() ? 'Nenhuma vaga livre no quadro' : 'Selecione o cargo e a vaga' }}</option>
                                @foreach ($vagas as $cargo => $lista)
                                    <optgroup label="{{ $cargo }}">
                                        @foreach ($lista as $vaga)
                                            <option value="{{ $vaga->id }}" @selected((int) $vagaAtual === $vaga->id)>
                                                Vaga nº {{ $vaga->numero }} ·
                                                @if ($vaga->id === $s->vaga_id) vaga atual deste servidor
                                                @elseif ($vaga->situacao === 'ocupada') ocupada na planilha por {{ $vaga->ocupante_nome }}
                                                @else livre @endif
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </x-campo>
                        <x-campo nome="unidade_id" rotulo="Lotação" obrigatorio>
                            <select id="campo-unidade_id" name="unidade_id" class="input" required @error('unidade_id') aria-invalid="true" @enderror>
                                <option value="">Selecione</option>
                                @foreach ($unidades as $u)
                                    <option value="{{ $u->id }}" @selected((int) old('unidade_id', $s->unidade_id) === $u->id)>{{ $u->sigla }} · {{ $u->nome }}</option>
                                @endforeach
                            </select>
                        </x-campo>
                        <x-campo nome="carga_horaria" rotulo="Carga horária" :valor="$s->carga_horaria" obrigatorio list="cargas" placeholder="6h diárias / 30h semanais" />
                        <datalist id="cargas">@foreach (FichaSegad::CARGAS_HORARIAS as $c)<option value="{{ $c }}">@endforeach</datalist>
                        <x-campo nome="matricula_comissionado" rotulo="Matrícula do comissionado" :valor="$s->matricula_comissionado" ajuda="Gerada pela SEGAD; pode ficar em branco até sair." />
                    </div>

                    <p class="grupo">Ato de nomeação</p>
                    <div class="form-grid">
                        <x-campo nome="decreto_nomeacao" rotulo="Decreto nº" :valor="$s->decreto_nomeacao" obrigatorio placeholder="1.388-P" />
                        <x-campo nome="data_decreto" rotulo="Data do decreto" tipo="date" :valor="$dt('data_decreto')" obrigatorio />
                        <x-campo nome="efeitos_a_partir_de" rotulo="Efeitos a partir de" tipo="date" :valor="$dt('efeitos_a_partir_de')" obrigatorio />
                        <x-campo nome="doe_numero" rotulo="DOE nº" :valor="$s->doe_numero" placeholder="0000" />
                        <x-campo nome="data_doe" rotulo="Data do DOE" tipo="date" :valor="$dt('data_doe')" />
                        <x-campo nome="processo_sei" rotulo="Processo SEI" :valor="$s->processo_sei" />
                    </div>
                    <div class="nota"><x-icone nome="cargos" tamanho="18" /><span>Ao salvar, a vaga escolhida passa a constar como ocupada no quadro de cargos, com o nome, o CPF e o ato deste servidor.</span></div>
                </section>

                {{-- 2. Dados pessoais --}}
                <section class="etapa" id="etapa-pessoais" aria-labelledby="t-pessoais">
                    <div class="etapa-cab"><div><h2 id="t-pessoais" tabindex="-1">{{ $etapas[1][1] }}</h2><p>{{ $etapas[1][3] }}</p></div><span class="small muted" style="font-weight:600" data-contador-etapa>Etapa 2 de 5</span></div>
                    <div class="form-grid">
                        <x-campo nome="nome" rotulo="Nome completo" :valor="$s->nome" obrigatorio classe="full" autocomplete="off" data-sempre-exigido />
                        <x-escolha nome="sexo" rotulo="Sexo" :opcoes="FichaSegad::SEXOS" :valor="$s->sexo" obrigatorio />
                        <x-lista nome="estado_civil" rotulo="Estado civil" :opcoes="FichaSegad::ESTADOS_CIVIS" :valor="$s->estado_civil" obrigatorio codigos />
                        <x-campo nome="data_nascimento" rotulo="Data de nascimento" tipo="date" :valor="$dt('data_nascimento')" obrigatorio />
                        <x-campo nome="nacionalidade" rotulo="Nacionalidade" :valor="$s->nacionalidade" obrigatorio list="nacionalidades" />
                        <datalist id="nacionalidades"><option value="Brasileira"><option value="Venezuelana"><option value="Guianense"></datalist>
                        <x-campo nome="naturalidade" rotulo="Naturalidade" :valor="$s->naturalidade" obrigatorio placeholder="Boa Vista/RR" />
                        <x-campo nome="nome_mae" rotulo="Nome da mãe" :valor="$s->nome_mae" obrigatorio />
                        <x-campo nome="nome_pai" rotulo="Nome do pai" :valor="$s->nome_pai" />
                    </div>
                    <p class="grupo">Dados sensíveis</p>
                    <div class="form-grid">
                        <x-lista nome="raca_cor" rotulo="Raça/cor (autodeclarada)" :opcoes="FichaSegad::RACAS" :valor="$s->raca_cor" obrigatorio codigos />
                        <x-lista nome="tipo_sanguineo" rotulo="Tipo sanguíneo/RH" :opcoes="array_combine(FichaSegad::TIPOS_SANGUINEOS, FichaSegad::TIPOS_SANGUINEOS)" :valor="$s->tipo_sanguineo" />
                        <x-escolha nome="pcd" rotulo="Pessoa com deficiência" :valor="$s->pcd" obrigatorio />
                        <div data-mostrar-se="pcd:1">
                            <x-lista nome="tipo_deficiencia" rotulo="Tipo de deficiência" :opcoes="array_diff_key(FichaSegad::DEFICIENCIAS, [99 => 1])" :valor="$s->tipo_deficiencia === 99 ? null : $s->tipo_deficiencia" codigos />
                        </div>
                        <x-escolha nome="molestia_grave" rotulo="Possui moléstia grave?" :valor="$s->molestia_grave" obrigatorio ajuda="Só sim ou não. Não informe o diagnóstico." />
                    </div>
                    <div class="nota"><x-icone nome="cadeado" tamanho="18" /><span>Raça/cor, deficiência, moléstia grave e tipo sanguíneo são dados sensíveis (LGPD, art. 11). Só o RH e a administração do órgão veem; na trilha de auditoria aparecem como "[protegido]".</span></div>
                </section>

                {{-- 3. Documentos --}}
                <section class="etapa" id="etapa-documentos" aria-labelledby="t-documentos">
                    <div class="etapa-cab"><div><h2 id="t-documentos" tabindex="-1">{{ $etapas[2][1] }}</h2><p>{{ $etapas[2][3] }}</p></div><span class="small muted" style="font-weight:600" data-contador-etapa>Etapa 3 de 5</span></div>
                    <div class="form-grid">
                        <x-campo nome="cpf" rotulo="CPF" :valor="$s->cpfFormatado()" obrigatorio data-sempre-exigido inputmode="numeric" data-mascara="cpf" placeholder="000.000.000-00" autocomplete="off" />
                        <x-campo nome="pis_pasep" rotulo="PIS/PASEP" :valor="$s->pis_pasep" obrigatorio autocomplete="off" />
                    </div>
                    <p class="grupo">Identidade</p>
                    <div class="form-grid">
                        <x-campo nome="rg_numero" rotulo="Identidade nº" :valor="$s->rg_numero" obrigatorio autocomplete="off" />
                        <x-campo nome="rg_orgao_uf" rotulo="Órgão expedidor/UF" :valor="$s->rg_orgao_uf" obrigatorio placeholder="SSP/RR" />
                        <x-campo nome="rg_data_expedicao" rotulo="Data de expedição" tipo="date" :valor="$dt('rg_data_expedicao')" obrigatorio />
                    </div>
                    <p class="grupo">Título de eleitor</p>
                    <div class="form-grid">
                        <x-campo nome="titulo_numero" rotulo="Título nº" :valor="$s->titulo_numero" obrigatorio autocomplete="off" />
                        <x-campo nome="titulo_zona" rotulo="Zona" :valor="$s->titulo_zona" obrigatorio />
                        <x-campo nome="titulo_secao" rotulo="Seção" :valor="$s->titulo_secao" obrigatorio />
                        <x-campo nome="titulo_data_expedicao" rotulo="Expedição" tipo="date" :valor="$dt('titulo_data_expedicao')" />
                        <x-campo nome="titulo_cidade" rotulo="Cidade de votação" :valor="$s->titulo_cidade" obrigatorio />
                        <x-lista nome="titulo_uf" rotulo="UF de votação" :opcoes="$ufs" :valor="$s->titulo_uf" obrigatorio />
                    </div>
                    <div data-mostrar-se="sexo:M">
                        <p class="grupo">Reservista · obrigatório para o sexo masculino</p>
                        <div class="form-grid">
                            <x-campo nome="reservista_numero" rotulo="Reservista nº" :valor="$s->reservista_numero" autocomplete="off" />
                            <x-campo nome="reservista_orgao_uf" rotulo="Órgão expedidor/UF" :valor="$s->reservista_orgao_uf" />
                            <x-campo nome="reservista_data_expedicao" rotulo="Data de expedição" tipo="date" :valor="$dt('reservista_data_expedicao')" />
                            <x-lista nome="reservista_categoria" rotulo="Categoria da reserva" :opcoes="FichaSegad::CATEGORIAS_RESERVA" :valor="$s->reservista_categoria" />
                            <x-campo nome="reservista_ano" rotulo="Ano de reserva" tipo="number" :valor="$s->reservista_ano" min="1900" max="2100" placeholder="AAAA" />
                        </div>
                    </div>
                    <p class="grupo">Carteira de trabalho · apenas empregado público</p>
                    <div class="form-grid">
                        <x-campo nome="ctps_numero" rotulo="CTPS nº" :valor="$s->ctps_numero" autocomplete="off" />
                        <x-campo nome="ctps_serie" rotulo="Série" :valor="$s->ctps_serie" />
                        <x-lista nome="ctps_uf" rotulo="UF" :opcoes="$ufs" :valor="$s->ctps_uf" />
                        <x-campo nome="ctps_data_expedicao" rotulo="Expedição" tipo="date" :valor="$dt('ctps_data_expedicao')" />
                    </div>
                    <p class="grupo">Habilitação · apenas motoristas</p>
                    <div class="form-grid">
                        <x-campo nome="cnh_numero" rotulo="CNH nº" :valor="$s->cnh_numero" autocomplete="off" />
                        <x-lista nome="cnh_categoria" rotulo="Categoria" :opcoes="array_combine(FichaSegad::CATEGORIAS_CNH, FichaSegad::CATEGORIAS_CNH)" :valor="$s->cnh_categoria" />
                        <x-lista nome="cnh_uf" rotulo="UF" :opcoes="$ufs" :valor="$s->cnh_uf" />
                        <x-campo nome="cnh_data_expedicao" rotulo="Expedição" tipo="date" :valor="$dt('cnh_data_expedicao')" />
                        <x-campo nome="cnh_data_validade" rotulo="Validade" tipo="date" :valor="$dt('cnh_data_validade')" />
                        <x-campo nome="cnh_data_primeira" rotulo="Primeira habilitação" tipo="date" :valor="$dt('cnh_data_primeira')" />
                    </div>
                    <div data-mostrar-se="nacionalidade:!Brasileira">
                        <p class="grupo">Estrangeiro</p>
                        <div class="form-grid">
                            <x-campo nome="estrangeiro_rne" rotulo="Registro Nacional de Estrangeiro" :valor="$s->estrangeiro_rne" autocomplete="off" />
                            <x-campo nome="estrangeiro_data_chegada" rotulo="Data de chegada" tipo="date" :valor="$dt('estrangeiro_data_chegada')" />
                            <x-campo nome="estrangeiro_ano_chegada" rotulo="Ano de chegada" tipo="number" :valor="$s->estrangeiro_ano_chegada" min="1900" max="2100" />
                            <x-campo nome="estrangeiro_data_limite" rotulo="Data limite de permanência" tipo="date" :valor="$dt('estrangeiro_data_limite')" />
                        </div>
                    </div>
                </section>

                {{-- 4. Formação e vínculos --}}
                <section class="etapa" id="etapa-formacao" aria-labelledby="t-formacao">
                    <div class="etapa-cab"><div><h2 id="t-formacao" tabindex="-1">{{ $etapas[3][1] }}</h2><p>{{ $etapas[3][3] }}</p></div><span class="small muted" style="font-weight:600" data-contador-etapa>Etapa 4 de 5</span></div>
                    <p class="grupo">Escolaridade</p>
                    <div class="form-grid">
                        <x-lista nome="grau_instrucao" rotulo="Grau de instrução" :opcoes="FichaSegad::ESCOLARIDADES" :valor="$s->grau_instrucao" obrigatorio codigos />
                        <x-campo nome="curso" rotulo="Curso" :valor="$s->curso" />
                        <x-campo nome="instituicao_curso" rotulo="Órgão ou instituição" :valor="$s->instituicao_curso" />
                    </div>
                    <p class="grupo">Vínculos e histórico</p>
                    <div class="form-grid">
                        <x-campo nome="ano_inicio_primeiro_emprego" rotulo="Ano de início do 1º emprego" tipo="number" :valor="$s->ano_inicio_primeiro_emprego" min="1900" max="2100" placeholder="AAAA" />
                        <x-campo nome="ano_fim_primeiro_emprego" rotulo="Ano de fim do 1º emprego" tipo="number" :valor="$s->ano_fim_primeiro_emprego" min="1900" max="2100" placeholder="AAAA" />
                        <x-escolha nome="vaga_pcd_ou_reabilitacao" rotulo="Ocupa vaga para deficiente ou reabilitação?" :valor="$s->vaga_pcd_ou_reabilitacao" obrigatorio />
                        <x-escolha nome="servidor_efetivo" rotulo="Servidor do quadro efetivo?" :valor="$s->servidor_efetivo" obrigatorio />
                        <div data-mostrar-se="servidor_efetivo:1"><x-campo nome="matricula_efetiva" rotulo="Matrícula efetiva" :valor="$s->matricula_efetiva" /></div>
                        <div data-mostrar-se="servidor_efetivo:1"><x-campo nome="cargo_efetivo" rotulo="Cargo efetivo" :valor="$s->cargo_efetivo" /></div>
                        <x-escolha nome="servidor_federal" rotulo="Servidor federal?" :valor="$s->servidor_federal" obrigatorio />
                        <div data-mostrar-se="servidor_federal:1"><x-campo nome="matricula_siape" rotulo="Matrícula SIAPE" :valor="$s->matricula_siape" /></div>
                    </div>
                </section>

                {{-- 5. Endereço e banco --}}
                <section class="etapa" id="etapa-endereco" aria-labelledby="t-endereco">
                    <div class="etapa-cab"><div><h2 id="t-endereco" tabindex="-1">{{ $etapas[4][1] }}</h2><p>{{ $etapas[4][3] }}</p></div><span class="small muted" style="font-weight:600" data-contador-etapa>Etapa 5 de 5</span></div>
                    <p class="grupo">Endereço e contatos</p>
                    <div class="form-grid">
                        <x-campo nome="cep" rotulo="CEP" :valor="$s->cep" obrigatorio inputmode="numeric" data-mascara="cep" placeholder="69300-000" />
                        <x-campo nome="logradouro" rotulo="Logradouro" :valor="$s->logradouro" obrigatorio placeholder="Rua, avenida etc." />
                        <x-campo nome="numero" rotulo="Número" :valor="$s->numero" obrigatorio />
                        <x-campo nome="complemento" rotulo="Complemento" :valor="$s->complemento" />
                        <x-campo nome="bairro" rotulo="Bairro" :valor="$s->bairro" obrigatorio />
                        <x-campo nome="municipio" rotulo="Município" :valor="$s->municipio" obrigatorio />
                        <x-lista nome="uf" rotulo="UF" :opcoes="$ufs" :valor="$s->uf" obrigatorio />
                        <x-campo nome="celular" rotulo="Celular com DDD" tipo="tel" :valor="$s->celular" obrigatorio data-mascara="celular" placeholder="(95) 90000-0000" />
                        <x-campo nome="email" rotulo="E-mail" tipo="email" :valor="$s->email" obrigatorio />
                    </div>
                    <p class="grupo">Dados bancários</p>
                    <div class="form-grid">
                        <x-campo nome="banco_codigo" rotulo="Código do banco" :valor="$s->banco_codigo" obrigatorio inputmode="numeric" maxlength="3" placeholder="001" :data-bancos="json_encode(FichaSegad::BANCOS)" />
                        <x-campo nome="banco_nome" rotulo="Nome do banco" :valor="$s->banco_nome" />
                        <x-campo nome="agencia" rotulo="Agência com dígito" :valor="$s->agencia" obrigatorio autocomplete="off" />
                        <x-campo nome="conta_corrente" rotulo="Conta corrente com dígito" :valor="$s->conta_corrente" obrigatorio autocomplete="off" />
                    </div>
                    <div class="nota"><x-icone nome="cadeado" tamanho="18" /><span>Agência e conta ficam criptografadas no banco de dados.</span></div>
                </section>

                <div class="form-acoes">
                    <a href="{{ $novo ? route('servidores.index') : route('servidores.show', $s) }}" class="btn btn-s" style="margin-right:auto">Cancelar</a>
                    <button type="button" class="btn btn-s" data-anterior hidden><x-icone nome="voltar" tamanho="18" />Anterior</button>
                    <button type="button" class="btn btn-s" data-proxima hidden>Próxima etapa<x-icone nome="seta" tamanho="18" /></button>
                    <button type="submit" class="btn btn-p"><x-icone nome="ok" tamanho="18" />{{ $novo ? 'Cadastrar servidor' : 'Salvar alterações' }}</button>
                </div>
            </div>
        </div>
    </form>
</x-layouts.app>
