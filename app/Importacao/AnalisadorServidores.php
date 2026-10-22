<?php

namespace App\Importacao;

use App\Models\Cargo;
use App\Models\Servidor;
use App\Models\Unidade;
use App\Servidores\RegrasDeclaracao;
use App\Servidores\RegrasFicha;
use App\Support\Cpf;
use App\Support\FichaSegad;
use Illuminate\Support\Facades\Validator;

/**
 * Lê a planilha modelo 03 (servidores) e confere cada linha com as mesmas regras
 * da tela de cadastro. Linhas com erro ficam de fora e aparecem no relatório;
 * as demais entram na prévia. Também lê as abas de declarações.
 *
 * Divergências: "bloqueia" (arquivo inteiro), "erro" (a linha não será importada)
 * e "aviso" (importa, mas vale conferir).
 */
class AnalisadorServidores
{
    /** Primeira linha de dados: a 1ª é o cabeçalho e a 2ª, a explicação de cada coluna. */
    private const PRIMEIRA_LINHA = 2;

    /** @var list<array{nivel: string, cargo: ?string, mensagem: string}> */
    private array $divergencias = [];

    public function __construct(private LeitorPlanilha $leitor = new LeitorPlanilha) {}

    /** @return array{formato: string, servidores: list<array<string, mixed>>, resumo: array<string, mixed>, divergencias: list<array<string, mixed>>} */
    public function analisar(string $caminho, int $orgaoId): array
    {
        $this->divergencias = [];
        $abas = $this->leitor->ler($caminho);

        $linhas = $this->tabela($abas['Servidores'] ?? []);
        if ($linhas === null) {
            $this->divergir('bloqueia', null, 'Formato não reconhecido. Use a planilha modelo 03 (aba "Servidores" com as colunas nome e cpf na primeira linha).');

            return $this->resultado('desconhecido', []);
        }
        if ($linhas === []) {
            $this->divergir('bloqueia', null, 'A aba "Servidores" não tem nenhuma linha preenchida a partir da 3ª linha.');

            return $this->resultado('modelo_servidores', []);
        }

        $unidades = Unidade::withoutGlobalScopes()->where('orgao_id', $orgaoId)->get()->keyBy(fn ($u) => Texto::chave($u->sigla));
        $cargos = Cargo::withoutGlobalScopes()->where('orgao_id', $orgaoId)->where('ativo', true)->get();
        $existentes = Servidor::withoutGlobalScopes()->where('orgao_id', $orgaoId)->get()->keyBy('cpf_hash');

        $declaracoes = $this->lerDeclaracoes($abas);
        $vistos = [];
        $servidores = [];

        foreach ($linhas as $numero => $l) {
            $rotulo = 'Linha '.$numero.($l['nome'] ? ' · '.$l['nome'] : '');
            $dados = $this->converter($l);
            $cpf = $dados['cpf'] ?? '';

            if (strlen($cpf) === 11 && isset($vistos[$cpf])) {
                $this->divergir('bloqueia', $rotulo, 'CPF repetido na planilha (também na linha '.$vistos[$cpf].'). Cada servidor deve aparecer uma vez.');

                continue;
            }
            $vistos[$cpf] = $numero;

            // Lotação e cargo vêm por sigla e nome; viram ids do órgão.
            $unidade = $unidades->get(Texto::chave($l['lotacao']));
            $dados['unidade_id'] = $unidade?->id;
            $cargo = $this->cargo($cargos, $l['cargo_funcao'], $l['cod_cargo_funcao']);

            $existente = Cpf::valido($cpf) ? $existentes->get(Cpf::hash($cpf)) : null;
            $regras = array_diff_key(RegrasFicha::regras($orgaoId, $existente, false), ['vaga_id' => 1]);
            // Reservista faltando não impede a importação: vira aviso e pendência na ficha.
            $regras['reservista_numero'] = ['nullable', 'string', 'max:30'];
            $validador = Validator::make($dados, $regras, RegrasFicha::mensagens(), RegrasFicha::nomes());

            if ($validador->fails()) {
                $erros = [];
                foreach ($validador->errors()->messages() as $campo => $msgs) {
                    $erros[] = $campo === 'unidade_id'
                        ? 'lotação "'.($l['lotacao'] ?? '').'" não existe no organograma'
                        : 'coluna "'.$campo.'": '.rtrim($msgs[0], '.');
                }
                $this->divergir('erro', $rotulo, 'Não será importada: '.implode('; ', array_slice($erros, 0, 4)).(count($erros) > 4 ? ' e mais '.(count($erros) - 4).' campo(s).' : '.'));

                continue;
            }

            $validos = $validador->validated();

            if ($cargo === null) {
                $this->divergir('aviso', $rotulo, 'Cargo "'.($l['cargo_funcao'] ?? '').' ('.($l['cod_cargo_funcao'] ?? '').')" não está no quadro. O servidor entra sem vaga; vincule depois na ficha.');
            }
            if ($dados['sexo'] === 'M' && empty($dados['reservista_numero'])) {
                $this->divergir('aviso', $rotulo, 'Reservista não informado (obrigatório para o sexo masculino). Fica como pendência na ficha.');
            }

            $servidores[] = [
                'linha' => $numero,
                'existente_id' => $existente?->id,
                'cargo_id' => $cargo?->id,
                'cargo_rotulo' => $cargo ? $cargo->denominacao.' ('.$cargo->simbolo.')' : null,
                'unidade_sigla' => $unidade?->sigla,
                'dados' => $validos,
                'declaracoes' => $this->declaracoesDoServidor($declaracoes, $cpf, $rotulo),
            ];
            unset($declaracoes[$cpf]);
        }

        if ($declaracoes !== []) {
            $this->divergir('aviso', 'Declarações', 'As declarações de '.count($declaracoes).' CPF(s) não serão importadas porque o servidor não está na aba Servidores ou a linha dele tem erro. Corrija a linha e envie de novo.');
        }

        return $this->resultado('modelo_servidores', $servidores, count($linhas));
    }

