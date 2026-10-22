<?php

namespace App\Importacao;

use App\Models\Dependente;
use App\Models\Servidor;
use App\Servidores\RegrasDependente;
use App\Support\Cpf;
use Illuminate\Support\Facades\Validator;

/**
 * Lê a planilha modelo 04 (dependentes) e confere cada linha com as mesmas regras
 * da tela. O titular é encontrado pelo CPF do servidor; o dependente que já existe
 * (mesmo CPF para o mesmo servidor) é atualizado. Linhas com erro ficam de fora.
 */
class AnalisadorDependentes
{
    private const PRIMEIRA_LINHA = 2;

    /** @var list<array{nivel: string, cargo: ?string, mensagem: string}> */
    private array $divergencias = [];

    public function __construct(private LeitorPlanilha $leitor = new LeitorPlanilha) {}

    /** @return array{formato: string, dependentes: list<array<string, mixed>>, resumo: array<string, mixed>, divergencias: list<array<string, mixed>>} */
    public function analisar(string $caminho, int $orgaoId): array
    {
        $this->divergencias = [];
        $linhas = $this->tabela($this->leitor->ler($caminho)['Dependentes'] ?? []);

        if ($linhas === null) {
            $this->divergir('bloqueia', null, 'Formato não reconhecido. Use a planilha modelo 04 (aba "Dependentes" com as colunas cpf_servidor e cpf_dependente na primeira linha).');

            return $this->resultado('desconhecido', []);
        }
        if ($linhas === []) {
            $this->divergir('bloqueia', null, 'A aba "Dependentes" não tem nenhuma linha preenchida a partir da 3ª linha.');

            return $this->resultado('modelo_dependentes', []);
        }

        $servidores = Servidor::withoutGlobalScopes()->where('orgao_id', $orgaoId)->get()->keyBy('cpf_hash');
        $existentes = Dependente::withoutGlobalScopes()->where('orgao_id', $orgaoId)->get()->keyBy(fn ($d) => $d->servidor_id.':'.$d->cpf_hash);
        $vistos = [];
        $conjuges = [];
        $dependentes = [];

        foreach ($linhas as $numero => $l) {
            $rotulo = 'Linha '.$numero.(($n = Texto::valor($l['nome_dependente'] ?? null)) ? ' · '.$n : '');
            $cpfServidor = $this->digitos($l['cpf_servidor'] ?? null);
            $servidor = Cpf::valido($cpfServidor) ? $servidores->get(Cpf::hash($cpfServidor)) : null;

            if ($servidor === null) {
                $this->divergir('erro', $rotulo, Cpf::valido($cpfServidor)
                    ? 'Não será importada: nenhum servidor do órgão tem o CPF '.Cpf::mascarar($cpfServidor).'. Importe o servidor (modelo 03) antes.'
                    : 'Não será importada: coluna "cpf_servidor" com CPF inválido.');

                continue;
            }

            $dados = $this->converter($l);
            $chave = $servidor->id.':'.Cpf::hash((string) $dados['cpf']);

            if (Cpf::valido($dados['cpf']) && isset($vistos[$chave])) {
                $this->divergir('erro', $rotulo, 'Não será importada: o mesmo dependente aparece de novo para o mesmo servidor (linha '.$vistos[$chave].').');

                continue;
            }

            $existente = $existentes->get($chave);
            $validador = Validator::make($dados, RegrasDependente::regras($servidor, $dados, $existente), RegrasDependente::mensagens());

            if ($validador->fails()) {
                $erros = [];
                foreach ($validador->errors()->messages() as $campo => $msgs) {
                    $erros[] = 'coluna "'.$this->coluna($campo).'": '.rtrim($msgs[0], '.');
                }
                $this->divergir('erro', $rotulo, 'Não será importada: '.implode('; ', array_slice($erros, 0, 4)).'.');

                continue;
            }

            // Dois cônjuges na mesma planilha: o banco ainda não tem nenhum, então a regra não pega.
            if (in_array($dados['parentesco'], Dependente::PARENTESCOS_CONJUGE, true)) {
                if (isset($conjuges[$servidor->id])) {
                    $this->divergir('erro', $rotulo, 'Não será importada: o servidor já tem cônjuge ou companheiro(a) na linha '.$conjuges[$servidor->id].'. Só um pode estar ativo.');

                    continue;
                }
                $conjuges[$servidor->id] = $numero;
            }
            $vistos[$chave] = $numero;

            $validos = $validador->validated();
            $teste = new Dependente($validos);
            foreach ($teste->alertas() as $alerta) {
                $this->divergir('aviso', $rotulo, $alerta.' Importa assim e fica como pendência na ficha.');
            }
            if ($existente && ! $existente->ativo()) {
                $this->divergir('aviso', $rotulo, 'Dependente encerrado no sistema em '.$existente->encerrado_em->format('d/m/Y').'. Os dados são atualizados, mas ele continua encerrado; reative na ficha se for o caso.');
            }
            if (! $servidor->ativo()) {
                $this->divergir('aviso', $rotulo, 'O servidor titular está desligado.');
            }

            $dependentes[] = [
                'linha' => $numero,
                'servidor_id' => $servidor->id,
                'servidor_nome' => $servidor->nome,
                'existente_id' => $existente?->id,
                'dados' => $validos,
            ];
        }

        return $this->resultado('modelo_dependentes', $dependentes, count($linhas));
    }

