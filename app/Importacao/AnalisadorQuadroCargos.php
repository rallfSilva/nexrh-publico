<?php

namespace App\Importacao;

use App\Models\Cargo;
use App\Support\Cpf;

/**
 * Lê a planilha do quadro de cargos e vagas e devolve os dados normalizados,
 * os totais e a lista de divergências. Não grava nada.
 *
 * Aceita dois formatos:
 * - "estrutura_selc": a planilha de controle usada hoje pela SELC (blocos por cargo,
 *   com cabeçalho de totais e uma linha por vaga);
 * - "modelo_nexrh": o modelo padrão 02_modelo_cargos_e_vagas.xlsx.
 *
 * Divergências têm três níveis: "bloqueia" (impede a importação), "erro" (inconsistência
 * da planilha; o sistema usa as linhas das vagas como verdade) e "aviso".
 */
class AnalisadorQuadroCargos
{
    /** @var list<array{nivel: string, cargo: ?string, mensagem: string}> */
    private array $divergencias = [];

    public function __construct(private LeitorPlanilha $leitor = new LeitorPlanilha) {}

    /** @return array{formato: string, cargos: list<array<string, mixed>>, resumo: array<string, mixed>, divergencias: list<array<string, mixed>>} */
    public function analisar(string $caminho): array
    {
        $this->divergencias = [];
        $abas = $this->leitor->ler($caminho);

        if ($this->ehModeloNexrh($abas)) {
            $formato = 'modelo_nexrh';
            $cargos = $this->lerModeloNexrh($abas);
            $declarado = null;
        } elseif (($aba = $this->abaEstruturaSelc($abas)) !== null) {
            $formato = 'estrutura_selc';
            [$cargos, $declarado] = $this->lerEstruturaSelc($abas[$aba]);
        } else {
            $this->divergir('bloqueia', null, 'Formato de planilha não reconhecido. Use o modelo padrão de cargos e vagas ou a planilha de estrutura de cargos da SELC.');

            return ['formato' => 'desconhecido', 'cargos' => [], 'resumo' => ['calculado' => $this->totais([]), 'declarado' => null], 'divergencias' => $this->divergencias];
        }

        if ($cargos === []) {
            $this->divergir('bloqueia', null, 'Nenhum cargo encontrado na planilha.');
        }

        $this->conferirCargosRepetidos($cargos);
        $this->conferirOcupantesRepetidos($cargos);

        $calculado = $this->totais($cargos);

        if ($declarado !== null) {
            $this->conferirResumoDeclarado($declarado, $calculado);
        }

        return [
            'formato' => $formato,
            'cargos' => $cargos,
            'resumo' => ['calculado' => $calculado, 'declarado' => $declarado],
            'divergencias' => $this->divergencias,
        ];
    }

    // ---------------------------------------------------------------- formato SELC

    /**
     * A SELC acrescenta uma aba por mês na mesma planilha: vale a última aba com o
     * quadro (a mais recente), e o relatório diz qual foi usada.
     *
     * @param  array<string, list<list<mixed>>>  $abas
     */
    private function abaEstruturaSelc(array $abas): ?string
    {
        $comQuadro = [];
        foreach ($abas as $nome => $linhas) {
            foreach (array_slice($linhas, 0, 40) as $linha) {
                if ($this->ehCabecalhoSelc($linha)) {
                    $comQuadro[] = (string) $nome;
                    break;
                }
            }
        }
        if (count($comQuadro) > 1) {
            $this->divergir('aviso', null, 'A planilha tem '.count($comQuadro).' abas com o quadro de cargos ('.implode(', ', $comQuadro).'). Foi usada a última, "'.end($comQuadro).'", a mais recente.');
        }

        return $comQuadro === [] ? null : end($comQuadro);
    }

    /** @param list<mixed> $linha */
    private function ehCabecalhoSelc(array $linha): bool
    {
        return str_starts_with(Texto::chave(Texto::valor($linha[0] ?? null)), 'leidecdecriacao');
    }

