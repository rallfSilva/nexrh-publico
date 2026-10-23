<?php

namespace App\Importacao;

use App\Models\Servidor;
use App\Support\Cpf;
use Illuminate\Support\Collection;

/**
 * Partes comuns das planilhas de férias e afastamentos (modelos 05 e 06): leitura
 * da aba pela linha de cabeçalho, servidor achado pelo CPF ou pela matrícula e
 * registro das divergências.
 */
trait LinhasDoServidor
{
    private const PRIMEIRA_LINHA = 2;

    /** @var list<array{nivel: string, cargo: ?string, mensagem: string}> */
    private array $divergencias = [];

    /** @var Collection<int, Servidor> */
    private Collection $servidores;

    /**
     * Linhas da aba como [número da linha => [coluna => valor]], ou null se o cabeçalho não tem as colunas exigidas.
     *
     * @param  list<string>  $exigidas
     * @return array<int, array<string, mixed>>|null
     */
    private function tabela(array $linhas, array $exigidas): ?array
    {
        $cabecalho = array_map(fn ($c) => trim(str_replace('*', '', (string) $c)), $linhas[0] ?? []);

        if (array_diff($exigidas, $cabecalho) !== [] || (! in_array('cpf_servidor', $cabecalho, true) && ! in_array('matricula', $cabecalho, true))) {
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

    private function carregarServidores(int $orgaoId): void
    {
        $this->servidores = Servidor::with('unidade')->where('orgao_id', $orgaoId)->get();
    }

    /** Servidor pelo CPF (preferido) ou pela matrícula; null com a divergência já registrada. */
    private function servidorDaLinha(array $l, string $rotulo): ?Servidor
    {
        $cpf = Cpf::somenteDigitos((string) Texto::valor($l['cpf_servidor'] ?? null));
        $matricula = Texto::valor($l['matricula'] ?? null);

        if ($cpf !== '') {
            $cpf = str_pad($cpf, 11, '0', STR_PAD_LEFT);
            if (! Cpf::valido($cpf)) {
                $this->divergir('erro', $rotulo, 'Não será importada: coluna "cpf_servidor" com CPF inválido.');

                return null;
            }
            $servidor = $this->servidores->firstWhere('cpf_hash', Cpf::hash($cpf));
            if ($servidor === null) {
                $this->divergir('erro', $rotulo, 'Não será importada: nenhum servidor do órgão tem o CPF '.Cpf::mascarar($cpf).'. Importe o servidor (modelo 03) antes.');
            }

            return $servidor;
        }

        if ($matricula === null) {
            $this->divergir('erro', $rotulo, 'Não será importada: informe o CPF do servidor (coluna "cpf_servidor") ou a matrícula.');

            return null;
        }

        $achados = $this->servidores->filter(fn (Servidor $s) => in_array($matricula, [$s->matricula_comissionado, $s->matricula_efetiva], true));
        if ($achados->count() !== 1) {
            $this->divergir('erro', $rotulo, $achados->isEmpty()
                ? 'Não será importada: nenhum servidor do órgão tem a matrícula '.$matricula.'.'
                : 'Não será importada: a matrícula '.$matricula.' aparece em mais de um servidor. Use o CPF.');

            return null;
        }

        return $achados->first();
    }

    private function rotuloDaLinha(int $numero, array $l): string
    {
        return 'Linha '.$numero.(($n = Texto::valor($l['nome'] ?? null)) ? ' · '.$n : '');
    }

    private function divergir(string $nivel, ?string $onde, string $mensagem): void
    {
        $this->divergencias[] = ['nivel' => $nivel, 'cargo' => $onde, 'mensagem' => $mensagem];
    }

    private function erros(): int
    {
        return count(array_filter($this->divergencias, fn ($d) => $d['nivel'] === 'erro'));
    }
}
