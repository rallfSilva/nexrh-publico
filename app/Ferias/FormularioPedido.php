<?php

namespace App\Ferias;

use App\Models\PeriodoAquisitivo;
use App\Models\RegraFerias;
use App\Models\Servidor;
use Illuminate\Http\Request;

/** Formulário de marcação ou pedido de férias, igual para o RH e para o servidor. */
class FormularioPedido
{
    public function __construct(private Escala $escala, private Periodos $periodos) {}

    /**
     * Regras do pedido, iguais para o RH e para o servidor (as de negócio ficam em Marcacao).
     *
     * @return array<string, mixed>
     */
    public static function validar(Request $request, bool $doRh): array
    {
        return $request->validate([
            'periodo_aquisitivo_id' => ['required', 'integer'],
            'inicio' => ['required', 'date_format:Y-m-d', 'after:2000-01-01', 'before:2100-01-01'],
            'dias' => ['required', 'integer', 'min:1', 'max:90'],
            ...($doRh ? ['processo_sei' => ['nullable', 'string', 'max:40'], 'observacao' => ['nullable', 'string', 'max:255']] : []),
        ], [
            'periodo_aquisitivo_id.required' => 'Escolha o período aquisitivo.',
            'inicio.required' => 'Informe o primeiro dia de férias.',
            'inicio.date_format' => 'Data inválida.',
            'dias.required' => 'Informe quantos dias.',
            'dias.integer' => 'Informe um número de dias.',
            'dias.min' => 'Informe pelo menos 1 dia.',
        ]);
    }

    /** @return list<array{inicio: string, fim: string, texto: string}> */
    public function sugestoes(Servidor $servidor, Request $request, bool $peloServidor): array
    {
        $periodo = $servidor->periodosAquisitivos()->with('ferias')->find($request->integer('periodo'));
        $dias = min(90, max(0, $request->integer('dias')));

        if ($periodo === null || $dias === 0 || $dias > $periodo->saldo()) {
            return [];
        }

        $regra = RegraFerias::doOrgao($servidor->orgao_id);
        $desde = max($periodo->liberadoEm(), today()->addDays($peloServidor ? $regra->antecedencia_dias : 1));

        return array_map(fn ($s) => [
            'inicio' => $s['inicio']->format('Y-m-d'),
            'fim' => $s['fim']->format('Y-m-d'),
            'texto' => $s['inicio']->translatedFormat('D, d/m/Y').' a '.$s['fim']->format('d/m/Y'),
        ], $this->escala->sugestoes($servidor, $dias, $desde));
    }

    /** @return array<string, mixed> */
    public function dados(Request $request, Servidor $servidor, bool $propria): array
    {
        $this->periodos->sincronizar($servidor);
        $periodos = $servidor->periodosAquisitivos()->with('ferias')->get()->filter(fn (PeriodoAquisitivo $p) => $p->saldo() > 0)->values();
        $regra = RegraFerias::doOrgao($servidor->orgao_id);

        $escolhido = $periodos->firstWhere('id', (int) $request->old('periodo_aquisitivo_id', $request->integer('periodo'))) ?? $periodos->first();
        $dias = (int) $request->old('dias', $escolhido ? min($escolhido->saldo(), $regra->dias_por_periodo) : 0);

        $request->merge(['periodo' => $escolhido?->id, 'dias' => $dias]);

        return [
            'servidor' => $servidor->loadMissing('unidade'),
            'periodos' => $periodos,
            'regra' => $regra,
            'propria' => $propria,
            'escolhido' => $escolhido,
            'dias' => $dias,
            'sugestoes' => $escolhido ? $this->sugestoes($servidor, $request, $propria) : [],
        ];
    }
}