    /**
     * @param  list<list<mixed>>  $linhas
     * @return array{0: list<array<string, mixed>>, 1: ?array<string, int>}
     */
    private function lerEstruturaSelc(array $linhas): array
    {
        $cargos = [];
        $declarado = null;
        $i = 0;
        $total = count($linhas);

        while ($i < $total) {
            $linha = $linhas[$i];
            $chaveA = Texto::chave(Texto::valor($linha[0] ?? null));

            if (str_starts_with($chaveA, 'cargosdaestrutura')) {
                $declarado = $this->lerResumoSelc(array_slice($linhas, $i + 1));
                break;
            }

            if (! $this->ehCabecalhoSelc($linha)) {
                $i++;

                continue;
            }

            $rotuloDenominacao = Texto::chave(Texto::valor($linha[5] ?? null));
            $cab = $linhas[$i + 1] ?? [];
            $i += 2;

            $vagas = [];
            while ($i < $total && ! $this->ehCabecalhoSelc($linhas[$i]) && ! str_starts_with(Texto::chave(Texto::valor($linhas[$i][0] ?? null)), 'cargosdaestrutura')) {
                if (Texto::inteiro($linhas[$i][1] ?? null) !== null) {
                    $vagas[] = $linhas[$i];
                }
                $i++;
            }

            $cargos[] = $this->montarCargoSelc($cab, $vagas, str_contains($rotuloDenominacao, 'funcaogratificada'));
        }

        return [$cargos, $declarado];
    }

    /**
     * @param  list<mixed>  $cab
     * @param  list<list<mixed>>  $linhasVagas
     * @return array<string, mixed>
     */
    private function montarCargoSelc(array $cab, array $linhasVagas, bool $funcaoGratificada): array
    {
        $nomeCabecalho = Texto::valor($cab[5] ?? null) ?? '';
        $nomes = array_filter(array_map(fn ($l) => Texto::valor($l[5] ?? null), $linhasVagas));
        $simbolos = array_filter(array_map(fn ($l) => Texto::valor($l[6] ?? null), $linhasVagas));
        $basesLegais = array_filter(array_map(fn ($l) => Texto::valor($l[0] ?? null), $linhasVagas));

        $nome = $this->maisFrequente($nomes) ?? $nomeCabecalho;
        $denominacao = Texto::titulo($nome);
        $simbolo = mb_strtoupper($this->maisFrequente($simbolos) ?? 'SEM SÍMBOLO');

        if ($nomeCabecalho !== '' && Texto::chave($nomeCabecalho) !== Texto::chave($nome)) {
            $this->divergir('aviso', $denominacao, "O nome no cabeçalho (\"{$nomeCabecalho}\") é diferente do nome nas vagas (\"{$nome}\"). Foi usado o nome das vagas.");
        }

        $natureza = match (true) {
            Texto::chave($simbolo) === 'subsidio' => 'subsidio',
            $funcaoGratificada => 'funcao_gratificada',
            default => 'comissao',
        };

        $vagas = [];
        foreach ($linhasVagas as $l) {
            $vagas[] = $this->montarVagaSelc($l, $denominacao);
        }
        usort($vagas, fn ($a, $b) => $a['numero'] <=> $b['numero']);

        $cargo = [
            'denominacao' => $denominacao,
            'simbolo' => $simbolo,
            'natureza' => $natureza,
            'remuneracao' => Texto::decimal($cab[6] ?? null),
            'quantidade_vagas' => Texto::inteiro($cab[1] ?? null) ?? count($vagas),
            'base_legal' => $this->maisFrequente($basesLegais),
            'observacao' => null,
            'vagas' => $vagas,
        ];

        $this->conferirCabecalhoSelc($cargo, $cab);
        $this->conferirNumeracao($cargo);

        return $cargo;
    }

