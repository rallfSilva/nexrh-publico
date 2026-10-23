<?php

namespace App\Ferias;

use App\Models\Ferias;
use App\Models\PeriodoAquisitivo;
use App\Models\RegraFerias;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Regras para marcar uma etapa de férias, iguais na tela do RH, no pedido do
 * servidor e na aprovação da chefia: saldo, número e tamanho das etapas,
 * liberação do período, antecedência do pedido e conflitos (as próprias férias,
 * afastamentos e o mínimo de presentes do setor).
 */
class Marcacao
{
    public function __construct(private Escala $escala) {}

    /**
     * @return array<string, string> erros por campo (periodo, inicio, dias)
     */
    public function conferir(Servidor $servidor, ?PeriodoAquisitivo $periodo, ?Carbon $inicio, ?int $dias, bool $peloServidor, ?Ferias $ignorar = null): array
    {
        if ($periodo === null || $periodo->servidor_id !== $servidor->id) {
            return ['periodo_aquisitivo_id' => 'Escolha o período aquisitivo.'];
        }
        if ($inicio === null) {
            return ['inicio' => 'Informe o primeiro dia de férias.'];
        }
        if ($dias === null || $dias < 1) {
            return ['dias' => 'Informe quantos dias.'];
        }

        $regra = RegraFerias::doOrgao($servidor->orgao_id);
        $periodo->loadMissing('ferias');
        $saldo = $periodo->saldo($ignorar);
        $etapas = $periodo->etapasMarcadas($ignorar);

        if ($saldo === 0) {
            return ['periodo_aquisitivo_id' => "O período {$periodo->rotulo()} não tem mais saldo."];
        }
        if ($dias > $saldo) {
            return ['dias' => "O saldo do período {$periodo->rotulo()} é de {$saldo} dias."];
        }
        if ($etapas >= $regra->max_etapas) {
            return ['periodo_aquisitivo_id' => "O período {$periodo->rotulo()} já tem {$etapas} etapas marcadas, o máximo permitido. Cancele uma antes de marcar outra."];
        }
        if ($dias < $regra->min_dias_etapa && $dias !== $saldo) {
            return ['dias' => "Cada etapa precisa ter pelo menos {$regra->min_dias_etapa} dias (ou todo o saldo que resta, {$saldo})."];
        }
        if ($etapas + 1 === $regra->max_etapas && $dias !== $saldo) {
            return ['dias' => "Esta é a última etapa permitida para o período {$periodo->rotulo()}: marque os {$saldo} dias que restam."];
        }
        $resto = $saldo - $dias;
        if ($resto > 0 && $resto < $regra->min_dias_etapa) {
            return ['dias' => "Sobrariam {$resto} dias, menos que o mínimo de {$regra->min_dias_etapa} por etapa. Marque {$saldo} dias, ou no máximo ".($saldo - $regra->min_dias_etapa).'.'];
        }

        $liberado = $periodo->liberadoEm();
        if ($inicio->lt($liberado)) {
            return ['inicio' => 'As férias do período '.$periodo->rotulo().' podem começar a partir de '.$liberado->format('d/m/Y').($liberado->eq($periodo->fim->copy()->addDay()) ? ', quando se completam os 12 meses de exercício.' : '.')];
        }

        if ($peloServidor) {
            $minimo = today()->addDays($regra->antecedencia_dias);
            if ($inicio->lt($minimo)) {
                return ['inicio' => "Peça com pelo menos {$regra->antecedencia_dias} dias de antecedência: a partir de {$minimo->format('d/m/Y')}. Para datas mais próximas, fale com o RH."];
            }
        }

        $fim = $inicio->copy()->addDays($dias - 1);
        $conflitos = $this->escala->conflitos($servidor, $inicio, $fim, $ignorar, detalhar: ! $peloServidor);

        return $conflitos === [] ? [] : ['inicio' => implode(' ', $conflitos)];
    }

    /** Marca a etapa, se passar nas regras. Pelo RH entra aprovada; pelo servidor, aguardando aprovação. */
    public function marcar(Servidor $servidor, PeriodoAquisitivo $periodo, Carbon $inicio, int $dias, User $quem, bool $peloServidor, array $extra = []): Ferias
    {
        return DB::transaction(function () use ($servidor, $periodo, $inicio, $dias, $quem, $peloServidor, $extra) {
            // Trava o servidor: dois pedidos ao mesmo tempo não passam juntos pela conferência.
            Servidor::whereKey($servidor->id)->lockForUpdate()->first();

            if ($erros = $this->conferir($servidor, $periodo->fresh(), $inicio, $dias, $peloServidor)) {
                throw ValidationException::withMessages($erros);
            }

            return Ferias::create([
                ...$extra,
                'orgao_id' => $servidor->orgao_id,
                'servidor_id' => $servidor->id,
                'periodo_aquisitivo_id' => $periodo->id,
                'etapa' => (int) $periodo->ferias()->max('etapa') + 1,
                'inicio' => $inicio,
                'fim' => $inicio->copy()->addDays($dias - 1),
                'dias' => $dias,
                'situacao' => $peloServidor ? 'programada' : 'aprovada',
                'solicitada_pelo_servidor' => $peloServidor,
                'solicitado_por' => $quem->id,
                'analisado_por' => $peloServidor ? null : $quem->id,
                'analisado_em' => $peloServidor ? null : now(),
            ]);
        });
    }
}
