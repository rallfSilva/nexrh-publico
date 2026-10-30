@php
    use App\Support\FichaSegad;
    $s = $servidor;
    $d = fn ($data) => $data?->format('d/m/Y');
    $sn = fn ($v) => $v === null ? null : ($v ? 'Sim' : 'Não');
    $cargo = $s->vaga?->cargo;
    $local = $s->orgao?->municipio && $s->orgao?->uf ? $s->orgao->municipio.'/'.$s->orgao->uf : config('nexrh.local_assinatura');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Ficha de Cadastro · {{ $s->nome }}</title>
<style>
    @page { margin: 28px 30px 40px; }
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.6px; color: #0F2A3A; margin: 0; }
    .topo { border-bottom: 2px solid #1E5470; padding-bottom: 6px; margin-bottom: 8px; }
    .topo table { width: 100%; border-collapse: collapse; }
    .topo .marca { width: 34px; height: 34px; display: block; }
    .topo h1 { font-size: 13px; margin: 0; color: #1E5470; letter-spacing: .02em; }
    .topo .org { font-size: 9px; color: #34729C; }
    .topo .dir { text-align: right; font-size: 8px; color: #4A6273; }
    h2 { font-size: 9.5px; margin: 10px 0 0; padding: 4px 7px; background: #1E5470; color: #fff; text-transform: uppercase; letter-spacing: .04em; }
    h3 { font-size: 8.4px; margin: 0; padding: 3px 7px; background: #D1ECFF; color: #1E5470; text-transform: uppercase; letter-spacing: .03em; border-left: 1px solid #9DBFD3; border-right: 1px solid #9DBFD3; }
    table.f { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.f td { border: 1px solid #9DBFD3; padding: 2px 5px 3px; vertical-align: top; height: 25px; }
    table.f td .r { display: block; font-size: 6.6px; color: #4A6273; text-transform: uppercase; letter-spacing: .02em; }
    table.f td .v { display: block; font-size: 8.8px; font-weight: bold; min-height: 11px; }
    .texto { border: 1px solid #9DBFD3; border-top: 0; padding: 6px 8px; line-height: 1.5; text-align: justify; }
    .assin { width: 100%; margin-top: 18px; border-collapse: collapse; }
    .assin td { text-align: center; font-size: 8px; padding-top: 22px; }
    .assin .linha { border-top: 1px solid #0F2A3A; margin: 0 26px; padding-top: 3px; }
    .quebra { page-break-before: always; }
    .rodape { position: fixed; bottom: -26px; left: 0; right: 0; font-size: 7px; color: #4A6273; border-top: 1px solid #CBEAEC; padding-top: 3px; }
    .rodape .pg:after { content: counter(page); }
    .rodape td { vertical-align: middle; }
    .rodape .mini { width: 11px; height: 11px; vertical-align: middle; }
    .rodape .nome { color: #1E5470; font-weight: bold; }
    table.lista { width: 100%; border-collapse: collapse; }
    table.lista th { background: #F3F7FA; font-size: 7px; text-transform: uppercase; color: #4A6273; text-align: left; border: 1px solid #9DBFD3; padding: 3px 5px; }
    table.lista td { border: 1px solid #9DBFD3; padding: 3px 5px; font-size: 8.4px; }
    .num { text-align: right; }
    .x { font-family: 'DejaVu Sans', sans-serif; }
</style>
</head>
<body>
@php
    /** Célula com rótulo pequeno em cima e valor embaixo, como na ficha impressa. */
    $c = function (string $rotulo, $valor, int $colspan = 1) {
        $v = $valor === null || $valor === '' ? '&nbsp;' : e($valor);
        return '<td colspan="'.$colspan.'"><span class="r">'.e($rotulo).'</span><span class="v">'.$v.'</span></td>';
    };
@endphp

<div class="rodape">
    <table style="width:100%"><tr>
        <td style="width:14px"><img src="{{ $logo }}" class="mini" alt=""></td>
        <td>Ficha de Cadastro · {{ $s->nome }} · CPF {{ $s->cpfFormatado() }} · gerada pelo <span class="nome">NexRH Público</span> em {{ $geradoEm->format('d/m/Y H:i') }}</td>
        <td style="text-align:right">Documento com dados pessoais (LGPD) · página <span class="pg"></span></td>
    </tr></table>
</div>

<div class="topo">
    <table><tr>
        <td style="width:42px"><img src="{{ $logo }}" class="marca" alt="NexRH Público"></td>
        <td>
            <h1>FICHA DE CADASTRO DO SERVIDOR</h1>
            <div class="org">{{ $s->orgao?->nome }} ({{ $s->orgao?->sigla }}) · para envio à SEGAD/RR</div>
        </td>
        <td class="dir">
            Decreto nº <b>{{ $s->decreto_nomeacao ?? '—' }}</b> de {{ $d($s->data_decreto) ?? '—' }}<br>
            DOE nº {{ $s->doe_numero ?? '—' }} de {{ $d($s->data_doe) ?? '—' }}<br>
            Efeitos a partir de <b>{{ $d($s->efeitos_a_partir_de) ?? '—' }}</b>
            @if ($s->processo_sei)<br>Processo SEI {{ $s->processo_sei }}@endif
        </td>
    </tr></table>
</div>

<h2>1. Dados cadastrais</h2>
<h3>Identificação</h3>
<table class="f">
    <tr>{!! $c('Nome completo', $s->nome, 5) !!}{!! $c('CPF', $s->cpfFormatado()) !!}</tr>
    <tr>{!! $c('Sexo', FichaSegad::SEXOS[$s->sexo] ?? $s->sexo) !!}{!! $c('Estado civil', $s->rotulo('ESTADOS_CIVIS', $s->estado_civil)) !!}{!! $c('Raça/cor', $s->rotulo('RACAS', $s->raca_cor)) !!}{!! $c('Data de nascimento', $d($s->data_nascimento)) !!}{!! $c('Tipo sanguíneo/RH', $s->tipo_sanguineo) !!}{!! $c('PIS/PASEP', $s->pis_pasep) !!}</tr>
    <tr>{!! $c('Nacionalidade', $s->nacionalidade) !!}{!! $c('Naturalidade', $s->naturalidade, 2) !!}{!! $c('Pessoa com deficiência', $sn($s->pcd)) !!}{!! $c('Tipo de deficiência', $s->rotulo('DEFICIENCIAS', $s->tipo_deficiencia)) !!}{!! $c('Moléstia grave', $sn($s->molestia_grave)) !!}</tr>
    <tr>{!! $c('Nome da mãe', $s->nome_mae, 3) !!}{!! $c('Nome do pai', $s->nome_pai, 3) !!}</tr>
</table>

<h3>Documentos</h3>
<table class="f">
    <tr>{!! $c('Identidade nº', $s->rg_numero, 2) !!}{!! $c('Órgão expedidor/UF', $s->rg_orgao_uf, 2) !!}{!! $c('Data de expedição', $d($s->rg_data_expedicao), 2) !!}</tr>
    <tr>{!! $c('Título de eleitor nº', $s->titulo_numero, 2) !!}{!! $c('Zona', $s->titulo_zona) !!}{!! $c('Seção', $s->titulo_secao) !!}{!! $c('Expedição', $d($s->titulo_data_expedicao)) !!}{!! $c('Cidade/UF de votação', trim(($s->titulo_cidade ?? '').'/'.($s->titulo_uf ?? ''), '/')) !!}</tr>
    <tr>{!! $c('Reservista nº', $s->reservista_numero) !!}{!! $c('Órgão/UF', $s->reservista_orgao_uf) !!}{!! $c('Expedição', $d($s->reservista_data_expedicao)) !!}{!! $c('Categoria', $s->rotulo('CATEGORIAS_RESERVA', $s->reservista_categoria), 2) !!}{!! $c('Ano de reserva', $s->reservista_ano) !!}</tr>
    <tr>{!! $c('CTPS nº', $s->ctps_numero, 2) !!}{!! $c('Série', $s->ctps_serie) !!}{!! $c('UF', $s->ctps_uf) !!}{!! $c('Expedição', $d($s->ctps_data_expedicao), 2) !!}</tr>
    <tr>{!! $c('CNH nº', $s->cnh_numero) !!}{!! $c('UF', $s->cnh_uf) !!}{!! $c('Categoria', $s->cnh_categoria) !!}{!! $c('Expedição', $d($s->cnh_data_expedicao)) !!}{!! $c('Validade', $d($s->cnh_data_validade)) !!}{!! $c('1ª habilitação', $d($s->cnh_data_primeira)) !!}</tr>
    @if ($s->estrangeiro_rne || $s->estrangeiro_data_chegada)
        <tr>{!! $c('Estrangeiro: RNE', $s->estrangeiro_rne, 2) !!}{!! $c('Data de chegada', $d($s->estrangeiro_data_chegada)) !!}{!! $c('Ano de chegada', $s->estrangeiro_ano_chegada) !!}{!! $c('Data limite de permanência', $d($s->estrangeiro_data_limite), 2) !!}</tr>
    @endif
</table>

<h3>Escolaridade e vínculos</h3>
<table class="f">
    <tr>{!! $c('Grau de instrução', $s->rotulo('ESCOLARIDADES', $s->grau_instrucao), 2) !!}{!! $c('Curso', $s->curso, 2) !!}{!! $c('Órgão/instituição', $s->instituicao_curso, 2) !!}</tr>
    <tr>{!! $c('Ano início do 1º emprego', $s->ano_inicio_primeiro_emprego) !!}{!! $c('Ano fim do 1º emprego', $s->ano_fim_primeiro_emprego) !!}{!! $c('Vaga para deficiente ou reabilitação', $sn($s->vaga_pcd_ou_reabilitacao), 2) !!}{!! $c('Servidor federal', $sn($s->servidor_federal)) !!}{!! $c('Matrícula SIAPE', $s->matricula_siape) !!}</tr>
    <tr>{!! $c('Servidor do quadro efetivo', $sn($s->servidor_efetivo)) !!}{!! $c('Matrícula efetiva', $s->matricula_efetiva) !!}{!! $c('Cargo efetivo', $s->cargo_efetivo, 4) !!}</tr>
</table>

<h3>Cargo e lotação</h3>
<table class="f">
    <tr>{!! $c('Cargo/função', $cargo?->denominacao, 3) !!}{!! $c('Cód. cargo/função', $cargo?->simbolo) !!}{!! $c('Matrícula comissionado', $s->matricula_comissionado) !!}{!! $c('Carga horária', $s->carga_horaria) !!}</tr>
    <tr>{!! $c('Lotação', $s->unidade ? $s->unidade->sigla.' · '.$s->unidade->nome : null, 6) !!}</tr>
</table>

<h3>Endereço, contato e dados bancários</h3>
<table class="f">
    <tr>{!! $c('Logradouro', $s->logradouro, 3) !!}{!! $c('Número', $s->numero) !!}{!! $c('Complemento', $s->complemento, 2) !!}</tr>
    <tr>{!! $c('Bairro', $s->bairro, 2) !!}{!! $c('Município', $s->municipio, 2) !!}{!! $c('UF', $s->uf) !!}{!! $c('CEP', $s->cep) !!}</tr>
    <tr>{!! $c('Celular', $s->celular, 2) !!}{!! $c('E-mail', $s->email, 4) !!}</tr>
    <tr>{!! $c('Banco', trim($s->banco_codigo.' · '.$s->banco_nome, ' ·'), 3) !!}{!! $c('Agência', $s->agencia, 1) !!}{!! $c('Conta corrente', $s->conta_corrente, 2) !!}</tr>
</table>

<h3>Dependentes</h3>
<table class="lista">
    <thead><tr>
        <th style="width:31%">Nome</th><th style="width:13%">CPF</th><th style="width:10%">Nascimento</th><th style="width:5%">Sexo</th>
        <th style="width:9%">Parentesco</th><th style="width:5%">IR</th><th style="width:7%">Previd.</th><th style="width:7%">Sal.-fam.</th><th style="width:5%">PcD</th><th style="width:8%">Univers.</th>
    </tr></thead>
    <tbody>
        @forelse ($dependentes as $dep)
            <tr>
                <td>{{ $dep->nome }}</td><td>{{ $dep->cpfFormatado() }}</td><td>{{ $d($dep->data_nascimento) }}</td><td>{{ $dep->sexo }}</td>
                <td>{{ $dep->parentesco }}</td><td>{{ $sn($dep->declarar_ir) }}</td><td>{{ $sn($dep->declarar_previdencia) }}</td><td>{{ $sn($dep->salario_familia) }}</td>
                <td>{{ $dep->pcd ? 'Sim ('.$dep->tipo_deficiencia.')' : 'Não' }}</td><td>{{ $sn($dep->universitario) }}</td>
            </tr>
        @empty
            <tr><td colspan="10" style="color:#4A6273">Sem dependentes cadastrados.</td></tr>
        @endforelse
    </tbody>
</table>

<table class="assin"><tr>
    <td style="width:40%"><div class="linha">Local e data</div></td>
    <td><div class="linha">Assinatura do(a) servidor(a)</div></td>
</tr></table>

{{-- 2. Parentesco --}}
<div class="quebra"></div>
<h2>2. Declaração de grau de parentesco</h2>
@php($p = $parentesco?->conteudo ?? [])
<div class="texto">
    Eu, <b>{{ $s->nome }}</b>, CPF <b>{{ $s->cpfFormatado() }}</b>,
    {{ mb_strtolower($p['vinculo_funcional'] ?? 'ocupante de função de confiança ou cargo em comissão') }},
    @if (! empty($p['cargo_efetivo'])) titular do cargo efetivo de <b>{{ $p['cargo_efetivo'] }}</b>, @endif
    nomeado(a) para o cargo em comissão de <b>{{ $p['cargo_comissao'] ?? $cargo?->denominacao ?? '________________' }}</b>,
    declaro, para fins do disposto na Súmula Vinculante nº 13 do Supremo Tribunal Federal, que
    <b>{{ $parentesco === null ? '( ) possuo  ( ) não possuo' : (! empty($p['possui_parente']) ? 'POSSUO' : 'NÃO POSSUO') }}</b>
    parente em linha reta, colateral ou por afinidade, até o 3º grau, ocupando cargo de direção, chefia ou assessoramento, ou função gratificada, na administração pública estadual, e que
    <b>{{ $parentesco === null ? '( ) possuo  ( ) não possuo' : (! empty($p['possui_conjuge']) ? 'POSSUO' : 'NÃO POSSUO') }}</b>
    cônjuge ou companheiro(a) nessa situação.
</div>
@if (! empty($p['parentes']) || ! empty($p['possui_conjuge']))
    <table class="lista">
        <tr><th>Nome</th><th>Cargo</th><th>Relação</th><th>Órgão</th></tr>
        @foreach ($p['parentes'] ?? [] as $par)
            <tr><td>{{ $par['nome'] }}</td><td>{{ $par['cargo'] }}</td><td>{{ $par['relacao'] }}</td><td>{{ $par['orgao'] }}</td></tr>
        @endforeach
        @if (! empty($p['possui_conjuge']))
            <tr><td>{{ $p['conjuge_nome'] }}</td><td>{{ $p['conjuge_cargo'] }}</td><td>Cônjuge ou companheiro(a)</td><td>{{ $p['conjuge_orgao'] }}</td></tr>
        @endif
    </table>
@endif
<table class="assin"><tr>
    <td style="width:40%"><div class="linha">{{ $parentesco ? $local.', '.$d($parentesco->data_declaracao) : 'Local e data' }}</div></td>
    <td><div class="linha">Assinatura do(a) declarante</div></td>
</tr></table>

{{-- 3. Acumulação --}}
<h2 style="margin-top:22px">3. Declaração de acumulação de cargos</h2>
@php($a = $acumulacao?->conteudo ?? [])
<div class="texto">
    Eu, <b>{{ $s->nome }}</b>, CPF <b>{{ $s->cpfFormatado() }}</b>, declaro, sob as penas da lei, que
    <b>{{ $acumulacao === null ? '( ) acumulo  ( ) não acumulo' : (! empty($a['acumula']) ? 'ACUMULO' : 'NÃO ACUMULO') }}</b>
    cargo, emprego ou função pública, nos termos do art. 37, XVI e XVII, da Constituição Federal, e que
    <b>{{ $acumulacao === null ? '( ) sou  ( ) não sou' : (! empty($a['aposentado']) ? 'SOU' : 'NÃO SOU') }}</b>
    aposentado(a).
</div>
<table class="f">
    <tr>{!! $c('Cargo acumulado', $a['cargo_acumulado'] ?? null, 2) !!}{!! $c('Instituição', $a['instituicao'] ?? null, 2) !!}{!! $c('Dias e horários do outro vínculo', $a['horario_trabalho'] ?? null, 2) !!}</tr>
    <tr>{!! $c('Cargo em que se aposentou', $a['cargo_aposentadoria'] ?? null, 3) !!}{!! $c('Órgão pagador dos proventos', $a['fonte_proventos'] ?? null, 3) !!}</tr>
</table>
<table class="assin"><tr>
    <td style="width:40%"><div class="linha">{{ $acumulacao ? $local.', '.$d($acumulacao->data_declaracao) : 'Local e data' }}</div></td>
    <td><div class="linha">Assinatura do(a) declarante</div></td>
</tr></table>

{{-- 4 e 5. Bens --}}
<div class="quebra"></div>
<h2>4 e 5. Declaração de bens do servidor e do cônjuge</h2>
@php($b = $bens?->conteudo ?? [])
<div class="texto">
    Eu, <b>{{ $s->nome }}</b>, CPF <b>{{ $s->cpfFormatado() }}</b>, em cumprimento ao art. 13 da Lei nº 8.429/1992, declaro
    @if ($bens === null)
        que ( ) possuo ( ) não possuo bens e valores que compõem o meu patrimônio privado, conforme relação abaixo, referente ao ano de ______.
    @elseif (! empty($b['possui_bens']))
        os bens e valores que compõem o meu patrimônio privado e o do meu cônjuge ou companheiro(a), referentes ao ano de <b>{{ $bens->ano_referencia }}</b>:
    @else
        que <b>NÃO POSSUO</b> bens a declarar, referente ao ano de <b>{{ $bens->ano_referencia }}</b>.
    @endif
</div>
<table class="lista">
    <tr><th style="width:22%">Titular</th><th>Discriminação do bem</th><th style="width:18%" class="num">Valor (R$)</th></tr>
    @forelse ($b['bens'] ?? [] as $bem)
        <tr><td>{{ FichaSegad::TITULARES_BEM[$bem['titular']] ?? $bem['titular'] }}</td><td>{{ $bem['discriminacao'] }}</td><td class="num">{{ number_format((float) $bem['valor'], 2, ',', '.') }}</td></tr>
    @empty
        @for ($i = 0; $i < 6; $i++)<tr><td>&nbsp;</td><td></td><td></td></tr>@endfor
    @endforelse
    @if (! empty($b['bens']))
        <tr><td colspan="2" class="num"><b>Total</b></td><td class="num"><b>{{ number_format(array_sum(array_column($b['bens'], 'valor')), 2, ',', '.') }}</b></td></tr>
    @endif
</table>
<table class="f" style="margin-top:6px">
    <tr>{!! $c('Cônjuge ou companheiro(a)', $b['conjuge_nome'] ?? null, 4) !!}{!! $c('CPF do cônjuge', isset($b['conjuge_cpf']) ? \App\Support\Cpf::formatar($b['conjuge_cpf']) : null, 2) !!}</tr>
</table>
<table class="assin"><tr>
    <td style="width:40%"><div class="linha">{{ $bens ? $local.', '.$d($bens->data_declaracao) : 'Local e data' }}</div></td>
    <td><div class="linha">Assinatura do(a) declarante</div></td>
</tr></table>
</body>
</html>