    /**
     * @param  list<mixed>  $l
     * @return array<string, mixed>
     */
    private function montarVagaSelc(array $l, string $denominacao): array
    {
        $numero = Texto::inteiro($l[1] ?? null);
        $texto = Texto::valor($l[7] ?? null) ?? '';
        $chave = Texto::chave($texto);
        $colunaCpf = Texto::valor($l[8] ?? null);
        $observacao = null;
        $orgaoRemanejamento = null;
        $ocupante = null;
        $cpf = null;

        if ($chave === '' || str_starts_with($chave, 'vago')) {
            $situacao = 'vaga';
        } elseif (str_starts_with($chave, 'cargoremanejado') || str_starts_with($chave, 'cargodevolvido')) {
            $situacao = str_starts_with($chave, 'cargodevolvido') ? 'devolvida' : 'remanejada';
            $orgaoRemanejamento = preg_match('/\bpara\s+(.+)$/iu', $texto, $m) ? trim($m[1]) : null;
            $observacao = $texto;
        } else {
            $situacao = 'ocupada';
            $ocupante = Texto::titulo($texto);
            $cpf = $colunaCpf;
        }

        if ($situacao !== 'ocupada' && $colunaCpf !== null && ! Cpf::valido($colunaCpf)) {
            $observacao = trim(($observacao ? $observacao.'. ' : '').$colunaCpf);
        }

        if ($situacao === 'ocupada') {
            if ($cpf === null) {
                $this->divergir('erro', $denominacao, "Vaga nº {$numero}: ocupante sem CPF.");
            } elseif (! Cpf::valido($cpf)) {
                $this->divergir('erro', $denominacao, "Vaga nº {$numero}: CPF do ocupante com formato ou dígito verificador inválido. Corrija na ficha do servidor.");
            }

            if (Texto::valor($l[9] ?? null) === null) {
                $this->divergir('aviso', $denominacao, "Vaga nº {$numero}: ocupada sem o número do decreto de nomeação.");
            }
        }

        return [
            'numero' => $numero,
            'base_legal' => Texto::valor($l[0] ?? null),
            'situacao' => $situacao,
            'orgao_remanejamento' => $orgaoRemanejamento,
            'ocupante_nome' => $ocupante,
            'ocupante_cpf' => $cpf !== null && Cpf::valido($cpf) ? Cpf::somenteDigitos($cpf) : $cpf,
            'ato_numero' => Texto::valor($l[9] ?? null),
            'ato_data' => Texto::data($l[10] ?? null),
            'efeito_data' => Texto::data($l[11] ?? null),
            'observacao' => $observacao,
        ];
    }

    /**
     * @param  array<string, mixed>  $cargo
     * @param  list<mixed>  $cab
     */
    private function conferirCabecalhoSelc(array $cargo, array $cab): void
    {
        $nome = $cargo['denominacao'];
        $contado = $this->contar($cargo['vagas']);
        $declarado = [
            'quantidade' => Texto::inteiro($cab[1] ?? null),
            'remanejadas' => Texto::inteiro($cab[2] ?? null),
            'ocupadas' => Texto::inteiro($cab[3] ?? null),
            'vagas' => Texto::inteiro($cab[4] ?? null),
        ];

        if ($declarado['quantidade'] !== null && $declarado['quantidade'] !== count($cargo['vagas'])) {
            $this->divergir('erro', $nome, "O cabeçalho informa {$declarado['quantidade']} vagas, mas a lista tem ".count($cargo['vagas']).' linhas.');
        }

        $rotulos = ['remanejadas' => 'remanejadas', 'ocupadas' => 'ocupadas', 'vagas' => 'vagas livres'];
        foreach ($rotulos as $campo => $rotulo) {
            if ($declarado[$campo] !== null && $declarado[$campo] !== $contado[$campo]) {
                $this->divergir('erro', $nome, "O cabeçalho informa {$declarado[$campo]} {$rotulo}, mas a lista tem {$contado[$campo]}.");
            }
        }

        if (! in_array(null, $declarado, true)) {
            $soma = $declarado['remanejadas'] + $declarado['ocupadas'] + $declarado['vagas'];
            if ($soma !== $declarado['quantidade']) {
                $this->divergir('erro', $nome, "Os números do cabeçalho não fecham: {$declarado['remanejadas']} remanejadas + {$declarado['ocupadas']} ocupadas + {$declarado['vagas']} livres = {$soma}, mas o cargo tem {$declarado['quantidade']} vagas.");
            }
        }
    }