    // ---------------------------------------------------------------- leitura

    /**
     * Converte uma aba em [nº da linha no Excel => [coluna => valor]], usando os nomes
     * da primeira linha (sem o " *" dos obrigatórios). Null se não for o modelo 03.
     *
     * @param  list<list<mixed>>  $linhas
     * @return array<int, array<string, mixed>>|null
     */
    private function tabela(array $linhas): ?array
    {
        $cabecalho = array_map(fn ($c) => trim(str_replace('*', '', (string) $c)), $linhas[0] ?? []);

        if (! in_array('cpf', $cabecalho, true) && ! in_array('cpf_servidor', $cabecalho, true)) {
            return null;
        }

        $tabela = [];
        foreach (array_slice($linhas, self::PRIMEIRA_LINHA, preserve_keys: true) as $i => $linha) {
            $registro = [];
            foreach ($cabecalho as $c => $nome) {
                if ($nome !== '') {
                    $registro[$nome] = $linha[$c] ?? null;
                }
            }
            if (array_filter($registro, fn ($v) => Texto::valor($v) !== null) === []) {
                continue;
            }
            $tabela[$i + 1] = $registro + ['nome' => null, 'lotacao' => null, 'cargo_funcao' => null, 'cod_cargo_funcao' => null];
        }

        return $tabela;
    }

    /**
     * Valores da planilha no formato da tela: códigos das listas, datas Y-m-d,
     * números com zeros à esquerda (CPF, CEP, banco) e sim/não.
     *
     * @param  array<string, mixed>  $l
     * @return array<string, mixed>
     */
    private function converter(array $l): array
    {
        $texto = fn (string $c) => Texto::valor($l[$c] ?? null);
        $digitos = fn (string $c, int $tamanho) => ($v = $texto($c)) === null ? null : (preg_match('/^\d+$/', $v) ? str_pad($v, $tamanho, '0', STR_PAD_LEFT) : $v);

        $dados = [];
        foreach (array_keys(RegrasFicha::regras(0)) as $campo) {
            $dados[$campo] = $texto($campo);
        }
        unset($dados['vaga_id'], $dados['unidade_id']);

        foreach (['data_decreto', 'data_doe', 'efeitos_a_partir_de', 'data_nascimento', 'rg_data_expedicao', 'reservista_data_expedicao', 'titulo_data_expedicao', 'ctps_data_expedicao', 'cnh_data_expedicao', 'cnh_data_validade', 'cnh_data_primeira', 'estrangeiro_data_chegada', 'estrangeiro_data_limite'] as $campo) {
            $original = $l[$campo] ?? null;
            $dados[$campo] = Texto::data($original) ?? (Texto::valor($original) === null ? null : 'data inválida');
        }

        $dados['cpf'] = $digitos('cpf', 11);
        $dados['cep'] = ($cep = $digitos('cep', 8)) !== null && strlen($cep) === 8 ? substr($cep, 0, 5).'-'.substr($cep, 5) : $cep;
        $dados['banco_codigo'] = $digitos('banco_codigo', 3);
        $dados['estado_civil'] = FichaSegad::codigo(FichaSegad::ESTADOS_CIVIS, $texto('estado_civil')) ?? $texto('estado_civil');
        $dados['raca_cor'] = FichaSegad::codigo(FichaSegad::RACAS, $texto('raca_cor')) ?? $texto('raca_cor');
        $dados['tipo_deficiencia'] = FichaSegad::codigo(FichaSegad::DEFICIENCIAS, $texto('tipo_deficiencia')) ?? $texto('tipo_deficiencia');
        $dados['grau_instrucao'] = FichaSegad::codigo(FichaSegad::ESCOLARIDADES, $texto('grau_instrucao')) ?? $texto('grau_instrucao');
        $dados['reservista_categoria'] = FichaSegad::codigo(FichaSegad::CATEGORIAS_RESERVA, $texto('reservista_categoria')) ?? $texto('reservista_categoria');
        $dados['sexo'] = mb_strtoupper(mb_substr((string) $texto('sexo'), 0, 1)) ?: null;

        return RegrasFicha::normalizar($dados);
    }