    /** @return array<int, array<string, mixed>>|null */
    private function tabela(array $linhas): ?array
    {
        $cabecalho = array_map(fn ($c) => trim(str_replace('*', '', (string) $c)), $linhas[0] ?? []);

        if (! in_array('cpf_servidor', $cabecalho, true) || ! in_array('cpf_dependente', $cabecalho, true)) {
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
            if (array_filter($registro, fn ($v) => Texto::valor($v) !== null) !== []) {
                $tabela[$i + 1] = $registro;
            }
        }

        return $tabela;
    }

    /** @return array<string, mixed> */
    private function converter(array $l): array
    {
        $texto = fn (string $c) => Texto::valor($l[$c] ?? null);

        $dados = RegrasDependente::normalizar([
            'nome' => $texto('nome_dependente'),
            'cpf' => $this->digitos($l['cpf_dependente'] ?? null),
            'data_nascimento' => Texto::data($l['data_nascimento'] ?? null) ?? ($texto('data_nascimento') === null ? null : 'data inválida'),
            'sexo' => mb_substr((string) $texto('sexo'), 0, 1) ?: null,
            'parentesco' => $texto('parentesco'),
            'declarar_ir' => $texto('declarar_ir'),
            'declarar_previdencia' => $texto('declarar_previdencia'),
            'salario_familia' => $texto('salario_familia'),
            'pcd' => $texto('pcd'),
            'tipo_deficiencia' => $texto('tipo_deficiencia'),
            'universitario' => $texto('universitario'),
        ]);

        // "IR e previdência" marcado vale pelas duas finalidades.
        if (in_array(mb_strtolower((string) $texto('declarar_ir_prev')), ['sim', 's', '1'], true)) {
            $dados['declarar_ir'] = true;
            $dados['declarar_previdencia'] = true;
        }

        return $dados;
    }

    private function digitos(mixed $v): string
    {
        $v = Cpf::somenteDigitos((string) Texto::valor($v));

        return $v === '' ? '' : str_pad($v, 11, '0', STR_PAD_LEFT);
    }

    /** Nome da coluna na planilha para cada campo. */
    private function coluna(string $campo): string
    {
        return ['nome' => 'nome_dependente', 'cpf' => 'cpf_dependente'][$campo] ?? $campo;
    }

    private function divergir(string $nivel, ?string $onde, string $mensagem): void
    {
        $this->divergencias[] = ['nivel' => $nivel, 'cargo' => $onde, 'mensagem' => $mensagem];
    }

    /** @param  list<array<string, mixed>>  $dependentes */
    private function resultado(string $formato, array $dependentes, int $linhas = 0): array
    {
        return [
            'formato' => $formato,
            'dependentes' => $dependentes,
            'resumo' => ['calculado' => [
                'linhas' => $linhas,
                'validas' => count($dependentes),
                'com_erro' => count(array_filter($this->divergencias, fn ($d) => $d['nivel'] === 'erro')),
                'novos' => count(array_filter($dependentes, fn ($d) => $d['existente_id'] === null)),
                'atualizados' => count(array_filter($dependentes, fn ($d) => $d['existente_id'] !== null)),
                'servidores' => count(array_unique(array_column($dependentes, 'servidor_id'))),
            ]],
            'divergencias' => $this->divergencias,
        ];
    }
}