    /**
     * @param  list<list<mixed>>  $linhas
     * @return array<string, int>
     */
    private function lerResumoSelc(array $linhas): array
    {
        $resumo = [];
        $mapa = [
            'totaldecargosdaestrutura' => 'total',
            'cargosremanejadosdaselcpoutros' => 'remanejadas',
            'totaldecargosdisponiveis' => 'disponiveis',
            'cargosocupados' => 'ocupadas',
            'cargosvagos' => 'vagas',
        ];

        foreach (array_slice($linhas, 0, 20) as $l) {
            $chave = Texto::chave(Texto::valor($l[0] ?? null));
            foreach ($mapa as $prefixo => $campo) {
                if (str_starts_with($chave, $prefixo) && ($n = Texto::inteiro($l[1] ?? null)) !== null) {
                    $resumo[$campo] = $n;
                }
            }
        }

        return $resumo;
    }

    // ---------------------------------------------------------------- modelo NexRH

    /** @param array<string, list<list<mixed>>> $abas */
    private function ehModeloNexrh(array $abas): bool
    {
        $cab = $abas['Cargos'][0] ?? [];

        return Texto::chave(Texto::valor($cab[0] ?? null)) === 'denominacao';
    }

    /**
     * @param  array<string, list<list<mixed>>>  $abas
     * @return list<array<string, mixed>>
     */
    private function lerModeloNexrh(array $abas): array
    {
        $naturezas = array_flip(array_map([Texto::class, 'chave'], Cargo::NATUREZAS));
        $cargos = [];

        foreach (array_slice($abas['Cargos'], 2, null, true) as $indice => $l) {
            $linha = $indice + 1;
            if (array_filter($l, fn ($v) => Texto::valor($v) !== null) === []) {
                continue;
            }

            $denominacao = Texto::valor($l[0] ?? null);
            $simbolo = Texto::valor($l[1] ?? null);
            $natureza = $naturezas[Texto::chave(Texto::valor($l[2] ?? null))] ?? null;
            $quantidade = Texto::inteiro($l[4] ?? null);

            $faltando = [];
            foreach (['denominacao' => $denominacao, 'simbolo' => $simbolo, 'natureza' => $natureza, 'quantidade_vagas' => $quantidade, 'base_legal' => Texto::valor($l[5] ?? null)] as $campo => $valor) {
                if ($valor === null) {
                    $faltando[] = $campo;
                }
            }
            if ($faltando !== []) {
                $this->divergir('bloqueia', $denominacao, "Aba Cargos, linha {$linha}: preencha corretamente ".implode(', ', $faltando).'.');

                continue;
            }

            $cargos[] = [
                'denominacao' => $denominacao,
                'simbolo' => mb_strtoupper($simbolo),
                'natureza' => $natureza,
                'remuneracao' => Texto::decimal($l[3] ?? null),
                'quantidade_vagas' => $quantidade,
                'base_legal' => Texto::valor($l[5] ?? null),
                'observacao' => Texto::valor($l[6] ?? null),
                'vagas' => array_map(fn ($n) => [
                    'numero' => $n, 'base_legal' => Texto::valor($l[5] ?? null), 'situacao' => 'vaga',
                    'orgao_remanejamento' => null, 'ocupante_nome' => null, 'ocupante_cpf' => null,
                    'ato_numero' => null, 'ato_data' => null, 'efeito_data' => null, 'observacao' => null,
                ], range(1, max(1, $quantidade))),
            ];
        }

        $this->aplicarRemanejamentosModelo($abas['Remanejamentos'] ?? [], $cargos);

        return $cargos;
    }

