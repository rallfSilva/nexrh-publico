<?php

namespace App\Importacao;

use App\Ferias\Escala;
use App\Ferias\Periodos;
use App\Models\Afastamento;
use App\Models\Ferias;
use App\Models\PeriodoAquisitivo;
use App\Models\RegraFerias;
use App\Models\Servidor;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

/**
 * Lê a planilha modelo 05 (férias) e confere cada linha com as regras da tela:
 * período aquisitivo igual aos do servidor (contados do ingresso), saldo, número
 * de etapas, coincidência com outras férias ou afastamentos e, para as férias
 * que ainda não terminaram, o mínimo de presentes do setor. Linhas com erro
 * ficam de fora; reimportar atualiza a etapa que começa no mesmo dia.
 */
class AnalisadorFerias
{
    use LinhasDoServidor;

    /** Situação da planilha => situação gravada. "Em gozo" e "Gozada" saem das datas. */
    private const SITUACOES = [
        'programada' => 'programada',
        'aprovada' => 'aprovada',
        'emgozo' => 'aprovada',
        'gozada' => 'aprovada',
        'cancelada' => 'cancelada',
        'interrompida' => 'interrompida',
    ];

    public function __construct(
        private LeitorPlanilha $leitor = new LeitorPlanilha,
        private Periodos $periodos = new Periodos,
        private Escala $escala = new Escala,
    ) {}

