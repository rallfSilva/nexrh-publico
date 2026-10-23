<?php

namespace App\Importacao;

use App\Models\Afastamento;
use App\Models\Ferias;
use Illuminate\Support\Carbon;

/**
 * Lê a planilha modelo 06 (licenças e afastamentos). Não pode coincidir com outro
 * afastamento nem com férias marcadas do mesmo servidor. Reimportar atualiza o
 * afastamento do mesmo tipo que começa no mesmo dia. Não guarda dado médico.
 */
class AnalisadorAfastamentos
{
    use LinhasDoServidor;

    public function __construct(private LeitorPlanilha $leitor = new LeitorPlanilha) {}

    /** @return array{formato: string, afastamentos: list<array<string, mixed>>, resumo: array<string, mixed>, divergencias: list<array<string, mixed>>} */
    public function analisar(string $caminho, int $orgaoId): array
    {
        $this->divergencias = [];
        $linhas = $this->tabela($this->leitor->ler($caminho)['Afastamentos'] ?? [], ['tipo', 'inicio', 'fim']);

        if ($linhas === null) {
            $this->divergir('bloqueia', null, 'Formato não reconhecido. Use a planilha modelo 06 (aba "Afastamentos" com as colunas cpf_servidor ou matricula, tipo, inicio e fim na primeira linha).');

            return $this->resultado('desconhecido', []);
        }
        if ($linhas === []) {
            $this->divergir('bloqueia', null, 'A aba "Afastamentos" não tem nenhuma linha preenchida a partir da 3ª linha.');

            return $this->resultado('modelo_afastamentos', []);
        }

        $this->carregarServidores($orgaoId);
        $ids = $this->servidores->pluck('id');
        $existentes = Afastamento::whereIn('servidor_id', $ids)->get()->groupBy('servidor_id');
        $ferias = Ferias::ocupam()->whereIn('servidor_id', $ids)->get()->groupBy('servidor_id');
        $tipos = collect(Afastamento::TIPOS)->keyBy(fn ($t) => Texto::chave($t));
        $daPlanilha = [];
        $afastamentos = [];

        foreach ($linhas as $numero => $l) {
            $rotulo = $this->rotuloDaLinha($numero, $l);
            $servidor = $this->servidorDaLinha($l, $rotulo);
            if ($servidor === null) {
                continue;
            }

            $tipo = $tipos[Texto::chave(Texto::valor($l['tipo'] ?? null))] ?? null;
            $inicio = Texto::data($l['inicio'] ?? null);
            $fim = Texto::data($l['fim'] ?? null);

            $faltando = array_keys(array_filter(['tipo' => $tipo === null, 'inicio' => $inicio === null, 'fim' => $fim === null]));
            if ($faltando !== []) {
                $this->divergir('erro', $rotulo, 'Não será importada: '.implode(', ', array_map(fn ($c) => 'coluna "'.$c.'" '.($c === 'tipo' ? 'vazia ou fora da lista' : 'vazia ou com data inválida (use DD/MM/AAAA)'), $faltando)).'.');

                continue;
            }
            $inicioC = Carbon::parse($inicio);
            $fimC = Carbon::parse($fim);
            if ($fimC->lt($inicioC)) {
                $this->divergir('erro', $rotulo, 'Não será importada: o último dia ('.$fimC->format('d/m/Y').') é antes do primeiro ('.$inicioC->format('d/m/Y').').');

                continue;
            }

            $existente = ($existentes[$servidor->id] ?? collect())->first(fn (Afastamento $a) => $a->tipo === $tipo && $a->inicio->format('Y-m-d') === $inicio);
            $outros = [
                ...($existentes[$servidor->id] ?? collect())->filter(fn ($a) => $a->valido() && ! $a->is($existente))->map(fn ($a) => [$a->inicio, $a->fim, 'afastamento já registrado no sistema'])->all(),
                ...array_map(fn ($a) => [$a['inicio'], $a['fim'], 'afastamento da linha '.$a['linha']], $daPlanilha[$servidor->id] ?? []),
                ...($ferias[$servidor->id] ?? collect())->map(fn ($f) => [$f->inicio, $f->fim, 'férias marcadas no sistema'])->all(),
            ];
            foreach ($outros as [$de, $ate, $oque]) {
                if ($inicioC->lte($ate) && $fimC->gte($de)) {
                    $this->divergir('erro', $rotulo, 'Não será importada: coincide com '.$oque.' ('.$de->format('d/m/Y').' a '.$ate->format('d/m/Y').').');

                    continue 2;
                }
            }

            $daPlanilha[$servidor->id][] = ['inicio' => $inicioC, 'fim' => $fimC, 'linha' => $numero];
            $afastamentos[] = [
                'linha' => $numero,
                'servidor_id' => $servidor->id,
                'servidor_nome' => $servidor->nome,
                'unidade_sigla' => $servidor->unidade?->sigla,
                'existente_id' => $existente?->id,
                'dados' => [
                    'tipo' => $tipo,
                    'inicio' => $inicio,
                    'fim' => $fim,
                    'documento' => ($d = Texto::valor($l['ato_ou_documento'] ?? null)) ? mb_substr($d, 0, 120) : null,
                    'processo_sei' => Texto::valor($l['processo_sei'] ?? null),
                    'observacao' => ($o = Texto::valor($l['observacao'] ?? null)) ? mb_substr($o, 0, 255) : null,
                ],
            ];
        }

        return $this->resultado('modelo_afastamentos', $afastamentos, count($linhas));
    }

    /** @param  list<array<string, mixed>>  $afastamentos */
    private function resultado(string $formato, array $afastamentos, int $linhas = 0): array
    {
        return [
            'formato' => $formato,
            'afastamentos' => $afastamentos,
            'resumo' => ['calculado' => [
                'linhas' => $linhas,
                'validas' => count($afastamentos),
                'com_erro' => $this->erros(),
                'novos' => count(array_filter($afastamentos, fn ($a) => $a['existente_id'] === null)),
                'atualizados' => count(array_filter($afastamentos, fn ($a) => $a['existente_id'] !== null)),
                'servidores' => count(array_unique(array_column($afastamentos, 'servidor_id'))),
            ]],
            'divergencias' => $this->divergencias,
        ];
    }
}