    /**
     * Marca como remanejadas as últimas vagas livres de cada cargo, conforme a aba Remanejamentos.
     *
     * @param  list<list<mixed>>  $linhas
     * @param  list<array<string, mixed>>  $cargos
     */
    private function aplicarRemanejamentosModelo(array $linhas, array &$cargos): void
    {
        foreach (array_slice($linhas, 2, null, true) as $indice => $l) {
            $linha = $indice + 1;
            if (array_filter($l, fn ($v) => Texto::valor($v) !== null) === []) {
                continue;
            }

            $chave = Texto::chave(Texto::valor($l[0] ?? null)).'|'.Texto::chave(Texto::valor($l[1] ?? null));
            $quantidade = Texto::inteiro($l[2] ?? null) ?? 0;
            $saida = str_starts_with(Texto::chave(Texto::valor($l[3] ?? null)), 'saida');
            $orgao = Texto::valor($l[4] ?? null);

            $alvo = null;
            foreach ($cargos as $k => $c) {
                if (Texto::chave($c['denominacao']).'|'.Texto::chave($c['simbolo']) === $chave) {
                    $alvo = $k;
                }
            }

            if ($alvo === null) {
                $this->divergir('bloqueia', Texto::valor($l[0] ?? null), "Aba Remanejamentos, linha {$linha}: o cargo não existe na aba Cargos.");

                continue;
            }

            if (! $saida) {
                // Entrada: os cargos recebidos já estão contados em quantidade_vagas; só registramos a origem.
                $cargos[$alvo]['observacao'] = trim(($cargos[$alvo]['observacao'] ?? '')." {$quantidade} recebido(s) de {$orgao}.");

                continue;
            }

            for ($v = count($cargos[$alvo]['vagas']) - 1; $v >= 0 && $quantidade > 0; $v--) {
                if ($cargos[$alvo]['vagas'][$v]['situacao'] === 'vaga') {
                    $cargos[$alvo]['vagas'][$v]['situacao'] = 'remanejada';
                    $cargos[$alvo]['vagas'][$v]['orgao_remanejamento'] = $orgao;
                    $cargos[$alvo]['vagas'][$v]['ato_numero'] = Texto::valor($l[5] ?? null);
                    $cargos[$alvo]['vagas'][$v]['ato_data'] = Texto::data($l[6] ?? null);
                    $quantidade--;
                }
            }

            if ($quantidade > 0) {
                $this->divergir('bloqueia', $cargos[$alvo]['denominacao'], "Aba Remanejamentos, linha {$linha}: há mais cargos remanejados do que vagas no cargo.");
            }
        }
    }

    // ---------------------------------------------------------------- conferências

    /** @param array<string, mixed> $cargo */
    private function conferirNumeracao(array $cargo): void
    {
        $numeros = array_column($cargo['vagas'], 'numero');
        $repetidos = array_keys(array_filter(array_count_values($numeros), fn ($n) => $n > 1));

        if ($repetidos !== []) {
            $this->divergir('bloqueia', $cargo['denominacao'], 'Número de vaga repetido: '.implode(', ', $repetidos).'. Cada vaga precisa de um número único dentro do cargo.');
        }

        $faltando = $numeros === [] ? [] : array_diff(range(1, max($numeros)), $numeros);
        if ($faltando !== []) {
            $this->divergir('aviso', $cargo['denominacao'], 'Numeração com lacunas: falta(m) a(s) vaga(s) nº '.implode(', ', $faltando).'.');
        }
    }