    /** Cargo pelo nome e símbolo; na falta, pelo símbolo ou pelo nome, se só houver um. */
    private function cargo($cargos, ?string $nome, ?string $simbolo): ?Cargo
    {
        $n = Texto::chave($nome);
        $s = Texto::chave($simbolo);

        $ambos = $cargos->filter(fn ($c) => Texto::chave($c->denominacao) === $n && Texto::chave($c->simbolo) === $s);
        if ($ambos->count() === 1) {
            return $ambos->first();
        }

        foreach ([fn ($c) => Texto::chave($c->denominacao) === $n, fn ($c) => Texto::chave($c->simbolo) === $s] as $criterio) {
            $achados = $cargos->filter($criterio);
            if ($achados->count() === 1 && ($n !== '' || $s !== '')) {
                return $achados->first();
            }
        }

        return null;
    }

    /**
     * Declarações agrupadas por CPF do servidor.
     *
     * @param  array<string, list<list<mixed>>>  $abas
     * @return array<string, array<string, list<array<string, mixed>>>>
     */
    private function lerDeclaracoes(array $abas): array
    {
        $porCpf = [];

        foreach (['parentesco' => 'Parentesco', 'acumulacao' => 'Acumulação', 'bens' => 'Bens'] as $tipo => $aba) {
            foreach ($this->tabela($abas[$aba] ?? []) ?? [] as $numero => $l) {
                $cpf = str_pad((string) Texto::valor($l['cpf_servidor'] ?? null), 11, '0', STR_PAD_LEFT);
                $porCpf[Cpf::somenteDigitos($cpf)][$tipo][] = $l + ['_linha' => $numero];
            }
        }

        return $porCpf;
    }