    /** @return array{formato: string, ferias: list<array<string, mixed>>, resumo: array<string, mixed>, divergencias: list<array<string, mixed>>} */
    public function analisar(string $caminho, int $orgaoId): array
    {
        $this->divergencias = [];
        $linhas = $this->tabela($this->leitor->ler($caminho)['Férias'] ?? [], ['aquisitivo_inicio', 'inicio_gozo', 'dias']);

        if ($linhas === null) {
            $this->divergir('bloqueia', null, 'Formato não reconhecido. Use a planilha modelo 05 (aba "Férias" com as colunas cpf_servidor ou matricula, aquisitivo_inicio, inicio_gozo e dias na primeira linha).');

            return $this->resultado('desconhecido', []);
        }
        if ($linhas === []) {
            $this->divergir('bloqueia', null, 'A aba "Férias" não tem nenhuma linha preenchida a partir da 3ª linha.');

            return $this->resultado('modelo_ferias', []);
        }

        $this->carregarServidores($orgaoId);
        $regra = RegraFerias::doOrgao($orgaoId);
        $hoje = today();

        // O que já está no sistema e o que a planilha vai acrescentando, por servidor.
        $existentes = Ferias::whereIn('servidor_id', $this->servidores->pluck('id'))->get()->groupBy('servidor_id');
        $afastamentos = Afastamento::validos()->whereIn('servidor_id', $this->servidores->pluck('id'))->get()->groupBy('servidor_id');
        $previstos = [];
        $bases = []; // servidor_id => data-base vinda da planilha (tempo de outro vínculo)
        $daPlanilha = []; // servidor_id => list<{inicio, fim, periodo, dias, linha, ocupa}>
        $ferias = [];

        foreach ($linhas as $numero => $l) {
            $rotulo = $this->rotuloDaLinha($numero, $l);
            $servidor = $this->servidorDaLinha($l, $rotulo);
            if ($servidor === null) {
                continue;
            }

            $aqInicio = Texto::data($l['aquisitivo_inicio'] ?? null);
            $aqFim = Texto::data($l['aquisitivo_fim'] ?? null);
            $inicio = Texto::data($l['inicio_gozo'] ?? null);
            $dias = Texto::inteiro($l['dias'] ?? null);
            $situacao = self::SITUACOES[Texto::chave(Texto::valor($l['situacao'] ?? null))] ?? null;
            $etapa = Texto::inteiro($l['etapa'] ?? null);

            $faltando = array_keys(array_filter([
                'aquisitivo_inicio' => $aqInicio === null,
                'inicio_gozo' => $inicio === null,
                'dias' => $dias === null || $dias < 1 || $dias > 90,
                'situacao' => $situacao === null,
            ]));
            if ($faltando !== []) {
                $this->divergir('erro', $rotulo, 'Não será importada: '.implode(', ', array_map(fn ($c) => 'coluna "'.$c.'" '.match ($c) {
                    'situacao' => 'vazia ou fora da lista',
                    'dias' => 'vazia ou com número inválido',
                    default => 'vazia ou com data inválida (use DD/MM/AAAA)',
                }, $faltando)).'.');

                continue;
            }

            // Período aquisitivo: precisa ser um dos períodos do servidor, contados do ingresso.
            $previstos[$servidor->id] ??= $this->periodos->previstos($servidor);
            $periodo = collect($previstos[$servidor->id])->first(fn ($p) => $p['inicio']->format('Y-m-d') === $aqInicio);

            // Sem férias no sistema, a planilha pode trazer outra data-base (tempo de outro vínculo com o Estado).
            $semFerias = ($existentes[$servidor->id] ?? collect())->isEmpty();
            $alinhado = isset($bases[$servidor->id]) && substr($aqInicio, 5) === $bases[$servidor->id]->format('m-d');
            if ($periodo === null && $semFerias && $aqInicio <= $hoje->format('Y-m-d') && (! isset($bases[$servidor->id]) ? ! isset($daPlanilha[$servidor->id]) : $alinhado)) {
                $base = Carbon::parse($aqInicio);
                if (isset($bases[$servidor->id]) && $bases[$servidor->id]->lt($base)) {
                    $base = $bases[$servidor->id];
                }
                $bases[$servidor->id] = $base;
                $previstos[$servidor->id] = array_map(fn ($p) => [...$p, 'existe' => false], $this->periodos->previstos($servidor, $base));
                $periodo = collect($previstos[$servidor->id])->first(fn ($p) => $p['inicio']->format('Y-m-d') === $aqInicio);
            }
            if ($periodo === null) {
                $lista = collect($previstos[$servidor->id])->map(fn ($p) => $p['inicio']->format('d/m/Y'));
                $detalhe = $lista->isEmpty() ? ' (sem data de ingresso)'
                    : ' (os mais recentes começam em '.$lista->take(-3)->join(', ', ' e ').'; a contagem parte de '.$lista->first().')';
                $this->divergir('erro', $rotulo, 'Não será importada: o período aquisitivo que começa em '.Carbon::parse($aqInicio)->format('d/m/Y').' não confere com os do servidor no sistema'.$detalhe.'.');

                continue;
            }
            if ($aqFim !== null && $aqFim !== $periodo['fim']->format('Y-m-d')) {
                $this->divergir('aviso', $rotulo, 'O fim do período aquisitivo na planilha ('.Carbon::parse($aqFim)->format('d/m/Y').') não é o do sistema ('.$periodo['fim']->format('d/m/Y').'). Vale o do sistema.');
            }

            $inicioC = Carbon::parse($inicio);
            $fimC = $inicioC->copy()->addDays($dias - 1);
            $ocupa = in_array($situacao, Ferias::OCUPAM, true);
            $existente = ($existentes[$servidor->id] ?? collect())->first(fn (Ferias $f) => $f->inicio->format('Y-m-d') === $inicio);

            if ($ocupa && ($erro = $this->conflito($servidor, $inicioC, $fimC, $existente, $existentes, $afastamentos, $daPlanilha, $aqInicio, $dias, $regra, $periodo))) {
                $this->divergir('erro', $rotulo, 'Não será importada: '.$erro);

                continue;
            }

            if ($ocupa && $dias < $regra->min_dias_etapa) {
                $this->divergir('aviso', $rotulo, "Etapa de {$dias} dias, abaixo do mínimo de {$regra->min_dias_etapa} das regras atuais. Importa assim, como registro.");
            }
            $label = Texto::chave(Texto::valor($l['situacao']));
            if ($label === 'gozada' && $fimC->gte($hoje)) {
                $this->divergir('aviso', $rotulo, 'Situação "Gozada", mas as férias vão até '.$fimC->format('d/m/Y').'. Entra como aprovada.');
            }
            if ($situacao === 'programada' && $fimC->lt($hoje)) {
                $this->divergir('aviso', $rotulo, 'Situação "Programada" (aguardando aprovação) para férias que já passaram. Confira se não deveria ser "Gozada".');
            }

            $daPlanilha[$servidor->id][] = ['inicio' => $inicioC, 'fim' => $fimC, 'periodo' => $aqInicio, 'dias' => $dias, 'linha' => $numero, 'ocupa' => $ocupa];
            $ferias[] = [
                'linha' => $numero,
                'servidor_id' => $servidor->id,
                'servidor_nome' => $servidor->nome,
                'unidade_sigla' => $servidor->unidade?->sigla,
                'existente_id' => $existente?->id,
                'periodo_inicio' => $aqInicio,
                'dados' => [
                    'etapa' => $etapa,
                    'inicio' => $inicio,
                    'fim' => $fimC->format('Y-m-d'),
                    'dias' => $dias,
                    'situacao' => $situacao,
                    'interrompida_em' => $situacao === 'interrompida' ? $fimC->copy()->addDay()->format('Y-m-d') : null,
                    'processo_sei' => Texto::valor($l['processo_sei'] ?? null),
                    'observacao' => ($o = Texto::valor($l['observacao'] ?? null)) ? mb_substr($o, 0, 255) : null,
                ],
            ];
        }

        if ($bases !== []) {
            $nomes = collect($bases)->map(fn ($base, $id) => $this->servidores->firstWhere('id', $id)->nome.' ('.$base->format('d/m/Y').')')->values();
            $this->divergir('aviso', count($bases).' servidor(es) com outra data-base', 'Os períodos aquisitivos passam a contar da data da planilha, e não da data de efeitos da nomeação. É o caso de quem traz tempo de outro vínculo com o Estado: '
                .$nomes->take(8)->implode(', ').($nomes->count() > 8 ? ' e mais '.($nomes->count() - 8) : '').'.');
        }
        foreach ($ferias as &$f) {
            $f['base'] = isset($bases[$f['servidor_id']]) ? $bases[$f['servidor_id']]->format('Y-m-d') : null;
        }
        unset($f);

        return $this->resultado('modelo_ferias', $ferias, count($linhas));
    }

