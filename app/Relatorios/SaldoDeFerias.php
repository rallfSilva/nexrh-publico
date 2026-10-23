<?php

namespace App\Relatorios;

use App\Ferias\Periodos;
use App\Models\PeriodoAquisitivo;
use App\Models\RegraFerias;
use App\Models\User;
use Illuminate\Support\Collection;

class SaldoDeFerias extends Relatorio
{
    public function __construct(private Periodos $periodos) {}

    public function chave(): string
    {
        return 'saldo-ferias';
    }

    public function titulo(): string
    {
        return 'Saldo de férias';
    }

    public function descricao(): string
    {
        return 'Quantos dias cada servidor ainda tem para marcar, e quem está acumulando períodos.';
    }

    public function icone(): string
    {
        return 'calendario';
    }

    public function permitido(User $user): bool
    {
        return $user->can('ver-escala');
    }

    public function filtros(User $user): array
    {
        return [
            'unidade' => $this->filtroUnidade($user),
            'mostrar' => ['rotulo' => 'Mostrar', 'opcoes' => ['com_saldo' => 'Só quem tem saldo', 'acumulados' => 'Só quem acumulou períodos'], 'vazio' => 'Todos os ativos'],
        ];
    }

    public function colunas(User $user): array
    {
        return ['servidor' => 'Servidor', 'unidade' => 'Setor', 'periodos' => 'Períodos com saldo', 'saldo' => 'Saldo (dias)', 'prazo' => 'Gozar o mais antigo até', 'alerta' => 'Situação'];
    }

    public function numericas(): array
    {
        return ['saldo'];
    }

    public function linhas(User $user, array $filtros): Collection
    {
        $servidores = $this->servidores($user, $filtros)->with('unidade')->where('situacao', 'ativo')->orderByRaw('unaccent(nome)')->get();

        return $servidores->map(function ($s) {
            $this->periodos->sincronizar($s);
            $s->load('periodosAquisitivos.ferias');
            $regra = RegraFerias::doOrgao($s->orgao_id);
            $comSaldo = $s->periodosAquisitivos->filter(fn (PeriodoAquisitivo $p) => $p->saldo() > 0);
            $vencidos = $comSaldo->filter->completo()->count();
            $maisAntigo = $comSaldo->first();

            return [
                'servidor' => $s->nome,
                'unidade' => $s->unidade?->sigla,
                'periodos' => $comSaldo->map(fn ($p) => $p->rotulo().' ('.$p->saldo().')')->implode(', ') ?: null,
                'saldo' => $comSaldo->sum(fn ($p) => $p->saldo()),
                'prazo' => $maisAntigo?->completo() ? $this->data($maisAntigo->fim->copy()->addYear()) : null,
                'alerta' => $vencidos >= $regra->max_periodos_acumulados ? "Acumulou {$vencidos} períodos" : ($vencidos > 0 ? 'Período vencido com saldo' : 'Em dia'),
                '_acumulou' => $vencidos >= $regra->max_periodos_acumulados,
            ];
        })
            ->when($filtros['mostrar'] === 'com_saldo', fn ($c) => $c->filter(fn ($l) => $l['saldo'] > 0))
            ->when($filtros['mostrar'] === 'acumulados', fn ($c) => $c->filter(fn ($l) => $l['_acumulou']))
            ->map(fn ($l) => array_diff_key($l, ['_acumulou' => 1]))
            ->values();
    }
}