    /** @param list<array<string, mixed>> $cargos */
    private function conferirCargosRepetidos(array $cargos): void
    {
        $vistos = [];
        foreach ($cargos as $c) {
            $chave = Texto::chave($c['denominacao']).'|'.Texto::chave($c['simbolo']);
            if (isset($vistos[$chave])) {
                $this->divergir('bloqueia', $c['denominacao'], "O cargo aparece mais de uma vez com o símbolo {$c['simbolo']}. Junte as vagas em um só bloco.");
            }
            $vistos[$chave] = true;
        }
    }

    /** @param list<array<string, mixed>> $cargos */
    private function conferirOcupantesRepetidos(array $cargos): void
    {
        $porCpf = [];
        foreach ($cargos as $c) {
            foreach ($c['vagas'] as $v) {
                if ($v['situacao'] === 'ocupada' && $v['ocupante_cpf'] && Cpf::valido($v['ocupante_cpf'])) {
                    $porCpf[$v['ocupante_cpf']][] = "{$c['denominacao']} nº {$v['numero']}";
                }
            }
        }

        foreach ($porCpf as $cpf => $onde) {
            if (count($onde) > 1) {
                $this->divergir('erro', null, 'O mesmo CPF ('.Cpf::mascarar($cpf).') ocupa mais de uma vaga: '.implode('; ', $onde).'.');
            }
        }
    }

    /**
     * @param  array<string, int>  $declarado
     * @param  array<string, int>  $calculado
     */
    private function conferirResumoDeclarado(array $declarado, array $calculado): void
    {
        $rotulos = [
            'total' => 'Total de cargos da estrutura',
            'remanejadas' => 'Cargos remanejados para outros órgãos',
            'disponiveis' => 'Total de cargos disponíveis',
            'ocupadas' => 'Cargos ocupados',
            'vagas' => 'Cargos vagos',
        ];

        foreach ($rotulos as $campo => $rotulo) {
            if (isset($declarado[$campo]) && $declarado[$campo] !== $calculado[$campo]) {
                $this->divergir('erro', null, "Resumo da planilha: \"{$rotulo}\" informa {$declarado[$campo]}, mas a contagem das vagas dá {$calculado[$campo]}.");
            }
        }
    }

    // ---------------------------------------------------------------- utilidades

    /**
     * @param  list<array<string, mixed>>  $vagas
     * @return array{remanejadas: int, ocupadas: int, vagas: int}
     */
    private function contar(array $vagas): array
    {
        $situacoes = array_count_values(array_column($vagas, 'situacao'));

        return [
            'remanejadas' => ($situacoes['remanejada'] ?? 0) + ($situacoes['devolvida'] ?? 0),
            'ocupadas' => $situacoes['ocupada'] ?? 0,
            'vagas' => $situacoes['vaga'] ?? 0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $cargos
     * @return array{cargos: int, total: int, remanejadas: int, disponiveis: int, ocupadas: int, vagas: int}
     */
    private function totais(array $cargos): array
    {
        $t = ['cargos' => count($cargos), 'total' => 0, 'remanejadas' => 0, 'disponiveis' => 0, 'ocupadas' => 0, 'vagas' => 0];

        foreach ($cargos as $c) {
            $n = $this->contar($c['vagas']);
            $t['total'] += $c['quantidade_vagas'];
            $t['remanejadas'] += $n['remanejadas'];
            $t['ocupadas'] += $n['ocupadas'];
            $t['vagas'] += $n['vagas'];
        }
        $t['disponiveis'] = $t['total'] - $t['remanejadas'];

        return $t;
    }

    /** @param array<int, string> $valores */
    private function maisFrequente(array $valores): ?string
    {
        if ($valores === []) {
            return null;
        }
        $contagem = array_count_values($valores);
        arsort($contagem);

        return (string) array_key_first($contagem);
    }

    private function divergir(string $nivel, ?string $cargo, string $mensagem): void
    {
        $this->divergencias[] = ['nivel' => $nivel, 'cargo' => $cargo, 'mensagem' => $mensagem];
    }
}
