<?php

namespace App\Ferias;

use App\Models\Afastamento;
use App\Models\Ferias;
use App\Models\Servidor;
use App\Models\Unidade;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Quem está fora do setor em cada dia: férias marcadas (aguardando aprovação,
 * aprovadas ou interrompidas, até a interrupção) e licenças ou afastamentos.
 * É daqui que saem o bloqueio pelo mínimo de presentes e as sugestões de período.
 */
class Escala
{
    /**
     * Servidores ativos lotados na unidade (sem as subordinadas: cada setor tem o próprio mínimo).
     *
     * @return Collection<int, Servidor>
     */
    public function equipe(Unidade $unidade): Collection
    {
        return Servidor::where('unidade_id', $unidade->id)->where('situacao', 'ativo')->orderBy('nome')->get();
    }

    /**
     * Ausências por dia: [Y-m-d => [servidor_id => descrição]].
     *
     * @param  list<int>  $servidores
     * @return array<string, array<int, string>>
     */
    public function ausencias(array $servidores, Carbon $inicio, Carbon $fim, ?Ferias $ignorar = null): array
    {
        $dias = [];

        $ferias = Ferias::ocupam()->whereIn('servidor_id', $servidores)->entre($inicio, $fim)
            ->when($ignorar?->exists, fn ($q) => $q->whereKeyNot($ignorar->id))->get(['id', 'servidor_id', 'inicio', 'fim', 'situacao']);
        $afastamentos = Afastamento::validos()->whereIn('servidor_id', $servidores)->entre($inicio, $fim)->get(['servidor_id', 'inicio', 'fim']);

        foreach ([[$ferias, 'férias'], [$afastamentos, 'afastamento']] as [$lista, $motivo]) {
            foreach ($lista as $item) {
                $de = $item->inicio->max($inicio);
                $ate = $item->fim->min($fim);
                foreach (CarbonPeriod::create($de, $ate) as $dia) {
                    $dias[$dia->format('Y-m-d')][$item->servidor_id] = $motivo;
                }
            }
        }

        return $dias;
    }

    /**
     * O que impede o servidor de estar fora no intervalo: as próprias férias ou
     * afastamentos no mesmo período e o setor abaixo do mínimo de presentes.
     *
     * @param  bool  $detalhar  mostra quem está fora (para RH e chefia; o servidor vê só a quantidade)
     * @return list<string>
     */
    public function conflitos(Servidor $servidor, Carbon $inicio, Carbon $fim, ?Ferias $ignorar = null, bool $detalhar = true): array
    {
        $erros = [];

        $propria = Ferias::ocupam()->where('servidor_id', $servidor->id)->entre($inicio, $fim)
            ->when($ignorar?->exists, fn ($q) => $q->whereKeyNot($ignorar->id))->orderBy('inicio')->first();
        if ($propria) {
            $erros[] = 'Já há férias marcadas de '.$propria->periodoTexto().' que coincidem com estas datas.';
        }

        $afastamento = Afastamento::validos()->where('servidor_id', $servidor->id)->entre($inicio, $fim)->orderBy('inicio')->first();
        if ($afastamento) {
            $erros[] = 'Há licença ou afastamento registrado de '.$afastamento->periodoTexto().', que coincide com estas datas.';
        }

        $unidade = $servidor->unidade;
        if ($unidade === null || ! $unidade->minimo_presentes_ferias) {
            return $erros;
        }

        $equipe = $this->equipe($unidade);
        $outros = $equipe->where('id', '!=', $servidor->id);
        $total = $equipe->count() - ($equipe->contains('id', $servidor->id) ? 1 : 0);
        $ausencias = $this->ausencias($outros->pluck('id')->all(), $inicio, $fim, $ignorar);
        $minimo = $unidade->minimo_presentes_ferias;

        $diasRuins = [];
        foreach (CarbonPeriod::create($inicio, $fim) as $dia) {
            $fora = $ausencias[$dia->format('Y-m-d')] ?? [];
            if ($total - count($fora) < $minimo) {
                $diasRuins[$dia->format('Y-m-d')] = $fora;
            }
        }

        if ($diasRuins !== []) {
            $primeiro = array_key_first($diasRuins);
            $fora = $diasRuins[$primeiro];
            $presentes = $total - count($fora);
            $quem = $outros->whereIn('id', array_keys($fora))->pluck('nome')->all();

            $erros[] = sprintf(
                'O setor %s ficaria com %d %s em %s (mínimo: %d)%s.%s',
                $unidade->sigla,
                $presentes,
                $presentes === 1 ? 'pessoa' : 'pessoas',
                Carbon::parse($primeiro)->format('d/m/Y'),
                $minimo,
                count($diasRuins) > 1 ? ' e em mais '.(count($diasRuins) - 1).' dia(s) do período' : '',
                $quem === [] ? '' : ($detalhar ? ' Fora nesse dia: '.implode(', ', $quem).'.' : ' '.count($quem).' colega(s) já estão fora nesse dia.'),
            );
        }

        return $erros;
    }

    /**
     * Primeiros intervalos livres a partir de uma data: sem conflito e com o
     * setor acima do mínimo. Prefere começar na segunda-feira.
     *
     * @return list<array{inicio: Carbon, fim: Carbon}>
     */
    public function sugestoes(Servidor $servidor, int $dias, Carbon $desde, int $quantas = 3, int $horizonteDias = 400): array
    {
        if ($dias < 1) {
            return [];
        }

        $ate = $desde->copy()->addDays($horizonteDias + $dias);
        $unidade = $servidor->unidade;
        $equipe = $unidade ? $this->equipe($unidade) : collect();
        $outros = $equipe->where('id', '!=', $servidor->id)->pluck('id')->all();
        $total = count($outros);
        $minimo = (int) $unidade?->minimo_presentes_ferias;

        $doSetor = $minimo > 0 ? $this->ausencias($outros, $desde, $ate) : [];
        $proprias = $this->ausencias([$servidor->id], $desde, $ate);

        $livre = function (Carbon $dia) use ($doSetor, $proprias, $total, $minimo) {
            $chave = $dia->format('Y-m-d');

            return ! isset($proprias[$chave]) && ($minimo === 0 || $total - count($doSetor[$chave] ?? []) >= $minimo);
        };

        $cabe = function (Carbon $inicio) use ($dias, $livre) {
            for ($i = 0; $i < $dias; $i++) {
                if (! $livre($inicio->copy()->addDays($i))) {
                    return false;
                }
            }

            return true;
        };

        $achados = [];
        foreach ([true, false] as $soSegunda) {
            $dia = $desde->copy();
            while (count($achados) < $quantas && $dia->lte($desde->copy()->addDays($horizonteDias))) {
                $sobrepoe = collect($achados)->contains(fn ($a) => $dia->lte($a['fim']) && $dia->copy()->addDays($dias - 1)->gte($a['inicio']));
                if ((! $soSegunda || $dia->isMonday()) && ! $sobrepoe && $cabe($dia)) {
                    $achados[] = ['inicio' => $dia->copy(), 'fim' => $dia->copy()->addDays($dias - 1)];
                    $dia->addDays($dias);

                    continue;
                }
                $dia->addDay();
            }
        }

        usort($achados, fn ($a, $b) => $a['inicio'] <=> $b['inicio']);

        return $achados;
    }
}