    /**
     * O que impede a etapa de entrar: saldo, etapas, as próprias férias ou afastamentos
     * e o mínimo do setor (só para férias que ainda não terminaram).
     */
    private function conflito(Servidor $s, Carbon $inicio, Carbon $fim, ?Ferias $existente, $existentes, $afastamentos, array $daPlanilha, string $aqInicio, int $dias, RegraFerias $regra, array $periodo): ?string
    {
        $doServidor = ($existentes[$s->id] ?? collect())->filter(fn (Ferias $f) => $f->ocupa() && ! $f->is($existente));
        $planilha = collect($daPlanilha[$s->id] ?? [])->where('ocupa', true);

        // Saldo e etapas do período (sistema + planilha).
        $periodoId = $periodo['existe'] ? PeriodoAquisitivo::where('servidor_id', $s->id)->whereDate('inicio', $aqInicio)->value('id') : null;
        $noPeriodo = $doServidor->where('periodo_aquisitivo_id', $periodoId ?? -1);
        $usados = $noPeriodo->sum('dias') + $planilha->where('periodo', $aqInicio)->sum('dias');
        $etapas = $noPeriodo->count() + $planilha->where('periodo', $aqInicio)->count();
        $direito = $regra->dias_por_periodo;

        if ($usados + $dias > $direito) {
            return 'passa do saldo do período '.Carbon::parse($aqInicio)->format('Y').'/'.$periodo['fim']->format('Y').' ('.max(0, $direito - $usados).' dias restantes).';
        }
        if ($etapas >= $regra->max_etapas) {
            return 'o período já tem '.$etapas.' etapas, o máximo permitido.';
        }

        foreach ([...$doServidor->map(fn ($f) => ['inicio' => $f->inicio, 'fim' => $f->fim, 'linha' => null])->all(), ...$planilha->all()] as $outra) {
            if ($inicio->lte($outra['fim']) && $fim->gte($outra['inicio'])) {
                return 'coincide com férias de '.$outra['inicio']->format('d/m/Y').' a '.$outra['fim']->format('d/m/Y').($outra['linha'] ? ' (linha '.$outra['linha'].')' : ' já registradas no sistema').'.';
            }
        }
        foreach ($afastamentos[$s->id] ?? [] as $a) {
            if ($inicio->lte($a->fim) && $fim->gte($a->inicio)) {
                return 'coincide com licença ou afastamento de '.$a->periodoTexto().'.';
            }
        }

        // Mínimo do setor: o sistema mais o que esta planilha já aceitou.
        $unidade = $s->unidade;
        if ($unidade === null || ! $unidade->minimo_presentes_ferias || $fim->lt(today())) {
            return null;
        }
        $colegas = $this->servidores->where('unidade_id', $unidade->id)->where('situacao', 'ativo')->where('id', '!=', $s->id);
        $ausencias = $this->escala->ausencias($colegas->pluck('id')->all(), $inicio, $fim, $existente);
        foreach ($colegas as $c) {
            foreach (collect($daPlanilha[$c->id] ?? [])->where('ocupa', true) as $outra) {
                foreach (CarbonPeriod::create($outra['inicio']->max($inicio), $outra['fim']->min($fim)) as $dia) {
                    $ausencias[$dia->format('Y-m-d')][$c->id] = 'férias';
                }
            }
        }
        foreach (CarbonPeriod::create($inicio, $fim) as $dia) {
            $presentes = $colegas->count() - count($ausencias[$dia->format('Y-m-d')] ?? []);
            if ($presentes < $unidade->minimo_presentes_ferias) {
                return 'o setor '.$unidade->sigla.' ficaria com '.$presentes.' '.($presentes === 1 ? 'pessoa' : 'pessoas').' em '.$dia->format('d/m/Y').' (mínimo: '.$unidade->minimo_presentes_ferias.').';
            }
        }

        return null;
    }

    /** @param  list<array<string, mixed>>  $ferias */
    private function resultado(string $formato, array $ferias, int $linhas = 0): array
    {
        return [
            'formato' => $formato,
            'ferias' => $ferias,
            'resumo' => ['calculado' => [
                'linhas' => $linhas,
                'validas' => count($ferias),
                'com_erro' => $this->erros(),
                'novos' => count(array_filter($ferias, fn ($f) => $f['existente_id'] === null)),
                'atualizados' => count(array_filter($ferias, fn ($f) => $f['existente_id'] !== null)),
                'servidores' => count(array_unique(array_column($ferias, 'servidor_id'))),
            ]],
            'divergencias' => $this->divergencias,
        ];
    }
}