    /**
     * Monta e confere as declarações de um servidor.
     *
     * @param  array<string, array<string, list<array<string, mixed>>>>  $todas
     * @return list<array{tipo: string, data_declaracao: string, ano_referencia: ?int, conteudo: array<string, mixed>}>
     */
    private function declaracoesDoServidor(array $todas, string $cpf, string $rotulo): array
    {
        $resultado = [];
        $doServidor = $todas[$cpf] ?? [];
        $sn = fn ($v) => Texto::valor($v);

        if ($linhas = $doServidor['parentesco'] ?? null) {
            $p = $linhas[0];
            $resultado[] = ['parentesco', [
                'data_declaracao' => Texto::data($p['data_declaracao'] ?? null),
                'vinculo_funcional' => Texto::valor($p['vinculo_funcional'] ?? null),
                'cargo_efetivo' => Texto::valor($p['cargo_efetivo'] ?? null),
                'cargo_comissao' => Texto::valor($p['cargo_comissao'] ?? null),
                'possui_parente' => $sn($p['possui_parente_no_governo'] ?? null),
                'parentes' => array_values(array_filter(array_map(fn ($l) => [
                    'nome' => Texto::valor($l['parente_nome'] ?? null),
                    'cargo' => Texto::valor($l['parente_cargo'] ?? null),
                    'relacao' => Texto::valor($l['parente_relacao'] ?? null),
                    'orgao' => Texto::valor($l['parente_orgao'] ?? null),
                ], $linhas), fn ($x) => $x['nome'] !== null)),
                'possui_conjuge' => $sn($p['possui_conjuge_no_governo'] ?? null),
                'conjuge_nome' => Texto::valor($p['conjuge_nome'] ?? null),
                'conjuge_cargo' => Texto::valor($p['conjuge_cargo'] ?? null),
                'conjuge_orgao' => Texto::valor($p['conjuge_orgao'] ?? null),
            ]];
        }

        if ($linhas = $doServidor['acumulacao'] ?? null) {
            $a = $linhas[0];
            $resultado[] = ['acumulacao', [
                'data_declaracao' => Texto::data($a['data_declaracao'] ?? null),
                'acumula' => $sn($a['acumula'] ?? null),
                'cargo_acumulado' => Texto::valor($a['cargo_acumulado'] ?? null),
                'instituicao' => Texto::valor($a['instituicao'] ?? null),
                'horario_trabalho' => Texto::valor($a['horario_trabalho'] ?? null),
                'aposentado' => $sn($a['aposentado'] ?? null),
                'cargo_aposentadoria' => Texto::valor($a['cargo_aposentadoria'] ?? null),
                'fonte_proventos' => Texto::valor($a['fonte_proventos'] ?? null),
            ]];
        }

        // Bens: uma declaração por ano de referência, com uma linha por bem.
        foreach (collect($doServidor['bens'] ?? [])->groupBy(fn ($l) => Texto::inteiro($l['ano_referencia'] ?? null)) as $ano => $linhas) {
            $b = $linhas->first();
            $resultado[] = ['bens', [
                'data_declaracao' => Texto::data($b['data_declaracao'] ?? null),
                'ano_referencia' => $ano ?: null,
                'possui_bens' => $sn($b['possui_bens'] ?? null),
                'bens' => $linhas->filter(fn ($l) => Texto::valor($l['discriminacao'] ?? null) !== null)->map(fn ($l) => [
                    'titular' => str_starts_with(Texto::chave($l['titular'] ?? ''), 'conjuge') ? 'conjuge' : 'servidor',
                    'discriminacao' => Texto::valor($l['discriminacao']),
                    'valor' => Texto::decimal($l['valor'] ?? null),
                ])->values()->all(),
                'conjuge_nome' => $linhas->map(fn ($l) => Texto::valor($l['conjuge_nome'] ?? null))->filter()->first(),
                'conjuge_cpf' => $linhas->map(fn ($l) => Texto::valor($l['conjuge_cpf'] ?? null))->filter()->first(),
            ]];
        }

        $validas = [];
        foreach ($resultado as [$tipo, $dados]) {
            $dados = RegrasDeclaracao::normalizar($dados);
            $v = Validator::make($dados, RegrasDeclaracao::regras($tipo), RegrasDeclaracao::mensagens());

            if ($v->fails()) {
                $campo = array_key_first($v->errors()->messages());
                $this->divergir('aviso', $rotulo, 'Declaração de '.$tipo.' não será importada ('.$campo.': '.mb_strtolower(rtrim($v->errors()->first($campo), '.')).').');

                continue;
            }

            $ok = $v->validated();
            $validas[] = [
                'tipo' => $tipo,
                'data_declaracao' => $ok['data_declaracao'],
                'ano_referencia' => $ok['ano_referencia'] ?? null,
                'conteudo' => RegrasDeclaracao::montar($tipo, $ok),
            ];
        }

        return $validas;
    }

    // ---------------------------------------------------------------- resultado

    /** @param  list<array<string, mixed>>  $servidores */
    private function resultado(string $formato, array $servidores, int $linhas = 0): array
    {
        return [
            'formato' => $formato,
            'servidores' => $servidores,
            'resumo' => ['calculado' => [
                'linhas' => $linhas,
                'validas' => count($servidores),
                'com_erro' => count(array_filter($this->divergencias, fn ($d) => $d['nivel'] === 'erro')),
                'novos' => count(array_filter($servidores, fn ($s) => $s['existente_id'] === null)),
                'atualizados' => count(array_filter($servidores, fn ($s) => $s['existente_id'] !== null)),
                'sem_cargo' => count(array_filter($servidores, fn ($s) => $s['cargo_id'] === null)),
                'declaracoes' => array_sum(array_map(fn ($s) => count($s['declaracoes']), $servidores)),
            ], 'declarado' => null],
            'divergencias' => $this->divergencias,
        ];
    }

    private function divergir(string $nivel, ?string $cargo, string $mensagem): void
    {
        $this->divergencias[] = ['nivel' => $nivel, 'cargo' => $cargo, 'mensagem' => $mensagem];
    }
}
